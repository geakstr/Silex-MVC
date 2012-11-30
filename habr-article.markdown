После прочтения [статьи](http://habrahabr.ru/post/160509/ "Как превратить Silex в полноценный PHP фреймворк"), рассказывающей как модифицировать [микро-фреймворк Silex](http://silex.sensiolabs.org/ "Silex micro-framework") под архитектуру MVC, у меня возникло двойственное впечатление. Способ имеет право на жизнь, однако:

1. в проекте не всегда нужна ORM, хочется иметь и простую реализацию Модели;
2. в Silex уже есть (хотя и не совсем явные) нативные контроллеры;
3. писать свои автозагрузчики, когда есть возможность добавить нужное в Composer — не есть хорошо.

Давайте посмотрим, что можно сделать.


Будем придерживаться следующей структуры приложения:

	+ project
	|	+ protected
	|		composer.json
	|		composer.phar
	|		composer.lock
	|		+ app
	|			+ Controllers
	|			+ Models
	|			+ Views
	|		+ vendor
	|		+ providers
	|			+ Providers
	|	+ public
	|		.htaccess
	|		index.php
	|		+ css
	|		+ img
	|		+ js

Я настроил виртуальный хост на папку `project/public`, поэтому `.htaccess` будет один

	# project/public/.htaccess
	<IfModule mod_rewrite.c>
	    Options -MultiViews
	    RewriteEngine On
	    RewriteCond %{REQUEST_FILENAME} !-f
	    RewriteRule ^ index.php [L]
	</IfModule>

### Устанавливаем Silex с помощью менеджера пакетов [Composer](http://getcomposer.org/ "Composer") ###
С недавних пор Silex поддерживает такой способ установки и вот почему нужно пользоваться им:
- возможность одной командой обновить все библиотеки до актуальных или необходимых версий;
- генерация одного автозагрузчика для всего необходимого.

Создадим файл `project/protected/composer.json` примерно такого содержания (в зависимости от того, что необходимо вам):

	{
		"require":{
			"silex/silex":"1.0.*@dev"
		},
		"autoload":{
	        "psr-0":{
	            "Providers" : "",
	            "Controllers" : "app/"
	        }
	    }
	}


Здесь мы хотим получить сам Silex и указываем, что наших Провайдеров (о них чуть дальше) будем загружать из `project/protected/Providers`, а Контроллеры из `project/protected/app/Controllers`.

Набор команд для установки:

	cd /path/to/project/protected
	curl -s http://getcomposer.org/installer | php
	php composer.phar install

### Silex Providers ###

[Providers](http://silex.sensiolabs.org/doc/providers.html "Silex Providers") — замечательная возможность Silex, позволяющая внедрить стороннюю функциональность. Провайдеры бывают двух типов: ControllerProviderInterface для контроллеров и ServiceProviderInterface для всего остального (в нашем случае — для Модели).


### Модель ###

Напишем простой Service Provider и загрузчик моделей.

	<?php
	// project/protected/Providers/ModelsServiceProvider.php

	namespace Providers;

	use Silex\Application;
	use Silex\ServiceProviderInterface;

	class ModelsServiceProvider implements ServiceProviderInterface {
		public function register(Application $app) {
			$app['models.path'] = array();
			$app['models']      = $app->share(function($app) {
				return new Models($app);
			});
		}
		public function boot(Application $app) {

		}
	}
	class Models {
		private $app;
		public function __construct(Application $app) {
			$this->app = $app;
		}
		public function load($modelName, $modelMethod, $data = array()) {
			require_once $this->app['models.path'] . $modelName . '.php';

			$Model = new $modelName($this->app);

			return $Model->$modelMethod($data);
		}

	}

С Моделью, в общем-то, всё. Теперь мы можем загрузить любую, находящуюся в директории `project/protected/app/Models` с помощью конструкции `$app['models']->load('Class', 'Method', $data)` с возможностью передать в нее нужные данные `$data`. Осталось лишь зарегистрировать нашего провайдера в Silex.

### Контроллер ###

Единственное, о чем нам непосредственно нужно позаботиться, так это об автозагрузке классов контроллеров, но это за нас уже сделал Composer. Так что теперь мы можем подключать контроллеры стандартным методом Silex `mount`. Посмотрим, как будет выглядеть файл index.php, простейший Контроллер и Модель.

*index.php*

	<?php // project/public/index.php

	require_once __DIR__ . '/../protected/vendor/autoload.php';

	$app = new Silex\Application();

	$app->register(new Providers\ModelsServiceProvider(), array(
		'models.path' => __DIR__ . '/../protected/app/models/'
	));

	$app->mount('/', new Controllers\Index());

	$app->run();

*Контроллер*

	<?php // project/protected/Controllers/Index.php

	namespace Controllers;

	use Silex\Application;
	use Silex\Route;
	use Silex\ControllerProviderInterface;
	use Silex\ControllerCollection;

	class Index implements ControllerProviderInterface {

		public function connect(Application $app) {
			$index = new ControllerCollection(new Route());

			$index->get('/', function() use ($app) {
				$label = $app['models']->load('Pages', 'index');

				return $label;
			});

			$index->get('/{name}', function($name) use ($app) {
				$name = $app['models']->load('Pages', 'hello', $name);

				return "Hello{$name}";
			});

			return $index;
		}
	}

Кратко по коду. Метод `connect()` говорит Silex, что роуты, описанные внутри, надо обрабатывать как часть контроллера, который описан в index.php (в данном случае базовым URL для этого контроллера является корень приложения — /). Далее создается переменная `$index`, она представляет собой что-то вроде частички `$app` и имеет только функции роутинга. Сами роуты пишутся как обычно.

*Модель*

	<?php // project/protected/Models/Pages.php

	class Pages {
		
		public function index() {
			return "Index";
		}

		public function hello($name) {
			return ", {$name}!";
		}

	}


Что в итоге? Простейшая реализация MVC, с возможностью в любой момент начать писать в стандартном для Silex стиле. В данном контексте не рассматривалось Представление, так как уже есть множество готовых решений, из который лично я предпочитаю использовать [Twig](http://twig.sensiolabs.org/ "Twig - The flexible, fast, and secure
template engine for PHP"), благо интеграция с Silex у него 100%.

Весь проект доступен на [github](https://github.com/geakstr/Silex-MVC "GitHub - Silex-MVC").









