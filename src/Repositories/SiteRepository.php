<?php

namespace App\Repositories;

final class SiteRepository
{
    public function __construct(
        private string $path,
        private array $defaults = [],
    ) {
    }

    public function all(): array
    {
        if (!is_file($this->path)) {
            return $this->defaults;
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return $this->defaults;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->defaults;
        }

        return $data;
    }

    public function save(array $sites): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $payload = json_encode($sites, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($this->path, $payload . PHP_EOL, LOCK_EX);
    }
}
