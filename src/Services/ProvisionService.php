<?php

namespace App\Services;

final class ProvisionService
{
    private string $lastLocalError = '';

    public function __construct(private array $config)
    {
    }

    public function provision(array $sites): array
    {
        if (empty($this->config['admin']['provision'])) {
            return [];
        }

        $results = [];
        $host = $this->config['admin']['provision_host'] ?? '88.198.104.148';
        $base = $this->config['admin']['provision_base'] ?? '/var/www';
        $conf = $this->config['admin']['apache_conf'] ?? '/etc/apache2/conf-available/site-paths.conf';
        $dynamicMode = !empty($this->config['admin']['apache_dynamic']);

        $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';
        $provisioned = $this->loadProvisioned($provisionedPath);

        foreach ($sites as $site) {
            $url = (string)($site['url'] ?? '');
            $name = (string)($site['name'] ?? '');
            $protected = !empty($site['protected']);
            $templateDir = $this->resolveTemplateDir((string)($site['template'] ?? ''));
            $slug = $this->slugFromUrl($url, $host);
            if ($slug === '') {
                continue;
            }

            $siteDir = rtrim($base, '/') . '/' . $slug;
            $dirExists = is_dir($siteDir);
            $aliasExists = $dynamicMode ? true : $this->aliasExists($conf, $slug);
            $needsReprovision = !$dirExists || (!$dynamicMode && !$aliasExists);

            if ($protected && $dirExists) {
                $results[] = [
                    'slug' => $slug,
                    'status' => 'skip',
                    'message' => 'Protegido: nao reprovisionado',
                ];
                continue;
            }

            if (isset($provisioned[$slug]) && !$needsReprovision) {
                $results[] = ['slug' => $slug, 'status' => 'skip', 'message' => 'Ja provisionado'];
                continue;
            }

            // For existing sites, only local apply (never shell provisioning).
            if ($dirExists) {
                if ($this->applyTemplateLocally($slug, $name, $templateDir, true)) {
                    $provisioned[$slug] = time();
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'reprovision',
                        'message' => 'Reprovisionado',
                    ];
                } else {
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'error',
                        'message' => $this->localFailureMessage(),
                    ];
                }
                continue;
            }

