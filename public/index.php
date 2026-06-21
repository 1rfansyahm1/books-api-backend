<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\JsonBodyParser()); // ← Step 13
$app->add(new App\Middleware\Cors()); // ← Step 14
$app->addErrorMiddleware(true, true, true);
(require __DIR__ . '/../src/routes.php')($app);
$app->run();