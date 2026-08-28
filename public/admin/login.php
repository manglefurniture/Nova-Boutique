<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

if (Security::isAdmin()) {
    header('Location: /admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Security::verifyCsrf($_POST['csrf'] ?? null);
        Security::throttleAdminLogin();
        if (Security::adminLogin((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: /admin/');
            exit;
        }
        $error = 'Credenciales incorrectas o administrador no configurado.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Acceso · Nova Admin</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="login"><form class="login-box" method="post"><span class="eyebrow">Hache Interactive</span><h1>Nova Admin</h1><?php if ($error): ?><div class="notice"><?= Security::e($error) ?></div><?php endif; ?><input type="hidden" name="csrf" value="<?= Security::e(Security::csrfToken()) ?>"><div class="field"><label>Correo</label><input type="email" name="email" required autocomplete="username"></div><br><div class="field"><label>Contraseña</label><input type="password" name="password" required autocomplete="current-password"></div><br><button class="btn" type="submit">Entrar</button></form></main></body></html>
