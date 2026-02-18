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
        $saved = !empty($_SESSION['saved']);
        unset($_SESSION['saved']);
        $savedMessage = trim((string)($_SESSION['saved_message'] ?? ''));
        unset($_SESSION['saved_message']);
        $templates = $this->loadTemplates();
        $templateDefault = $this->defaultTemplateId($templates);

        $sites = $args['sites'] ?? $this->siteService->all();
        if ($sites === []) {
            $sites = [[
                'name' => '',
                'description' => '',
                'url' => '',
                'template' => $templateDefault,
            ]];
        } else {
            $templateLabels = $this->templateLabelMap($templates);
            foreach ($sites as &$site) {
                if (!is_array($site)) {
                    continue;
                }
                $site['template'] = $this->normalizeTemplateId((string)($site['template'] ?? ''), $templates, $templateDefault);
                $site = array_merge($site, $this->resolveTemplateUsageInfo($site, $templateLabels));
            }
            unset($site);
        }
        $errors = $args['errors'] ?? [];
        $permissionHelp = $args['permissionHelp'] ?? $this->buildPermissionHelp($errors);
        $provision = $args['provision'] ?? ($_SESSION['provision'] ?? []);
        unset($_SESSION['provision']);

        $html = $this->render('admin', [
            'title' => 'Admin',
            'sites' => $sites,
            'errors' => $errors,
            'permissionHelp' => $permissionHelp,
            'saved' => $saved,
            'savedMessage' => $savedMessage,
            'provision' => $provision,
            'provisionBySlug' => $this->mapProvisionBySlug($provision),
            'templates' => $templates,
            'templateDefault' => $templateDefault,
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
        $templates = $this->loadTemplates();
        $templateDefault = $this->defaultTemplateId($templates);
        $templateIds = $data['sites']['template'] ?? [];

        $items = [];
        $max = max(count($names), count($descriptions), count($urls), count($protected), count($templateIds));
        for ($i = 0; $i < $max; $i += 1) {
            $items[] = [
                'name' => $names[$i] ?? '',
                'description' => $descriptions[$i] ?? '',
                'url' => $urls[$i] ?? '',
                'protected' => !empty($protected[$i]),
                'template' => $this->normalizeTemplateId((string)($templateIds[$i] ?? ''), $templates, $templateDefault),
            ];
        }

        $transitionErrors = $this->validateSlugTransitions($previousSites, $items);
        if (!empty($transitionErrors)) {
            return $this->index($request, $response, [
                'sites' => $items,
                'errors' => $transitionErrors,
            ]);
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

        $provision = $this->provision->provision($result['clean']);
        $templateReprovision = $this->reprovisionTemplateChanges($previousSites, $result['clean']);
        if (!empty($templateReprovision)) {
            $provision = $this->mergeProvisionResults($provision, $templateReprovision);
        }
        $removedFromList = $this->buildRemovedListResults($previousSites, $result['clean']);
        if (!empty($removedFromList)) {
            $provision = $this->mergeProvisionResults($provision, $removedFromList);
        }
        $hasProvisionErrors = $this->hasProvisionErrors($provision);
        if (!$hasProvisionErrors) {
            $deprovision = $this->provision->deprovisionRemoved($previousSites, $result['clean']);
            if (!empty($deprovision)) {
                $provision = $this->mergeProvisionResults($provision, $deprovision);
            }
        }
        $this->provision->forgetProvisionedForRemoved($previousSites, $result['clean']);
        $this->logger->info('admin.provision.results', ['items' => $provision]);
        $visibleProvision = $this->filterVisibleProvisionResults($provision);
        if (!empty($visibleProvision)) {
            $_SESSION['provision'] = $visibleProvision;
        } else {
            unset($_SESSION['provision']);
        }

        if ($hasProvisionErrors) {
            return $this->index($request, $response, [
                'sites' => $items,
                'errors' => [0 => ['url' => 'Alteracoes nao foram salvas porque houve falha no provisionamento.']],
            ]);
        }

        $this->siteService->save($result['clean']);
        $_SESSION['saved_message'] = $this->buildSaveSummary($previousSites, $result['clean']);
        $_SESSION['saved'] = true;

        return $response
            ->withHeader('Location', $this->path('/admin'))
            ->withStatus(302);
    }

    public function reprovision(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $existing = $this->siteService->all();
        $site = [
            'name' => $data['name'] ?? '',
            'url' => $data['url'] ?? '',
            'template' => $data['template'] ?? '',
        ];
        $templates = $this->loadTemplates();
        $templateDefault = $this->defaultTemplateId($templates);
        $site['template'] = $this->normalizeTemplateId((string)$site['template'], $templates, $templateDefault);

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
            ->withHeader('Location', $this->path('/admin'))
            ->withStatus(302);
    }

    public function remove(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $targetUrl = trim((string)($data['url'] ?? ''));
        $removeIndex = isset($data['remove_index']) ? (int)$data['remove_index'] : null;
        if (($targetUrl === '') && $removeIndex !== null && $removeIndex >= 0) {
            $postedUrls = $data['sites']['url'] ?? [];
            if (is_array($postedUrls) && isset($postedUrls[$removeIndex])) {
                $targetUrl = trim((string)$postedUrls[$removeIndex]);
            }
        }
        if ($targetUrl === '') {
            return $response
                ->withHeader('Location', $this->path('/admin'))
                ->withStatus(302);
        }

        $existing = $this->siteService->all();
        $target = null;
        foreach ($existing as $site) {
            if (!is_array($site)) {
                continue;
            }
            if (trim((string)($site['url'] ?? '')) === $targetUrl) {
                $target = $site;
                break;
            }
        }

        if ($target === null) {
            return $response
                ->withHeader('Location', $this->path('/admin'))
                ->withStatus(302);
        }
        if (!empty($target['protected'])) {
            return $this->index($request, $response, [
                'sites' => $existing,
                'errors' => [0 => ['url' => 'Este site esta protegido contra remocao.']],
            ]);
        }

        $next = [];
        foreach ($existing as $site) {
            if (!is_array($site)) {
                continue;
            }
            if (trim((string)($site['url'] ?? '')) === $targetUrl) {
                continue;
            }
            $next[] = $site;
        }

        $this->siteService->save($next);

        $provision = $this->buildRemovedListResults($existing, $next);
        $deprovision = $this->provision->deprovisionRemoved($existing, $next);
        if (!empty($deprovision)) {
            $provision = $this->mergeProvisionResults($provision, $deprovision);
        }
        $this->provision->forgetProvisionedForRemoved($existing, $next);
        $errorProvision = array_values(array_filter($provision, static function ($item): bool {
            return is_array($item) && (string)($item['status'] ?? '') === 'error';
        }));
        if (!empty($errorProvision)) {
            $_SESSION['provision'] = $errorProvision;
        } else {
            unset($_SESSION['provision']);
        }
        $slug = $this->slugFromUrl($targetUrl);
        $_SESSION['saved_message'] = sprintf('Site removido: %s.', $slug !== '' ? $slug : $targetUrl);
        $_SESSION['saved'] = true;

        return $response
            ->withHeader('Location', $this->path('/admin'))
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

    private function mergeProvisionResults(array $left, array $right): array
    {
        $bySlug = [];
        $extra = [];
        foreach (array_merge($left, $right) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = trim((string)($item['slug'] ?? ''));
            if ($slug === '') {
                $extra[] = $item;
                continue;
            }
            // Keep the latest status for each slug to avoid duplicated rows in the UI.
            $bySlug[$slug] = $item;
        }

        return array_merge($extra, array_values($bySlug));
    }

    private function filterVisibleProvisionResults(array $items): array
    {
        $visible = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = trim((string)($item['status'] ?? ''));
            if ($status === '') {
                continue;
            }
            if ($status !== 'skip') {
                $visible[] = $item;
                continue;
            }

            $message = mb_strtolower(trim((string)($item['message'] ?? '')));
            $hideSkip = $message === ''
                || str_contains($message, 'protegido')
                || str_contains($message, 'ja provisionado');
            if (!$hideSkip) {
                $visible[] = $item;
            }
        }
        return $visible;
    }

    private function buildRemovedListResults(array $previousSites, array $currentSites): array
    {
        $currentUrls = [];
        foreach ($currentSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string)($site['url'] ?? ''));
            if ($url !== '') {
                $currentUrls[$url] = true;
            }
        }

        $results = [];
        foreach ($previousSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string)($site['url'] ?? ''));
            if ($url === '' || isset($currentUrls[$url])) {
                continue;
            }
            $slug = $this->slugFromUrl($url);
            $results[] = [
                'slug' => $slug !== '' ? $slug : $url,
                'status' => 'deprovision',
                'message' => 'Removido da lista do painel',
            ];
        }

        return $results;
    }

    private function hasProvisionErrors(array $items): bool
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string)($item['status'] ?? '') === 'error') {
                return true;
            }
        }
        return false;
    }

    private function validateSlugTransitions(array $previousSites, array $items): array
    {
        $errors = [];
        $previousSlugs = [];
        foreach ($previousSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $slug = $this->slugFromUrlForProvisionHost((string)($site['url'] ?? ''));
            if ($slug !== '') {
                $previousSlugs[$slug] = true;
            }
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string)($item['url'] ?? ''));
            $name = trim((string)($item['name'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
            if ($url === '' && $name === '' && $description === '') {
                continue;
            }
            $slug = $this->slugFromUrlForProvisionHost($url);
            if ($slug === '' || isset($previousSlugs[$slug])) {
                continue;
            }
            $dir = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/') . '/' . $slug;
            if (is_dir($dir)) {
                continue;
            }
            if (!$this->canCreateLocalSiteDirectories()) {
                $errors[$index]['url'] = 'Nao e possivel criar/renomear slug neste ambiente. Mantenha a URL atual ou provisione via terminal (com sudo) antes de salvar.';
                continue;
            }
            if ($this->isApacheDynamicMode() && !$this->isDynamicApacheReady()) {
                $errors[$index]['url'] = 'Modo dinamico ativo no painel, mas o Apache ainda nao foi preparado para rotas dinamicas. Aplique o bloco unico no vhost e tente novamente.';
                continue;
            }
            if (!$this->isApacheDynamicMode() && !$this->canManageApacheAlias()) {
                $errors[$index]['url'] = 'Nao e possivel criar alias Apache neste ambiente. Provisione via terminal com sudo para publicar a rota antes de salvar.';
            }
        }

        return $errors;
    }

    private function canCreateLocalSiteDirectories(): bool
    {
        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        return is_dir($base) && is_writable($base);
    }

    private function canManageApacheAlias(): bool
    {
        $conf = (string)($this->config['admin']['apache_conf'] ?? '/etc/apache2/conf-available/site-paths.conf');
        if ($conf === '') {
            return false;
        }

        if (is_file($conf)) {
            return is_writable($conf);
        }

        $parent = dirname($conf);
        return is_dir($parent) && is_writable($parent);
    }

    private function isApacheDynamicMode(): bool
    {
        return !empty($this->config['admin']['apache_dynamic']);
    }

    private function isDynamicApacheReady(): bool
    {
        $vhost = trim((string)($this->config['admin']['apache_dynamic_vhost'] ?? '/etc/apache2/sites-available/000-default.conf'));
        $marker = trim((string)($this->config['admin']['apache_dynamic_marker'] ?? 'LABS_DYNAMIC_SITES'));
        if ($vhost === '' || !is_file($vhost) || !is_readable($vhost)) {
            return false;
        }

        $raw = file_get_contents($vhost);
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        if ($marker !== '' && str_contains($raw, $marker)) {
            return true;
        }

        return str_contains($raw, '/var/www/$1/public');
    }

    private function buildPermissionHelp(array $errors): ?array
    {
        if (!$this->hasEnvironmentPermissionError($errors)) {
            return null;
        }

        if ($this->hasDynamicApacheNotReadyError($errors)) {
            $vhost = (string)($this->config['admin']['apache_dynamic_vhost'] ?? '/etc/apache2/sites-available/000-default.conf');
            $marker = (string)($this->config['admin']['apache_dynamic_marker'] ?? 'LABS_DYNAMIC_SITES');
            return [
                'title' => 'Ative o bloco unico de rotas dinamicas no Apache (uma unica vez).',
                'commands' => [
                    sprintf('sudo nano %s', escapeshellarg($vhost)),
                    'sudo apache2ctl -t && sudo systemctl reload apache2',
                ],
                'fallback' => "# {$marker}\nAliasMatch ^/(?!assets/|admin(?:/|$)|about(?:/|$)|readme(?:/|$)|changelog(?:/|$)|login(?:/|$)|logout(?:/|$)|reset(?:/|$)|api(?:/|$)|health(?:/|$)|index\\.php(?:/|$))([A-Za-z0-9_-]+)(/.*)?$ /var/www/$1/public$2\n<Directory /var/www>\n    AllowOverride All\n    Require all granted\n</Directory>\n# /{$marker}",
            ];
        }

        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $defaultTemplate = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        $provisionScript = dirname(__DIR__, 2) . '/bin/provision-site';
        $templatesDir = rtrim((string)($this->config['admin']['templates_dir'] ?? '/var/www/labs/templates'), '/');

        return [
            'title' => 'Este ambiente nao permite publicar novos slugs automaticamente.',
            'commands' => [
                sprintf('sudo chgrp www-data %s', escapeshellarg($base)),
                sprintf('sudo chmod 2775 %s', escapeshellarg($base)),
            ],
            'fallback' => sprintf(
                'sudo SITE_TEMPLATE_DIR=%s %s <slug> "Nome do Site"',
                escapeshellarg($templatesDir . '/' . $defaultTemplate),
                escapeshellarg($provisionScript)
            ),
        ];
    }

    private function hasEnvironmentPermissionError(array $errors): bool
    {
        if ($errors === []) {
            return false;
        }

        $needles = [
            'nao e possivel criar/renomear slug neste ambiente',
            'nao e possivel criar alias apache neste ambiente',
            'modo dinamico ativo no painel',
        ];
        foreach ($errors as $rowErrors) {
            if (!is_array($rowErrors)) {
                continue;
            }
            foreach ($rowErrors as $message) {
                $text = mb_strtolower(trim((string)$message));
                if ($text === '') {
                    continue;
                }
                foreach ($needles as $needle) {
                    if (str_contains($text, $needle)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function hasDynamicApacheNotReadyError(array $errors): bool
    {
        foreach ($errors as $rowErrors) {
            if (!is_array($rowErrors)) {
                continue;
            }
            foreach ($rowErrors as $message) {
                $text = mb_strtolower(trim((string)$message));
                if ($text !== '' && str_contains($text, 'modo dinamico ativo no painel')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function slugFromUrlForProvisionHost(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $host = (string)($this->config['admin']['provision_host'] ?? '88.198.104.148');
        $urlHost = (string)($parts['host'] ?? '');
        if ($urlHost === '' || $urlHost !== $host) {
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

    private function path(string $path): string
    {
        return $this->resolveBasePath() . $path;
    }

    private function loadTemplates(): array
    {
        $dir = (string)($this->config['admin']['templates_dir'] ?? '/var/www/labs/templates');
        $items = [];
        if (!is_dir($dir)) {
            return $items;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return $items;
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
            $meta = $this->loadTemplateMeta($path);
            $defaultLabel = ucwords(str_replace(['-', '_', '.'], ' ', $entry));
            $items[] = [
                'id' => $entry,
                'label' => (string)($meta['label'] ?? $defaultLabel),
                'description' => (string)($meta['description'] ?? 'Template base para provisionamento de sites.'),
                'preview_url' => (string)($meta['preview_url'] ?? ''),
                'path' => $path,
            ];
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['id'], (string)$b['id']));
        return $items;
    }

    private function defaultTemplateId(array $templates): string
    {
        $preferred = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        foreach ($templates as $tpl) {
            if (($tpl['id'] ?? '') === $preferred) {
                return $preferred;
            }
        }
        return $templates[0]['id'] ?? $preferred;
    }

    private function normalizeTemplateId(string $requested, array $templates, string $fallback): string
    {
        foreach ($templates as $tpl) {
            if (($tpl['id'] ?? '') === $requested) {
                return $requested;
            }
        }
        return $fallback;
    }

    private function reprovisionTemplateChanges(array $previousSites, array $currentSites): array
    {
        $previousByUrl = [];
        foreach ($previousSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string)($site['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $previousByUrl[$url] = $site;
        }

        $results = [];
        foreach ($currentSites as $site) {
            if (!is_array($site)) {
                continue;
            }

            $url = trim((string)($site['url'] ?? ''));
            if ($url === '' || !isset($previousByUrl[$url])) {
                continue;
            }

            $previous = $previousByUrl[$url];
            $before = trim((string)($previous['template'] ?? ''));
            $after = trim((string)($site['template'] ?? ''));
            if ($after === '' || $before === $after) {
                continue;
            }

            if (!empty($previous['protected'])) {
                $results[] = [
                    'slug' => $this->slugFromUrl($url),
                    'status' => 'skip',
                    'message' => 'Protegido: template alterado sem reprovisionar',
                ];
                continue;
            }

            $results = array_merge($results, $this->provision->reprovisionOne($site, true));
        }

        return $results;
    }

    private function slugFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url !== '' ? $url : 'site';
        }

        $path = trim((string)($parts['path'] ?? ''), '/');
        if ($path === '') {
            return $url !== '' ? $url : 'site';
        }

        $segments = explode('/', $path);
        return $segments[0] ?? ($url !== '' ? $url : 'site');
    }

    private function loadTemplateMeta(string $templatePath): array
    {
        $metaFile = rtrim($templatePath, '/') . '/template.json';
        if (!is_file($metaFile) || !is_readable($metaFile)) {
            return [];
        }

        $raw = file_get_contents($metaFile);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    private function templateLabelMap(array $templates): array
    {
        $map = [];
        foreach ($templates as $template) {
            $id = (string)($template['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $map[$id] = (string)($template['label'] ?? $id);
        }
        return $map;
    }

    private function resolveTemplateUsageInfo(array $site, array $templateLabels): array
    {
        $requestedId = trim((string)($site['template'] ?? ''));
        $slug = $this->slugFromUrl((string)($site['url'] ?? ''));
        $marker = $this->readTemplateMarker($slug);

        if ($marker !== '' && $marker !== 'template-v4') {
            return [
                'template_usage_label' => 'Legado (manual)',
                'template_usage_hint' => $marker,
                'template_usage_kind' => 'legacy',
            ];
        }

        if ($marker === '' && !empty($site['protected'])) {
            return [
                'template_usage_label' => 'Legado (manual)',
                'template_usage_hint' => 'sem marcador',
                'template_usage_kind' => 'legacy',
            ];
        }

        if ($marker === '' && $slug === '') {
            return [
                'template_usage_label' => 'Novo (ao salvar)',
                'template_usage_hint' => '-',
                'template_usage_kind' => 'pending',
            ];
        }

        $defaultId = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        $effectiveId = $requestedId !== '' ? $requestedId : $defaultId;
        $label = $templateLabels[$effectiveId] ?? $effectiveId;
        if ($label === '') {
            $label = 'Template';
        }

        return [
            'template_usage_label' => $label,
            'template_usage_hint' => $effectiveId,
            'template_usage_kind' => 'managed',
        ];
    }

    private function buildSaveSummary(array $previousSites, array $currentSites): string
    {
        $previousByUrl = [];
        foreach ($previousSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string)($site['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $previousByUrl[$url] = $site;
        }

        $currentByUrl = [];
        foreach ($currentSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string)($site['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $currentByUrl[$url] = $site;
        }

        $created = 0;
        $edited = 0;
        $removed = 0;

        foreach ($currentByUrl as $url => $site) {
            if (!isset($previousByUrl[$url])) {
                $created += 1;
                continue;
            }
            if ($this->siteRowSignature($previousByUrl[$url]) !== $this->siteRowSignature($site)) {
                $edited += 1;
            }
        }

        foreach ($previousByUrl as $url => $site) {
            if (!isset($currentByUrl[$url])) {
                $removed += 1;
            }
        }

        return sprintf(
            'Alteracoes salvas. Criados: %d, editados: %d, removidos: %d.',
            $created,
            $edited,
            $removed
        );
    }

    private function siteRowSignature(array $site): string
    {
        return json_encode([
            'name' => trim((string)($site['name'] ?? '')),
            'description' => trim((string)($site['description'] ?? '')),
            'url' => trim((string)($site['url'] ?? '')),
            'protected' => !empty($site['protected']),
            'template' => trim((string)($site['template'] ?? '')),
        ], JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function readTemplateMarker(string $slug): string
    {
        if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
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
}
