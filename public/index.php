<?php

require_once __DIR__ . '/../protected/vendor/autoload.php';

$app = new Silex\Application();

$app->register(new Providers\ModelsServiceProvider(), array(
	'models.path' => __DIR__ . '/../protected/app/models/'
));

$app->mount('/', new Controllers\Index());

$app->run();