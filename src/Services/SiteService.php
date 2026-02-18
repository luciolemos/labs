<?php

namespace App\Services;

use App\Repositories\SiteRepository;

final class SiteService
{
    public function __construct(
        private SiteRepository $repository,
        private array $config = [],
    )
    {
    }

    public function all(): array
    {
        $sites = $this->repository->all();
        return $this->reconcileWithProvisioned($sites);
    }

    public function validate(array $payload): array
    {
        $errors = [];

        $name = trim((string)($payload['name'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $url = trim((string)($payload['url'] ?? ''));
        $protected = !empty($payload['protected']);
        $template = trim((string)($payload['template'] ?? 'tech-v4-blue'));
        if ($template === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $template)) {
            $template = 'tech-v4-blue';
        }

        if ($name === '') {
            $errors['name'] = 'Nome eh obrigatorio.';
        } elseif (mb_strlen($name) < 3) {
            $errors['name'] = 'Nome precisa ter ao menos 3 caracteres.';
        }

        if ($url === '') {
            $errors['url'] = 'URL eh obrigatoria.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors['url'] = 'URL invalida.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'clean' => [
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'protected' => $protected,
                'template' => $template,
            ],
        ];
    }

    public function validateList(array $items): array
    {
        $errors = [];
        $clean = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string)($item['name'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            $protected = !empty($item['protected']);
            $template = trim((string)($item['template'] ?? 'tech-v4-blue'));

            if ($name === '' && $description === '' && $url === '') {
                continue;
            }

            $result = $this->validate([
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'protected' => $protected,
                'template' => $template,
            ]);
            if (!$result['valid']) {
                $errors[$index] = $result['errors'];
            } else {
                $clean[] = $result['clean'];
            }
        }

        if ($clean === [] && $errors === []) {
            $errors[0] = [
                'name' => 'Informe pelo menos um site.',
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'clean' => $clean,
        ];
    }

    public function save(array $sites): void
    {
        $this->repository->save($sites);
    }

    private function reconcileWithProvisioned(array $sites): array
    {
        $host = (string)($this->config['admin']['provision_host'] ?? '88.198.104.148');
        $base = rtrim((string)($this->config['admin']['provision_base'] ?? '/var/www'), '/');
        $defaultTemplate = (string)($this->config['admin']['template_default'] ?? 'tech-v4-blue');
        $provisionedPath = rtrim((string)($this->config['paths']['storage'] ?? ''), '/') . '/data/provisioned.json';
        $provisioned = $this->loadProvisioned($provisionedPath);

        $bySlug = [];
        $clean = [];
        foreach ($sites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $name = trim((string)($site['name'] ?? ''));
            $description = trim((string)($site['description'] ?? ''));
            $url = trim((string)($site['url'] ?? ''));
            if ($name === '' && $description === '' && $url === '') {
                continue;
            }

            $entry = [
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'protected' => !empty($site['protected']),
                'template' => $this->normalizeTemplateId((string)($site['template'] ?? $defaultTemplate), $defaultTemplate),
            ];
            $clean[] = $entry;

            $slug = $this->slugFromUrl($url, $host);
            if ($slug !== '') {
                $bySlug[$slug] = true;
            }
        }

        $changed = false;
        foreach ($provisioned as $slug => $timestamp) {
            if (!is_string($slug) || !$this->isValidSlug($slug)) {
                continue;
            }
            if (isset($bySlug[$slug])) {
                continue;
            }

            $siteDir = $base . '/' . $slug;
            if (!is_dir($siteDir)) {
                continue;
            }

            $detectedTemplate = $this->detectTemplateId($siteDir, $defaultTemplate);
            $clean[] = [
                'name' => $this->humanizeSlug($slug),
                'description' => 'Site importado automaticamente do provisionamento local.',
                'url' => sprintf('http://%s/%s/', $host, $slug),
                'protected' => false,
                'template' => $detectedTemplate,
            ];
            $bySlug[$slug] = true;
            $changed = true;
        }

        if ($changed) {
            $this->repository->save($clean);
        }

        return $clean;
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
        return is_array($data) ? $data : [];
    }

    private function normalizeTemplateId(string $value, string $fallback): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $trimmed)) {
            return $fallback;
        }
        return $trimmed;
    }

    private function detectTemplateId(string $siteDir, string $fallback): string
    {
        $metaFile = rtrim($siteDir, '/') . '/template.json';
        if (!is_file($metaFile) || !is_readable($metaFile)) {
            return $fallback;
        }

        $raw = file_get_contents($metaFile);
        if ($raw === false) {
            return $fallback;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $fallback;
        }

        $id = trim((string)($data['id'] ?? ''));
        if ($id === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $id)) {
            return $fallback;
        }

        return $id;
    }

    private function slugFromUrl(string $url, string $host): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $urlHost = trim((string)($parts['host'] ?? ''));
        if ($urlHost !== '' && $urlHost !== $host) {
            return '';
        }

        $path = trim((string)($parts['path'] ?? ''), '/');
        if ($path === '') {
            return '';
        }
        $slug = explode('/', $path)[0] ?? '';

        return $this->isValidSlug($slug) ? $slug : '';
    }

    private function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $slug) === 1;
    }

    private function humanizeSlug(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
