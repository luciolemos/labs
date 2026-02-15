<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Repositories\SiteRepository;
use App\Services\AuthService;
use App\Services\ProvisionService;
use App\Services\SiteService;
use App\Services\Logger;
use Slim\App;

return static function (App $app): void {
    $config = require __DIR__ . '/Config/app.php';
    $repo = new SiteRepository($config['paths']['sites'] ?? '', $config['sites'] ?? []);
    $siteService = new SiteService($repo);
    $home = new HomeController($siteService, $config);
    $provision = new ProvisionService($config);
    $logger = new Logger($config['paths']['logs'] ?? (__DIR__ . '/../storage/logs/app.log'));
    $admin = new AdminController($siteService, $provision, $logger, $config);
    $auth = new AuthService($config);
    $authController = new AuthController($auth, $config);

    $app->get('/', [$home, 'index']);
    $app->get('/index.php[/]', [$home, 'index']);
    $app->get('/about', [$home, 'about']);
    $app->get('/login', [$authController, 'showLogin']);
    $app->post('/login', [$authController, 'login']);
    $app->get('/logout', [$authController, 'logout']);
    $app->get('/reset', [$authController, 'showReset']);

    $sessionAuth = static function ($request, $handler) use ($auth) {
        if (!$auth->check()) {
            $response = $handler->handle($request)->withStatus(302);
            return $response->withHeader('Location', '/login');
        }
        return $handler->handle($request);
    };

    $app->group('/admin', function ($group) use ($admin) {
        $group->get('', [$admin, 'index']);
        $group->post('/save', [$admin, 'save']);
        $group->post('/reprovision', [$admin, 'reprovision']);
    })->add($sessionAuth);

    $app->get('/api/sites', static function ($request, $response) use ($siteService) {
        $payload = [
            'data' => $siteService->all(),
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/validate', static function ($request, $response) use ($siteService) {
        $body = (string)$request->getBody();
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $siteService->validate($payload);
        $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/health', static function ($request, $response) {
        $response->getBody()->write('ok');
        return $response;
    });
};
