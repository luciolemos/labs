<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class HomeController
{
    public function __construct(private Twig $twig, private array $config)
    {
    }

    public function home(Request $request, Response $response): Response
    {
        $copyMode = (string) ($request->getQueryParams()['copy'] ?? 'soft');
        if (!in_array($copyMode, ['soft', 'growth'], true)) {
            $copyMode = 'soft';
        }

        return $this->twig->render($response, 'pages/home.twig', [
            'app_name' => $this->config['app_name'] ?? 'Agência',
            'app_mark' => $this->config['app_mark'] ?? 'A',
            'page_title' => $this->config['page_title'] ?? null,
            'copy_mode' => $copyMode,
            'asset_version' => $this->assetVersion(),
        ]);
    }

    private function assetVersion(): string
    {
        $base = dirname(__DIR__, 2) . '/public/assets';
        $files = [
            $base . '/css/app.css',
            $base . '/css/landing.css',
            $base . '/js/landing.js',
        ];

        $parts = [];
        foreach ($files as $file) {
            $parts[] = is_file($file) ? (string)filemtime($file) : '0';
        }

        return implode('-', $parts);
    }
}