            if ($dynamicMode) {
                if ($this->applyTemplateLocally($slug, $name, $templateDir, true)) {
                    $this->writeSiteEnv($slug, $name);
                    $provisioned[$slug] = time();
                    $results[] = [
                        'slug' => $slug,
                        'status' => $needsReprovision ? 'reprovision' : 'ok',
                        'message' => $needsReprovision ? 'Reprovisionado' : 'Provisionado',
                    ];
                } else {
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'error',
                        'message' => $this->localFailureMessage(),
                    ];
                }
                continue;
            }

            $cmd = sprintf('env SITE_TEMPLATE_DIR=%s %s %s %s 2>&1',
                escapeshellarg($templateDir),
                escapeshellarg(dirname(__DIR__, 2) . '/bin/provision-site'),
                escapeshellarg($slug),
                escapeshellarg($name)
            );

            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0 && $this->shouldFallbackLocal($output)) {
                $localOk = $this->applyTemplateLocally($slug, $name, $templateDir, true);
                if ($localOk) {
                    $code = 0;
                }
            }
            if ($code === 0 && !$this->isTemplateApplied($slug, $templateDir)) {
                $localOk = $this->applyTemplateLocally($slug, $name, $templateDir, true);
                if (!$localOk) {
                    $code = 1;
                    $output[] = 'Template aplicado diverge do solicitado e fallback local falhou.';
                }
            }

            if ($code === 0) {
                $this->writeSiteEnv($slug, $name);
                $provisioned[$slug] = time();
                $results[] = [
                    'slug' => $slug,
                    'status' => $needsReprovision ? 'reprovision' : 'ok',
                    'message' => $needsReprovision ? 'Reprovisionado' : 'Provisionado',
                ];
            } else {
                $results[] = [
                    'slug' => $slug,
                    'status' => 'error',
                    'message' => trim(implode("\n", $output)) ?: 'Falha ao provisionar',
                ];
            }
        }

        $this->saveProvisioned($provisionedPath, $provisioned);

        return $results;
    }

    public function reprovisionOne(array $site, bool $force = false): array
    {
        if (empty($this->config['admin']['provision'])) {
            return [];
        }

        $host = $this->config['admin']['provision_host'] ?? '88.198.104.148';
        $url = (string)($site['url'] ?? '');
        $name = (string)($site['name'] ?? '');
        $templateDir = $this->resolveTemplateDir((string)($site['template'] ?? ''));
        $slug = $this->slugFromUrl($url, $host);
        if ($slug === '') {
            return [[
                'slug' => $url ?: 'site',
                'status' => 'error',
                'message' => 'URL invalida para reprovisionar',
            ]];
        }

        if ($this->applyTemplateLocally($slug, $name, $templateDir, $force)) {
            $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';
            $provisioned = $this->loadProvisioned($provisionedPath);
            $provisioned[$slug] = time();
            $this->saveProvisioned($provisionedPath, $provisioned);
            return [[
                'slug' => $slug,
                'status' => 'reprovision',
                'message' => 'Reprovisionado',
            ]];
        }

        return [[
            'slug' => $slug,
            'status' => 'error',
            'message' => $this->localFailureMessage(),
        ]];
    }

    public function deprovisionRemoved(array $previousSites, array $currentSites): array
    {
        if (empty($this->config['admin']['provision']) || empty($this->config['admin']['deprovision'])) {
            return [];
        }

        $host = $this->config['admin']['provision_host'] ?? '88.198.104.148';
        $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';
        $removeDir = !empty($this->config['admin']['deprovision_remove_dir']);
        $dynamicMode = !empty($this->config['admin']['apache_dynamic']);

        $currentSlugs = [];
        foreach ($currentSites as $site) {
            $slug = $this->slugFromUrl((string)($site['url'] ?? ''), $host);
            if ($slug !== '') {
                $currentSlugs[$slug] = true;
            }
        }

        $provisioned = $this->loadProvisioned($provisionedPath);
        $results = [];

        foreach ($previousSites as $site) {
            $slug = $this->slugFromUrl((string)($site['url'] ?? ''), $host);
            if ($slug === '' || isset($currentSlugs[$slug])) {
                continue;
            }

            if (!empty($site['protected'])) {
                $results[] = [
                    'slug' => $slug,
                    'status' => 'skip',
                    'message' => 'Protegido: alias nao removido',
                ];
                continue;
            }

            if ($dynamicMode) {
                $local = $this->deprovisionLocally($slug, $removeDir);
                if ($local['ok']) {
                    unset($provisioned[$slug]);
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'deprovision',
                        'message' => $local['message'],
                    ];
                } else {
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'error',
                        'message' => $local['message'],
                    ];
                }
                continue;
            }

            $cmdParts = [
                'sudo -n',
                escapeshellarg(dirname(__DIR__, 2) . '/bin/deprovision-site'),
                escapeshellarg($slug),
            ];
            if ($removeDir) {
                $cmdParts[] = '--remove-dir';
            }
            $cmd = implode(' ', $cmdParts) . ' 2>&1';

            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code === 0) {
                unset($provisioned[$slug]);
                $results[] = [
                    'slug' => $slug,
                    'status' => 'deprovision',
                    'message' => $removeDir ? 'Alias removido e pasta apagada' : 'Alias removido do Apache',
                ];
                continue;
            }

            if ($this->shouldFallbackLocal($output)) {
                $local = $this->deprovisionLocally($slug, $removeDir);
                if ($local['ok']) {
                    unset($provisioned[$slug]);
                    $results[] = [
                        'slug' => $slug,
                        'status' => 'deprovision',
                        'message' => $local['message'],
                    ];
                    continue;
                }
            }

            $results[] = [
                'slug' => $slug,
                'status' => 'error',
                'message' => trim(implode("\n", $output)) ?: 'Falha ao desprovisionar',
            ];
        }

        if (!empty($results)) {
            $this->saveProvisioned($provisionedPath, $provisioned);
        }

        return $results;
    }

    public function forgetProvisionedForRemoved(array $previousSites, array $currentSites): void
    {
        $host = $this->config['admin']['provision_host'] ?? '88.198.104.148';
        $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';

        $currentSlugs = [];
        foreach ($currentSites as $site) {
            $slug = $this->slugFromUrl((string)($site['url'] ?? ''), $host);
            if ($slug !== '') {
                $currentSlugs[$slug] = true;
            }
        }

        $provisioned = $this->loadProvisioned($provisionedPath);
        $changed = false;

        foreach ($previousSites as $site) {
            $slug = $this->slugFromUrl((string)($site['url'] ?? ''), $host);
            if ($slug === '' || isset($currentSlugs[$slug])) {
                continue;
            }
            if (isset($provisioned[$slug])) {
                unset($provisioned[$slug]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->saveProvisioned($provisionedPath, $provisioned);
        }
    }

    private function slugFromUrl(string $url, string $host): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        if (!empty($parts['host']) && $parts['host'] !== $host) {
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

    private function aliasExists(string $confPath, string $slug): bool
    {
        if (!is_file($confPath)) {
            return false;
        }
        if (!is_readable($confPath)) {
            // In restricted web-server contexts this file may be unreadable.
            // Treat as "unknown" and avoid forcing reprovision on every save.
            return true;
        }

        $contents = @file_get_contents($confPath);
        if ($contents === false) {
            return true;
        }

        return preg_match('/^Alias\\s+\\/' . preg_quote($slug, '/') . '\\s+/m', $contents) === 1;
    }

    private function loadProvisioned(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    private function saveProvisioned(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($path, $payload . PHP_EOL, LOCK_EX);
    }

    private function resolveTemplateDir(string $templateId): string
    {
        $templatesBase = rtrim((string)($this->config['admin']['templates_dir'] ?? '/var/www/labs/templates'), '/');
        $fallbackId = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        $requested = trim($templateId);

        if ($requested !== '' && preg_match('/^[a-zA-Z0-9_.-]+$/', $requested)) {
            $requestedDir = $templatesBase . '/' . $requested;
            if (is_dir($requestedDir)) {
                return $requestedDir;
            }
        }

        $fallbackDir = $templatesBase . '/' . $fallbackId;
        if (is_dir($fallbackDir)) {
            return $fallbackDir;
        }

        return '/var/www/labs/templates/tech-v4-blue';
    }

    private function shouldFallbackLocal(array $output): bool
    {
        $message = mb_strtolower(trim(implode("\n", $output)));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'apachectl not found')
            || str_contains($message, 'mod_alias not enabled')
            || str_contains($message, 'a password is required')
            || str_contains($message, 'sudo:')
            || str_contains($message, 'permission denied');
    }

    private function applyTemplateLocally(string $slug, string $name, string $templateDir, bool $force): bool
    {
        $this->lastLocalError = '';

        if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            return $this->failLocal('Slug invalido para reprovisionamento local');
        }
        if (!is_dir($templateDir)) {
            return $this->failLocal('Template nao encontrado no filesystem');
        }

        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $dir = $base . '/' . $slug;
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return $this->failLocal('Diretorio do site nao encontrado e nao foi possivel criar');
            }
        }
        if (!is_writable($dir) && !@chmod($dir, 0775)) {
            return $this->failLocal('Diretorio do site sem permissao de escrita');
        }

        // Overlay copy is safer in web-server contexts than hard delete+copy.
        // It avoids permission edge-cases and preserves runtime artifacts.
        $title = trim($name) !== '' ? trim($name) : $slug;

        if (!$this->copyTemplateContents($templateDir, $dir)) {
            if ($this->lastLocalError !== '') {
                return false;
            }
            return $this->failLocal('Falha ao copiar arquivos do template');
        }

        if (!$this->writeSiteEnvSafe($slug, $title)) {
            return $this->failLocal('Falha ao escrever .env do site');
        }

        @mkdir($dir . '/storage/cache/twig', 0775, true);
        @mkdir($dir . '/storage/logs', 0775, true);
        @mkdir($dir . '/storage/data', 0775, true);

        if (!$this->installDependenciesLocally($dir)) {
            return $this->failLocal('Falha ao garantir dependencias do projeto');
        }

        return true;
    }

    private function deleteDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child) && !is_link($child)) {
                $this->deleteDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }

    private function installDependenciesLocally(string $dir): bool
    {
        if (!is_file($dir . '/composer.json')) {
            return true;
        }

        $composerBin = '';
        foreach (['composer', '/usr/bin/composer', '/usr/local/bin/composer'] as $candidate) {
            $check = [];
            $checkCode = 0;
            exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null', $check, $checkCode);
            if ($checkCode === 0 && !empty($check)) {
                $composerBin = $check[0];
                break;
            }
            if (is_file($candidate) && is_executable($candidate)) {
                $composerBin = $candidate;
                break;
            }
        }

        if ($composerBin === '') {
            // In web-server contexts composer may be absent in PATH.
            // Do not fail reprovision just because of this.
            return true;
        }

        $cmd = sprintf(
            'cd %s && %s install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1',
            escapeshellarg($dir),
            escapeshellarg($composerBin)
        );
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return $code === 0 || is_file($dir . '/vendor/autoload.php');
    }

    private function isTemplateApplied(string $slug, string $templateDir): bool
    {
        if ($slug === '' || $templateDir === '') {
            return false;
        }

        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $siteCss = $base . '/' . $slug . '/public/assets/css/landing.css';
        $tplCss = rtrim($templateDir, '/') . '/public/assets/css/landing.css';

        if (!is_file($siteCss) || !is_file($tplCss)) {
            return false;
        }

        $siteHash = @md5_file($siteCss);
        $tplHash = @md5_file($tplCss);
        if (!is_string($siteHash) || !is_string($tplHash) || $siteHash === '' || $tplHash === '') {
            return false;
        }

        return hash_equals($tplHash, $siteHash);
    }

    private function writeSiteEnv(string $slug, string $name): void
    {
        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $dir = $base . '/' . $slug;
        $title = trim($name) !== '' ? trim($name) : $slug;

        $env = implode(PHP_EOL, [
            'APP_NAME="' . addslashes($title) . '"',
            'APP_MARK="A"',
            'APP_BADGE="PHP 8.3+"',
            'APP_PAGE_TITLE="' . addslashes($title) . ' — Landing moderna em PHP"',
            'APP_BASE="/' . $slug . '"',
            'APP_ENV="production"',
            'GITHUB_URL="#"',
            'X_URL="https://x.com"',
            'INSTAGRAM_URL="https://instagram.com"',
            'WHATSAPP_URL="https://wa.me/5584998087340"',
            'ENV="production"',
            '',
        ]);
        @file_put_contents($dir . '/.env', $env, LOCK_EX);
    }

    private function writeSiteEnvSafe(string $slug, string $name): bool
    {
        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $dir = $base . '/' . $slug;
        if (!is_dir($dir)) {
            return false;
        }

        $this->writeSiteEnv($slug, $name);
        $envFile = $dir . '/.env';
        $legacy = @file_get_contents($envFile);
        if (is_string($legacy) && str_contains($legacy, 'APP_BASE="/' . $slug . '"')) {
            return true;
        }

        return false;
    }

    private function copyTemplateContents(string $sourceDir, string $targetDir, string $relative = ''): bool
    {
        $entries = scandir($sourceDir);
        if ($entries === false) {
            return $this->failLocal('Falha ao listar template em ' . ($relative !== '' ? $relative : '/'));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'vendor') {
                continue;
            }
            if ($relative === '' && $entry === '.env') {
                // Keep site-specific runtime env and rewrite it explicitly after copy.
                continue;
            }

            $src = $sourceDir . '/' . $entry;
            $dst = $targetDir . '/' . $entry;
            $rel = $relative === '' ? $entry : $relative . '/' . $entry;

            if (is_dir($src) && !is_link($src)) {
                if (!is_dir($dst) && !@mkdir($dst, 0775, true) && !is_dir($dst)) {
                    return $this->failLocal('Falha ao criar diretorio: ' . $rel);
                }
                if (is_dir($dst) && !is_writable($dst) && !@chmod($dst, 0775)) {
                    return $this->failLocal('Sem permissao de escrita em diretorio: ' . $rel);
                }
                if (!$this->copyTemplateContents($src, $dst, $rel)) {
                    return false;
                }
                continue;
            }

            if (is_file($dst) && !is_writable($dst)) {
                @chmod($dst, 0664);
            }
            if (is_file($dst) && !is_writable($dst)) {
                // In shared hosting contexts, destination file may belong to another user/group.
                // If directory is writable, replace the file to recover from ownership mismatch.
                @unlink($dst);
            }
            if (is_file($dst) && !is_writable($dst)) {
                return $this->failLocal('Sem permissao para substituir arquivo: ' . $rel);
            }
            if (!@copy($src, $dst)) {
                $last = error_get_last();
                $detail = is_array($last) && isset($last['message']) ? ' (' . $last['message'] . ')' : '';
                return $this->failLocal('Falha ao copiar arquivo: ' . $rel . $detail);
            }
            @chmod($dst, 0664);
        }

        return true;
    }

    private function failLocal(string $reason): bool
    {
        $this->lastLocalError = trim($reason);
        return false;
    }

    private function localFailureMessage(): string
    {
        return $this->lastLocalError !== ''
            ? 'Falha no reprovisionamento local: ' . $this->lastLocalError
            : 'Falha no reprovisionamento local deste site';
    }

    private function deprovisionLocally(string $slug, bool $removeDir): array
    {
        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $dir = $base . '/' . $slug;

        if ($removeDir && is_dir($dir)) {
            $this->deleteDirectory($dir);
            if (is_dir($dir)) {
                return [
                    'ok' => false,
                    'message' => 'Falha ao remover pasta local do site',
                ];
            }

            if (!empty($this->config['admin']['apache_dynamic'])) {
                return [
                    'ok' => true,
                    'message' => 'Pasta apagada (modo dinamico)',
                ];
            }

            return [
                'ok' => true,
                'message' => 'Pasta removida localmente (alias Apache nao removido neste ambiente)',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Removido localmente da lista de provisionados',
        ];
    }
}
