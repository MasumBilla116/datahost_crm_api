<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

// set time zone from config.xml file
$xml=simplexml_load_file("../config/xml/config.xml");
if(!empty($xml->timeZone)){
    date_default_timezone_set($xml->timeZone);
}

use App\Middleware\LaravelApiMiddleware;
use DI\Container;
use DI\Bridge\Slim\Bridge as SlimAppFactory;

require_once __DIR__  . '/../vendor/autoload.php';


$container = new Container();

$settings = require_once __DIR__ . '/settings.php';

$settings($container);

$app = SlimAppFactory::create($container);

$middleware = require_once __DIR__ . '/middleware.php';
$middleware($app);

$routes = require_once  __DIR__ . '/routes.php';
$web = require_once  __DIR__ . '/web.php';

$routes($app);
$web($app);

$app->run();
