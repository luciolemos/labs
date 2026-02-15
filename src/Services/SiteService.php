<?php

namespace App\Services;

use App\Repositories\SiteRepository;

final class SiteService
{
    public function __construct(private SiteRepository $repository)
    {
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function validate(array $payload): array
    {
        $errors = [];

        $name = trim((string)($payload['name'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $url = trim((string)($payload['url'] ?? ''));
        $protected = !empty($payload['protected']);

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

            if ($name === '' && $description === '' && $url === '') {
                continue;
            }

            $result = $this->validate([
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'protected' => $protected,
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
}
