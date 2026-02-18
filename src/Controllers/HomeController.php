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
        $templateFilter = trim((string)($query['template'] ?? 'all'));
        if (!in_array($visibility, ['all', 'protected', 'public'], true)) {
            $visibility = 'all';
        }

        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = (int)($this->config['pagination']['per_page'] ?? 12);
        if ($perPage < 1) {
            $perPage = 12;
        }

        $templates = $this->loadTemplateLabels();
        $templateOptions = $this->buildTemplateOptions($templates);
        if (!$this->isValidTemplateFilter($templateFilter, $templateOptions)) {
            $templateFilter = 'all';
        }

        $allSites = $this->decorateSitesWithTemplateInfo($this->siteService->all(), $templates);
        $sitesFiltered = $this->filterSites($allSites, $search, $visibility, $templateFilter);
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
                'template' => $templateFilter,
            ],
            'templateOptions' => $templateOptions,
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

    public function readme(Request $request, Response $response): Response
    {
        $html = $this->render('readme', [
            'title' => 'README',
            'readmeText' => $this->loadReadmeText(),
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
        $data['basePath'] = $data['basePath'] ?? $this->resolveBasePath();

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewsPath . '/' . $view . '.php';
        $content = ob_get_clean();

        ob_start();
        require $viewsPath . '/layout.php';
        return ob_get_clean();
    }

    private function resolveBasePath(): string
    {
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return $basePath === '/' ? '' : $basePath;
    }

    private function loadReadmeText(): string
    {
        $path = dirname(__DIR__, 2) . '/README.md';
        if (!is_file($path) || !is_readable($path)) {
            return 'README.md nao encontrado neste ambiente.';
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return 'Nao foi possivel ler o README.md.';
        }

        return $raw;
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

    private function filterSites(array $sites, string $search, string $visibility, string $templateFilter): array
    {
        return array_values(array_filter($sites, static function ($site) use ($search, $visibility, $templateFilter): bool {
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

            if ($templateFilter === 'legacy' && (($site['template_kind'] ?? '') !== 'legacy')) {
                return false;
            }
            if ($templateFilter !== 'all' && $templateFilter !== 'legacy' && ($site['template_hint'] ?? '') !== $templateFilter) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(trim(implode(' ', [
                (string)($site['name'] ?? ''),
                (string)($site['description'] ?? ''),
                (string)($site['url'] ?? ''),
                (string)($site['template_label'] ?? ''),
                (string)($site['template_hint'] ?? ''),
            ])));

            return str_contains($haystack, mb_strtolower($search));
        }));
    }

    private function buildTemplateOptions(array $templateLabels): array
    {
        $options = [
            ['value' => 'all', 'label' => 'Todos templates'],
            ['value' => 'legacy', 'label' => 'Legado (manual)'],
        ];

        ksort($templateLabels, SORT_NATURAL);
        foreach ($templateLabels as $id => $label) {
            $options[] = [
                'value' => (string)$id,
                'label' => (string)$label,
            ];
        }

        return $options;
    }

    private function isValidTemplateFilter(string $value, array $options): bool
    {
        foreach ($options as $option) {
            if (($option['value'] ?? '') === $value) {
                return true;
            }
        }
        return false;
    }

    private function decorateSitesWithTemplateInfo(array $sites, array $templateLabels): array
    {
        $decorated = [];
        foreach ($sites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $decorated[] = array_merge(
                $site,
                $this->resolveTemplateInfo($site, $templateLabels),
                $this->resolveLocalAvailability($site)
            );
        }
        return $decorated;
    }

    private function resolveTemplateInfo(array $site, array $templateLabels): array
    {
        $requestedId = trim((string)($site['template'] ?? ''));
        $slug = $this->slugFromUrl((string)($site['url'] ?? ''));
        $marker = $this->readTemplateMarker($slug);

        if ($marker !== '' && $marker !== 'template-v4') {
            return [
                'template_label' => 'Legado (manual)',
                'template_hint' => $marker,
                'template_kind' => 'legacy',
            ];
        }
        if ($marker === '' && !empty($site['protected'])) {
            return [
                'template_label' => 'Legado (manual)',
                'template_hint' => 'sem marcador',
                'template_kind' => 'legacy',
            ];
        }

        $defaultId = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        $effectiveId = $requestedId !== '' ? $requestedId : $defaultId;
        $label = $templateLabels[$effectiveId] ?? $effectiveId;
        if ($label === '') {
            $label = 'Template';
        }

        return [
            'template_label' => $label,
            'template_hint' => $effectiveId,
            'template_kind' => 'managed',
        ];
    }

    private function loadTemplateLabels(): array
    {
        $dir = (string)($this->config['admin']['templates_dir'] ?? '/var/www/labs/templates');
        $labels = [];
        if (!is_dir($dir)) {
            return $labels;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return $labels;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $entry)) {
                continue;
            }
            $path = rtrim($dir, '/') . '/' . $entry;
            if (!is_dir($path)) {
                continue;
            }
            $metaFile = $path . '/template.json';
            $label = ucwords(str_replace(['-', '_', '.'], ' ', $entry));
            if (is_file($metaFile) && is_readable($metaFile)) {
                $raw = file_get_contents($metaFile);
                $meta = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($meta) && !empty($meta['label']) && is_string($meta['label'])) {
                    $label = $meta['label'];
                }
            }
            $labels[$entry] = $label;
        }

        return $labels;
    }

    private function slugFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $path = trim((string)($parts['path'] ?? ''), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $slug = $segments[0] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return '';
        }

        return $slug;
    }

    private function readTemplateMarker(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        $file = '/var/www/' . $slug . '/.site-template';
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return '';
        }

        return trim($raw);
    }

    private function resolveLocalAvailability(array $site): array
    {
        $url = trim((string)($site['url'] ?? ''));
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return [
                'local_managed' => false,
                'local_available' => true,
            ];
        }

        $host = (string)($parts['host'] ?? '');
        $managedHost = (string)($this->config['admin']['provision_host'] ?? '88.198.104.148');
        $slug = $this->slugFromUrl($url);
        $isManaged = $host !== '' && $host === $managedHost && $slug !== '';
        if (!$isManaged) {
            return [
                'local_managed' => false,
                'local_available' => true,
            ];
        }

        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $dir = $base . '/' . $slug;
        $exists = is_dir($dir);

        return [
            'local_managed' => true,
            'local_available' => $exists,
        ];
    }
}
