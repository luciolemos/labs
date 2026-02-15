<?php

namespace App\Services;

final class AuthService
{
    public function __construct(private array $config)
    {
    }

    public function attempt(string $user, string $pass): bool
    {
        $expectedUser = $this->config['admin']['user'] ?? 'admin';
        $expectedPass = $this->config['admin']['pass'] ?? 'changeme';
        $expectedHash = $this->config['admin']['pass_hash'] ?? '';

        if (!hash_equals($expectedUser, $user)) {
            return false;
        }

        if (is_string($expectedHash) && $expectedHash !== '') {
            return password_verify($pass, $expectedHash);
        }

        return hash_equals($expectedPass, $pass);
    }

    public function login(string $user): void
    {
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_at'] = time();
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user'], $_SESSION['auth_at']);
    }

    public function check(): bool
    {
        if (empty($_SESSION['auth_user'])) {
            return false;
        }

        $minutes = (int)($this->config['admin']['session_minutes'] ?? 60);
        $ttl = max(1, $minutes) * 60;
        $last = (int)($_SESSION['auth_at'] ?? 0);

        if ($last === 0 || (time() - $last) > $ttl) {
            $this->logout();
            return false;
        }

        $_SESSION['auth_at'] = time();
        return true;
    }
}
