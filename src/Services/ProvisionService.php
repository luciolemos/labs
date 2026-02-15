<?php

namespace App\Services;

final class ProvisionService
{
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

        $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';
        $provisioned = $this->loadProvisioned($provisionedPath);

        foreach ($sites as $site) {
            $url = (string)($site['url'] ?? '');
            $name = (string)($site['name'] ?? '');
            $protected = !empty($site['protected']);
            $slug = $this->slugFromUrl($url, $host);
            if ($slug === '') {
                continue;
            }

            $dirExists = is_dir(rtrim($base, '/') . '/' . $slug);
            $aliasExists = $this->aliasExists($conf, $slug);
            $needsReprovision = !$dirExists || !$aliasExists;

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

            $cmd = sprintf('sudo -n %s %s %s 2>&1',
                escapeshellarg(dirname(__DIR__, 2) . '/bin/provision-site'),
                escapeshellarg($slug),
                escapeshellarg($name)
            );

            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code === 0) {
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
        $slug = $this->slugFromUrl($url, $host);
        if ($slug === '') {
            return [[
                'slug' => $url ?: 'site',
                'status' => 'error',
                'message' => 'URL invalida para reprovisionar',
            ]];
        }

        $cmd = sprintf('sudo -n %s %s %s %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2) . '/bin/provision-site'),
            escapeshellarg($slug),
            escapeshellarg($name),
            $force ? '--force' : ''
        );

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        if ($code === 0) {
            $provisionedPath = $this->config['paths']['storage'] . '/data/provisioned.json';
            $provisioned = $this->loadProvisioned($provisionedPath);
            $provisioned[$slug] = time();
            $this->saveProvisioned($provisionedPath, $provisioned);
            return [[
                'slug' => $slug,
                'status' => 'reprovision',
                'message' => $force ? 'Reprovisionado (template atualizado)' : 'Reprovisionado',
            ]];
        }

        return [[
            'slug' => $slug,
            'status' => 'error',
            'message' => trim(implode("\n", $output)) ?: 'Falha ao reprovisionar',
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

        $contents = file_get_contents($confPath);
        if ($contents === false) {
            return false;
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
}
