<?php

namespace App\Controllers;

use App\Services\SiteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HomeController
{
    public function __construct(
        private SiteService $siteService,
        private array $config,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $search = trim((string)($query['q'] ?? ''));
        $visibility = (string)($query['visibility'] ?? 'all');
        if (!in_array($visibility, ['all', 'protected', 'public'], true)) {
            $visibility = 'all';
        }

        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = (int)($this->config['pagination']['per_page'] ?? 12);
        if ($perPage < 1) {
            $perPage = 12;
        }

        $allSites = $this->siteService->all();
        $sitesFiltered = $this->filterSites($allSites, $search, $visibility);
        $total = count($sitesFiltered);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $sites = array_slice($sitesFiltered, $offset, $perPage);

        $html = $this->render('home', [
            'title' => $this->config['name'] ?? 'Painel de Sites',
            'sites' => $sites,
            'filters' => [
                'q' => $search,
                'visibility' => $visibility,
            ],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'meta' => [
                'env' => $this->config['env'] ?? 'production',
                'php' => PHP_VERSION,
                'apache' => $this->getApacheVersion(),
                'host' => $this->getHostname(),
                'uptime' => $this->formatUptime($this->getUptimeSeconds()),
                'lastProvision' => $this->getLastProvisionedAt(),
                'total' => count($allSites),
                'filteredTotal' => $total,
                'perPage' => $perPage,
            ],
            'auth' => [
                'logged' => !empty($_SESSION['auth_user']),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function about(Request $request, Response $response): Response
    {
        $html = $this->render('about', [
            'title' => 'Sobre',
            'app' => $this->config,
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

    private function getApacheVersion(): string
    {
        if (function_exists('apache_get_version')) {
            return (string)apache_get_version();
        }

        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';
        return is_string($server) ? $server : '';
    }

    private function getHostname(): string
    {
        $name = gethostname();
        return $name !== false ? $name : '';
    }

    private function getUptimeSeconds(): ?int
    {
        $path = '/proc/uptime';
        if (!is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $parts = preg_split('/\\s+/', trim($raw));
        if (!$parts || !isset($parts[0])) {
            return null;
        }

        return (int)floor((float)$parts[0]);
    }

    private function formatUptime(?int $seconds): string
    {
        if ($seconds === null || $seconds < 1) {
            return '';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return sprintf('%dd %dh %dm', $days, $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    private function getLastProvisionedAt(): string
    {
        $path = $this->config['paths']['storage'] . '/data/provisioned.json';
        if (!is_readable($path)) {
            return '';
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return '';
        }

        $last = 0;
        foreach ($data as $value) {
            if (is_int($value)) {
                $last = max($last, $value);
            } elseif (is_numeric($value)) {
                $last = max($last, (int)$value);
            }
        }

        if ($last <= 0) {
            return '';
        }

        return date('d/m/Y H:i', $last);
    }

    private function filterSites(array $sites, string $search, string $visibility): array
    {
        return array_values(array_filter($sites, static function ($site) use ($search, $visibility): bool {
            if (!is_array($site)) {
                return false;
            }

            $isProtected = !empty($site['protected']);
            if ($visibility === 'protected' && !$isProtected) {
                return false;
            }
            if ($visibility === 'public' && $isProtected) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(trim(implode(' ', [
                (string)($site['name'] ?? ''),
                (string)($site['description'] ?? ''),
                (string)($site['url'] ?? ''),
            ])));

            return str_contains($haystack, mb_strtolower($search));
        }));
    }
}
