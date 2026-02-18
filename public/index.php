<?php

use App\Config\Env;
use App\Services\Logger;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$app = AppFactory::create();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

$config = require __DIR__ . '/../src/Config/app.php';
$logger = new Logger($config['paths']['logs'] ?? (__DIR__ . '/../storage/logs/app.log'));

/**
 * Renderiza uma view simples dentro do layout padrao do painel.
 */
$renderWithLayout = static function (string $view, array $data = []): string {
    $viewsPath = dirname(__DIR__) . '/views';
    $basePathLocal = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($basePathLocal === '/') {
        $basePathLocal = '';
    }
    $data['basePath'] = $data['basePath'] ?? $basePathLocal;
    extract($data, EXTR_SKIP);

    ob_start();
    require $viewsPath . '/' . $view . '.php';
    $content = ob_get_clean();

    ob_start();
    require $viewsPath . '/layout.php';
    return (string)ob_get_clean();
};

$errorMiddleware = $app->addErrorMiddleware((bool)($config['debug'] ?? false), true, true);
$notFoundHandler = static function ($request, Throwable $exception) use ($app, $config, $logger, $renderWithLayout) {
    $logger->info('http.404', [
        'path' => (string)$request->getUri()->getPath(),
    ]);

    $response = $app->getResponseFactory()->createResponse(404);
    $html = $renderWithLayout('404', [
        'title' => '404 - Pagina nao encontrada',
        'path' => (string)$request->getUri()->getPath(),
        'meta' => [
            'env' => $config['env'] ?? 'production',
        ],
        'auth' => [
            'logged' => !empty($_SESSION['auth_user']),
        ],
    ]);

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
};
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, $notFoundHandler);
$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, $notFoundHandler);
$errorMiddleware->setDefaultErrorHandler(function (
    $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app, $logger, $config) {
    $logger->error($exception->getMessage(), [
        'type' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    $status = $exception instanceof HttpException && $exception->getCode() > 0
        ? $exception->getCode()
        : 500;

    $response = $app->getResponseFactory()->createResponse($status);
    if (!empty($config['debug'])) {
        $response->getBody()->write('<pre>' . htmlspecialchars((string)$exception) . '</pre>');
        return $response->withHeader('Content-Type', 'text/html');
    }

    $response->getBody()->write('Erro interno.');
    return $response->withHeader('Content-Type', 'text/plain');
});

$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

$app->run();
