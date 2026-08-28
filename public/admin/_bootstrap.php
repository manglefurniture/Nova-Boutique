<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
Security::requireAdmin();
$db = Database::connection();

function adminUploadDir(): string
{
    return rtrim((string) env('UPLOAD_DIR', dirname(__DIR__) . '/uploads/productos'), '/');
}

function adminUploadUrl(): string
{
    return rtrim((string) env('UPLOAD_URL', '/uploads/productos'), '/');
}

function adminUploadedFilePath(string $url): ?string
{
    $base = adminUploadUrl() . '/';
    if (!str_starts_with($url, $base)) {
        return null;
    }
    return adminUploadDir() . '/' . basename($url);
}

function adminAcquireGalleryLock()
{
    $path = dirname(__DIR__, 2) . '/deploy/gallery.lock';
    $handle = @fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('No se pudo preparar temporalmente la galería.');
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('No se pudo bloquear temporalmente la galería.');
    }
    return $handle;
}

function adminReleaseGalleryLock($handle): void
{
    if (!is_resource($handle)) {
        return;
    }
    flock($handle, LOCK_UN);
    fclose($handle);
}

function adminStart(string $title): void
{ ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= Security::e($title) ?> · Nova Admin</title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="admin-shell"><aside class="admin-side"><a class="brand" href="/admin/">NOVA ADMIN</a><nav class="admin-menu"><a href="/admin/">Resumen</a><a href="/admin/productos.php">Productos</a><a href="/admin/pedidos.php">Pedidos</a><a href="/admin/clientes.php">Clientes</a><a href="/admin/ventas.php">Ventas</a><a href="/admin/pasarelas.php">Mercado Pago</a><a href="/admin/auditoria.php">Auditoría</a><a href="/" target="_blank">Ver tienda ↗</a><a href="/admin/logout.php">Cerrar sesión</a></nav></aside><main class="admin-main"><h1><?= Security::e($title) ?></h1><?php
}
function adminEnd(): void
{
    echo '</main></div></body></html>';
}
