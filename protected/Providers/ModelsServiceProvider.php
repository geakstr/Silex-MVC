<?php
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