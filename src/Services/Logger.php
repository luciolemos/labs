<?php

namespace App\Services;

final class Logger
{
    public function __construct(private string $path)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $entry = [
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
