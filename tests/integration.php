<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$db = Database::connection();
$_SESSION = [];

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
    'version' => (int) $product['version'],
    'nombre' => 'Producto CI editado',
    'slug' => $testSlug,
    'precio' => '275.00',
    'stock' => '3',
    'imagen_url' => 'https://example.com/test2.jpg',
    'activo' => '1',
]);
$product = ProductRepository::findById($db, $productId);
assertTrue($product !== null && (float) $product['precio'] === 275.0, 'No se editó el precio del producto CI.');
assertTrue((int) $product['version'] === 2, 'La edición administrativa no incrementó la versión del producto.');

ProductRepository::softDelete($db, $productId);
assertTrue(ProductRepository::findById($db, $productId) === null, 'La eliminación lógica no ocultó el producto.');

// Auditoría mínima de mutaciones administrativas.
AuditLog::record($db, 'ci@example.com', 'product.test', 'product', (string) $productId, ['source' => 'integration']);
$auditCount = (int) $db->query("SELECT COUNT(*) FROM audit_events WHERE action='product.test'")->fetchColumn();
assertTrue($auditCount === 1, 'No se registró el evento de auditoría.');

// Un producto que se quedó sin stock debe desaparecer del carrito de sesión.
$zeroSlug = 'sin-stock-ci-' . bin2hex(random_bytes(3));
$zeroId = ProductRepository::save($db, [
    'nombre' => 'Sin stock CI',
    'slug' => $zeroSlug,
    'precio' => '100.00',
    'stock' => '0',
    'imagen_url' => 'https://example.com/zero.jpg',
    'activo' => '1',
]);
Cart::add($zeroId, 1);
assertTrue(Cart::count() === 1, 'No se preparó el carrito de stock cero.');
assertTrue(Cart::detailed($db) === [], 'Un producto con stock cero siguió apareciendo en el carrito.');
assertTrue(Cart::all() === [], 'El producto sin stock no fue retirado de la sesión.');
ProductRepository::softDelete($db, $zeroId);

// Un formulario admin abierto antes de una reserva no puede restaurar stock obsoleto.
$staleSlug = 'stale-ci-' . bin2hex(random_bytes(3));
$staleId = ProductRepository::save($db, [
    'nombre' => 'Stale CI',
    'slug' => $staleSlug,
    'precio' => '180.00',
    'stock' => '2',
    'imagen_url' => 'https://example.com/stale.jpg',
    'activo' => '1',
]);
$staleSnapshot = ProductRepository::findById($db, $staleId);
assertTrue($staleSnapshot !== null, 'No se creó el producto para probar concurrencia admin.');
$staleOrder = OrderService::create($db, [
    'nombre' => 'Reserva Concurrente',
    'email' => 'stale@example.com',
    'telefono' => '9980000099',
    'direccion' => 'Prueba CI',
], [[
    'id' => $staleId,
    'cantidad' => 1,
]]);
$afterReservation = ProductRepository::findById($db, $staleId);
assertTrue($afterReservation !== null && (int) $afterReservation['stock'] === 1, 'La reserva concurrente no consumió stock.');
assertTrue((int) $afterReservation['version'] === (int) $staleSnapshot['version'] + 1, 'La reserva no incrementó la versión del producto.');

