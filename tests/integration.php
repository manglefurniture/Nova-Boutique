<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$db = Database::connection();

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// CRUD de producto.
$testSlug = 'producto-ci-' . bin2hex(random_bytes(3));
$productId = ProductRepository::save($db, [
    'nombre' => 'Producto CI',
    'slug' => $testSlug,
    'precio' => '250.00',
    'stock' => '2',
    'imagen_url' => 'https://example.com/test.jpg',
    'activo' => '1',
]);
$product = ProductRepository::findById($db, $productId);
assertTrue($product !== null && (float) $product['precio'] === 250.0, 'No se creó el producto CI.');

ProductRepository::save($db, [
    'id' => $productId,
    'nombre' => 'Producto CI editado',
    'slug' => $testSlug,
    'precio' => '275.00',
    'stock' => '3',
    'imagen_url' => 'https://example.com/test2.jpg',
    'activo' => '1',
]);
$product = ProductRepository::findById($db, $productId);
assertTrue($product !== null && (float) $product['precio'] === 275.0, 'No se editó el precio del producto CI.');

ProductRepository::softDelete($db, $productId);
assertTrue(ProductRepository::findById($db, $productId) === null, 'La eliminación lógica no ocultó el producto.');

// Reserva y liberación de stock usando el producto demo.
$demo = ProductRepository::findPublicBySlug($db, 'vestido-aurora');
assertTrue($demo !== null, 'No existe el producto demo.');
$stockBefore = (int) $demo['stock'];
$order = OrderService::create($db, [
    'nombre' => 'Cliente CI',
    'email' => 'ci@example.com',
    'telefono' => '9980000000',
    'direccion' => 'Prueba CI',
], [[
    'id' => (int) $demo['id'],
    'cantidad' => 1,
]]);

$stockAfter = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockAfter === $stockBefore - 1, 'El checkout no reservó el stock.');

$db->prepare('UPDATE pedidos SET reserva_expira_en = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([(int) $order['id']]);
$released = OrderService::releaseExpiredReservations($db);
assertTrue($released >= 1, 'No se liberó la reserva vencida.');
$stockRestored = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockRestored === $stockBefore, 'El stock no volvió después de liberar la reserva.');

// Las versiones de credenciales son inmutables a nivel MariaDB.
$db->exec("INSERT INTO payment_gateway_credentials
(provider, credential_ref, environment, access_token_enc, webhook_secret_enc)
VALUES ('TEST_PROVIDER', 'cred_ci', 'TEST', 'cipher-a', 'cipher-b')");
$immutable = false;
try {
    $db->exec("UPDATE payment_gateway_credentials SET account_label='mutado' WHERE provider='TEST_PROVIDER' AND credential_ref='cred_ci'");
} catch (PDOException $e) {
    $immutable = true;
}
assertTrue($immutable, 'MariaDB permitió mutar una versión histórica de credenciales.');

echo "Nova MariaDB integration: OK\n";
