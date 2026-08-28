<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    Security::verifyCsrf($_POST['csrf'] ?? null);
    $wasUpdate = !empty($_POST['id']);
    $db->beginTransaction();
    try {
        $id = ProductRepository::save($db, $_POST);
        AuditLog::record(
            $db,
            (string) ($_SESSION['nova_admin_email'] ?? 'admin'),
            $wasUpdate ? 'product.update' : 'product.create',
            'product',
            (string) $id,
            [
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'precio' => (float) ($_POST['precio'] ?? 0),
                'stock' => (int) ($_POST['stock'] ?? 0),
                'activo' => !empty($_POST['activo']),
            ]
        );
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    header('Location: /admin/producto.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    http_response_code(422);
    adminStart('No se pudo guardar');
    echo '<div class="notice">' . Security::e($e->getMessage()) . '</div><a class="pill" href="/admin/productos.php">Volver</a>';
    adminEnd();
}
