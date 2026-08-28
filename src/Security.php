<?php
declare(strict_types=1);

final class Security
{
    private const LOGIN_WINDOW_SECONDS = 900;
    private const LOGIN_MAX_ATTEMPTS = 8;

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): void
    {
        $expected = (string) ($_SESSION['csrf'] ?? '');
        if ($expected === '' || $token === null || !hash_equals($expected, $token)) {
            throw new RuntimeException('La sesión expiró o la solicitud no es válida.');
        }
    }

    public static function throttleAdminLogin(): void
    {
        $path = self::loginThrottlePath();
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('No se pudo validar temporalmente el acceso.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo validar temporalmente el acceso.');
            }
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $attempts = is_array($decoded) ? array_map('intval', $decoded) : [];
            $cutoff = time() - self::LOGIN_WINDOW_SECONDS;
            $attempts = array_values(array_filter($attempts, static fn(int $ts): bool => $ts >= $cutoff));
            if (count($attempts) >= self::LOGIN_MAX_ATTEMPTS) {
                throw new RuntimeException('Demasiados intentos de acceso. Intenta de nuevo más tarde.');
            }
            $attempts[] = time();
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attempts, JSON_THROW_ON_ERROR));
            fflush($handle);
            @chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function clearAdminLoginThrottle(): void
    {
        @unlink(self::loginThrottlePath());
    }

    public static function adminLogin(string $email, string $password): bool
    {
        $expectedEmail = mb_strtolower(trim((string) env('ADMIN_EMAIL')));
        $hash = (string) env('ADMIN_PASSWORD_HASH');
        if ($expectedEmail === '' || $hash === '') {
            return false;
        }
        if (!hash_equals($expectedEmail, mb_strtolower(trim($email)))) {
            return false;
        }
        if (!password_verify($password, $hash)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['nova_admin'] = true;
        $_SESSION['nova_admin_email'] = $expectedEmail;
        self::clearAdminLoginThrottle();
        return true;
    }

    public static function isAdmin(): bool
    {
        return !empty($_SESSION['nova_admin']);
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION['nova_admin'], $_SESSION['nova_admin_email']);
        session_regenerate_id(true);
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function loginThrottlePath(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'nova-admin-login-'
            . hash('sha256', $ip)
            . '.json';
    }
}
