<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$must = [
    'public/index.php' => ['ProductRepository::publicList'],
    'public/_layout.php' => ['meta name="robots"', 'noindex,nofollow', '/privacidad.php'],
    'public/robots.txt' => ['Disallow: /'],
    'public/privacidad.php' => ['PRIVACY_CONTROLLER_NAME', 'PRIVACY_CONTACT_EMAIL'],
    'public/health.php' => ['SELECT 1', "'ok' => true"],
    'public/admin/productos.php' => ['Eliminar', 'Editar'],
    'public/admin/producto.php' => ['name="precio"', 'name="stock"', 'name="imagen_url"'],
    'public/admin/login.php' => ['throttleAdminLogin'],
    'database/002_seed_demo.sql' => ['Vestido Aurora', '699.00'],
    'database/001_initial.sql' => ['audit_events', 'payment_review', 'uq_pedidos_mp_payment_id'],
    'src/PaymentCredentialCipher.php' => ['GCM_TAG_LENGTH = 16', 'credential_ref', 'hache-payment-credential-aad-v1'],
    'public/webhooks/mercadopago.php' => ['HTTP_X_SIGNATURE', 'hash_hmac', 'PaymentGatewayConfig::candidates'],
    'src/OrderService.php' => ['releaseExpiredReservations', 'reacquireReleasedStock', 'payment_review'],
    'deploy/nova-release-reservations.timer' => ['OnUnitActiveSec=1min', 'Persistent=true'],
    'deploy/nova-release-reservations.service' => ['release-expired-reservations.php'],
    'deploy/backup.sh' => ['BACKUP_OK', 'mariadb-dump'],
    'deploy/preflight.sh' => ['PREFLIGHT_OK'],
    'deploy/health-check.sh' => ['HEALTH_OK'],
    'deploy/rollback.sh' => ['ROLLBACK_CODE_OK'],
];

foreach ($must as $file => $needles) {
    $content = file_get_contents($root . '/' . $file);
    if ($content === false) {
        throw new RuntimeException("No se pudo leer $file");
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            throw new RuntimeException("$file no contiene: $needle");
        }
    }
}

require_once $root . '/src/PaymentCredentialCipher.php';
$cipher = new PaymentCredentialCipher('test-master-key');
$payload = $cipher->encrypt('secret', 'MERCADO_PAGO', 'cred_test', 'access_token');
if ($cipher->decrypt($payload, 'MERCADO_PAGO', 'cred_test', 'access_token') !== 'secret') {
    throw new RuntimeException('Falló round-trip.');
}

$wrongPurposeRejected = false;
try {
    $cipher->decrypt($payload, 'MERCADO_PAGO', 'cred_test', 'webhook_secret');
} catch (RuntimeException $e) {
    $wrongPurposeRejected = true;
}
if (!$wrongPurposeRejected) {
    throw new RuntimeException('El AAD cruzado por propósito no fue rechazado.');
}

$wrongRefRejected = false;
try {
    $cipher->decrypt($payload, 'MERCADO_PAGO', 'cred_other', 'access_token');
} catch (RuntimeException $e) {
    $wrongRefRejected = true;
}
if (!$wrongRefRejected) {
    throw new RuntimeException('El AAD cruzado por credential_ref no fue rechazado.');
}

echo "Nova regressions: OK\n";
