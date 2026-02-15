<?php

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private array $config,
    ) {
    }

    public function showLogin(Request $request, Response $response, array $args = []): Response
    {
        $query = $request->getQueryParams();
        $error = $args['error'] ?? (($query['error'] ?? '') === '1');

        $html = $this->render('login', [
            'title' => 'Login',
            'error' => $error,
            'auth' => [
                'logged' => !empty($_SESSION['auth_user']),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = trim((string)($data['user'] ?? ''));
        $pass = trim((string)($data['pass'] ?? ''));

        if (!$this->auth->attempt($user, $pass)) {
            return $response
                ->withHeader('Location', '/login?error=1')
                ->withStatus(302);
        }

        $this->auth->login($user);

        return $response
            ->withHeader('Location', '/admin')
            ->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    public function showReset(Request $request, Response $response): Response
    {
        $html = $this->render('reset', [
            'title' => 'Reset de senha',
            'auth' => [
                'logged' => !empty($_SESSION['auth_user']),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    private function render(string $view, array $data = []): string
    {
        $viewsPath = dirname(__DIR__, 2) . '/views';

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewsPath . '/' . $view . '.php';
        $content = ob_get_clean();

        ob_start();
        require $viewsPath . '/layout.php';
        return ob_get_clean();
    }
}
