<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Core\Env;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../src/Core/Env.php';
Env::load(__DIR__ . '/../.env');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo '<h1>Dependencias ausentes</h1><p>Rode <code>composer install</code> neste projeto.</p>';
    exit;
}

require $autoload;

$base = $_ENV['APP_BASE'] ?? '';

$twig = Twig::create(__DIR__ . '/../views', [
    'cache' => false,
    'auto_reload' => true,
]);
$twig->getEnvironment()->addGlobal('base_url', $base);
$twig->getEnvironment()->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'prod');
$twig->getEnvironment()->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'Agência');
$twig->getEnvironment()->addGlobal('app_mark', $_ENV['APP_MARK'] ?? 'A');
$twig->getEnvironment()->addGlobal('app_badge', $_ENV['APP_BADGE'] ?? 'PHP 8.3+');
$twig->getEnvironment()->addGlobal('github_url', $_ENV['GITHUB_URL'] ?? '#');
$twig->getEnvironment()->addGlobal('x_url', $_ENV['X_URL'] ?? 'https://x.com');
$twig->getEnvironment()->addGlobal('instagram_url', $_ENV['INSTAGRAM_URL'] ?? 'https://instagram.com');
$twig->getEnvironment()->addGlobal('whatsapp_url', $_ENV['WHATSAPP_URL'] ?? 'https://wa.me/5584998087340');

$controller = new HomeController($twig, [
    'app_name' => $_ENV['APP_NAME'] ?? 'Agência',
    'app_mark' => $_ENV['APP_MARK'] ?? 'A',
    'page_title' => $_ENV['APP_PAGE_TITLE'] ?? null,
]);

$app = AppFactory::create();
$app->setBasePath($base);
$app->add(TwigMiddleware::create($app, $twig));

$isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';
$app->addErrorMiddleware($isDev, $isDev, $isDev);

$routes = require __DIR__ . '/../routes/web.php';
$routes($app, $controller);

$app->run();

