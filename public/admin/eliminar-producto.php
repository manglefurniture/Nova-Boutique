<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Security::verifyCsrf($_POST['csrf'] ?? null);
$id = (int) ($_POST['id'] ?? 0);
ProductRepository::softDelete($db, $id);
AuditLog::record(
    $db,
    (string) ($_SESSION['nova_admin_email'] ?? 'admin'),
    'product.delete',
    'product',
    (string) $id
);
header('Location: /admin/productos.php');
exit;
