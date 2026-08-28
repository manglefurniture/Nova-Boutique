<?php
declare(strict_types=1);

final class Security
{
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
}