$staleRejected = false;
try {
    ProductRepository::save($db, [
        'id' => $staleId,
        'version' => (int) $staleSnapshot['version'],
        'nombre' => 'Stale CI',
        'slug' => $staleSlug,
        'precio' => '180.00',
        'stock' => '2',
        'imagen_url' => 'https://example.com/stale.jpg',
        'activo' => '1',
    ]);
} catch (RuntimeException $e) {
    $staleRejected = true;
}
assertTrue($staleRejected, 'Un formulario administrativo obsoleto pudo sobrescribir una reserva.');
$afterRejectedEdit = ProductRepository::findById($db, $staleId);
assertTrue($afterRejectedEdit !== null && (int) $afterRejectedEdit['stock'] === 1, 'El intento obsoleto restauró stock reservado.');
$db->prepare('UPDATE pedidos SET reserva_expira_en = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([(int) $staleOrder['id']]);
OrderService::releaseExpiredReservations($db);
$afterStaleRelease = ProductRepository::findById($db, $staleId);
assertTrue($afterStaleRelease !== null && (int) $afterStaleRelease['stock'] === 2, 'No se restauró la reserva de la prueba concurrente.');
ProductRepository::softDelete($db, $staleId);

// Credencial histórica de prueba para conciliación de pagos.
$db->exec("INSERT INTO payment_gateway_credentials
(provider, credential_ref, environment, access_token_enc, webhook_secret_enc)
VALUES ('TEST_PROVIDER', 'cred_ci', 'TEST', 'cipher-a', 'cipher-b')");
$credentialId = (int) $db->lastInsertId();

// Reserva, liberación y pago tardío con stock todavía disponible: debe reacquirir la unidad.
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
$db->prepare('UPDATE pedidos SET payment_credential_id = ? WHERE id = ?')->execute([$credentialId, (int) $order['id']]);

$stockAfter = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockAfter === $stockBefore - 1, 'El checkout no reservó el stock.');

$db->prepare('UPDATE pedidos SET reserva_expira_en = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([(int) $order['id']]);
$released = OrderService::releaseExpiredReservations($db);
assertTrue($released >= 1, 'No se liberó la reserva vencida.');
$stockRestored = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockRestored === $stockBefore, 'El stock no volvió después de liberar la reserva.');

OrderService::applyPayment($db, [
    'id' => '900001',
    'status' => 'approved',
    'external_reference' => (string) $order['numero_pedido'],
    'currency_id' => 'MXN',
    'transaction_amount' => (float) $order['total'],
], $credentialId);
$paidOrder = OrderService::findByNumber($db, (string) $order['numero_pedido']);
assertTrue($paidOrder !== null && $paidOrder['estado'] === 'paid', 'El pago tardío con stock disponible no quedó pagado.');
$stockReacquired = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockReacquired === $stockBefore - 1, 'El pago tardío no volvió a consumir la unidad liberada.');

// Repetir el mismo webhook debe ser idempotente y no volver a consumir stock.
OrderService::applyPayment($db, [
    'id' => '900001',
    'status' => 'approved',
    'external_reference' => (string) $order['numero_pedido'],
    'currency_id' => 'MXN',
    'transaction_amount' => (float) $order['total'],
], $credentialId);
$stockAfterRepeat = (int) $db->query('SELECT stock FROM productos WHERE id=' . (int) $demo['id'])->fetchColumn();
assertTrue($stockAfterRepeat === $stockReacquired, 'Un webhook repetido consumió stock por segunda vez.');

// Pago tardío sin inventario disponible: registra el cobro pero no vende stock inexistente.
$lateSlug = 'late-ci-' . bin2hex(random_bytes(3));
$lateProductId = ProductRepository::save($db, [
    'nombre' => 'Pago tardío CI',
    'slug' => $lateSlug,
    'precio' => '150.00',
    'stock' => '1',
    'imagen_url' => 'https://example.com/late.jpg',
    'activo' => '1',
]);
$lateOrder = OrderService::create($db, [
    'nombre' => 'Cliente Tardío',
    'email' => 'late@example.com',
    'telefono' => '9980000001',
    'direccion' => 'Prueba CI',
], [[
    'id' => $lateProductId,
    'cantidad' => 1,
]]);
$db->prepare('UPDATE pedidos SET payment_credential_id = ?, reserva_expira_en = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')
    ->execute([$credentialId, (int) $lateOrder['id']]);
OrderService::releaseExpiredReservations($db);
$db->prepare('UPDATE productos SET stock = 0, version = version + 1 WHERE id = ?')->execute([$lateProductId]);

OrderService::applyPayment($db, [
    'id' => '900002',
    'status' => 'approved',
    'external_reference' => (string) $lateOrder['numero_pedido'],
    'currency_id' => 'MXN',
    'transaction_amount' => (float) $lateOrder['total'],
], $credentialId);
$reviewOrder = OrderService::findByNumber($db, (string) $lateOrder['numero_pedido']);
assertTrue($reviewOrder !== null && $reviewOrder['estado'] === 'payment_review', 'El pago tardío sin stock no quedó en revisión.');
assertTrue($reviewOrder['estado_pago'] === 'approved', 'El pago aprobado no quedó registrado financieramente.');
$lateStock = (int) $db->query('SELECT stock FROM productos WHERE id=' . $lateProductId)->fetchColumn();
assertTrue($lateStock === 0, 'La conciliación tardía inventó stock inexistente.');
ProductRepository::softDelete($db, $lateProductId);

// Las versiones de credenciales son inmutables a nivel MariaDB.
$immutable = false;
try {
    $db->exec("UPDATE payment_gateway_credentials SET account_label='mutado' WHERE id=" . $credentialId);
} catch (PDOException $e) {
    $immutable = true;
}
assertTrue($immutable, 'MariaDB permitió mutar una versión histórica de credenciales.');

echo "Nova MariaDB integration: OK\n";
