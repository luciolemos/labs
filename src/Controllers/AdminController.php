<?php

namespace App\Controllers;

use App\Services\ProvisionService;
use App\Services\SiteService;
use App\Services\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AdminController
{
    public function __construct(
        private SiteService $siteService,
        private ProvisionService $provision,
        private Logger $logger,
        private array $config,
    ) {
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        $query = $request->getQueryParams();
        $saved = ($query['saved'] ?? '') === '1';

        $sites = $args['sites'] ?? $this->siteService->all();
        if ($sites === []) {
            $sites = [['name' => '', 'description' => '', 'url' => '']];
        }
        $errors = $args['errors'] ?? [];
        $provision = $args['provision'] ?? ($_SESSION['provision'] ?? []);
        unset($_SESSION['provision']);

        $html = $this->render('admin', [
            'title' => 'Admin',
            'sites' => $sites,
            'errors' => $errors,
            'saved' => $saved,
            'provision' => $provision,
            'provisionBySlug' => $this->mapProvisionBySlug($provision),
            'auth' => [
                'logged' => !empty($_SESSION['auth_user']),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function save(Request $request, Response $response): Response
    {
        $previousSites = $this->siteService->all();

        $data = $request->getParsedBody();
        $names = $data['sites']['name'] ?? [];
        $descriptions = $data['sites']['description'] ?? [];
        $urls = $data['sites']['url'] ?? [];
        $protected = $data['sites']['protected'] ?? [];

        $items = [];
        $max = max(count($names), count($descriptions), count($urls), count($protected));
        for ($i = 0; $i < $max; $i += 1) {
            $items[] = [
                'name' => $names[$i] ?? '',
                'description' => $descriptions[$i] ?? '',
                'url' => $urls[$i] ?? '',
                'protected' => !empty($protected[$i]),
            ];
        }

        $result = $this->siteService->validateList($items);
        $this->logger->info('admin.save', [
            'items' => $items,
            'result' => $result,
        ]);
        if (!$result['valid']) {
            return $this->index($request, $response, [
                'sites' => $items,
                'errors' => $result['errors'],
            ]);
        }

        $this->siteService->save($result['clean']);

        $provision = $this->provision->provision($result['clean']);
        $deprovision = $this->provision->deprovisionRemoved($previousSites, $result['clean']);
        if (!empty($deprovision)) {
            $provision = array_merge($provision, $deprovision);
        }
        if (!empty($provision)) {
            $_SESSION['provision'] = $provision;
        }

        return $response
            ->withHeader('Location', '/admin?saved=1')
            ->withStatus(302);
    }

    public function reprovision(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $existing = $this->siteService->all();
        $site = [
            'name' => $data['name'] ?? '',
            'url' => $data['url'] ?? '',
        ];

        foreach ($existing as $item) {
            if (($item['url'] ?? '') === $site['url'] && !empty($item['protected'])) {
                return $this->index($request, $response, [
                    'sites' => $existing,
                    'errors' => [0 => ['url' => 'Este site esta protegido contra reprovisionamento.']],
                ]);
            }
        }

        $result = $this->siteService->validate($site);
        if (!$result['valid']) {
            return $this->index($request, $response, [
                'sites' => $this->siteService->all(),
                'errors' => [0 => $result['errors']],
            ]);
        }

        $provision = $this->provision->reprovisionOne($site, true);
        if (!empty($provision)) {
            $_SESSION['provision'] = $provision;
        }

        return $response
            ->withHeader('Location', '/admin?saved=1')
            ->withStatus(302);
    }

    private function mapProvisionBySlug(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $slug = $item['slug'] ?? '';
            if ($slug !== '') {
                $map[$slug] = $item;
            }
        }
        return $map;
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
