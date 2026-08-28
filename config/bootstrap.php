<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';

if (is_file($envFile) && is_readable($envFile)) {
    $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($values)) {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

date_default_timezone_set(env('APP_TIMEZONE', 'America/Cancun') ?? 'America/Cancun');

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('nova_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Robots-Tag: noindex, nofollow', true);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once $root . '/src/Database.php';
require_once $root . '/src/Security.php';
require_once $root . '/src/AuditLog.php';
require_once $root . '/src/ProductRepository.php';
require_once $root . '/src/Cart.php';
require_once $root . '/src/OrderService.php';
require_once $root . '/src/PaymentCredentialCipher.php';
require_once $root . '/src/PaymentGatewayConfig.php';
require_once $root . '/src/MercadoPago.php';
