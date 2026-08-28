<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$db = Database::connection();

function galleryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$demo = ProductRepository::findPublicBySlug($db, 'vestido-aurora');
galleryAssert($demo !== null, 'No existe Vestido Aurora para probar la galería.');

$images = ProductRepository::images($db, (int) $demo['id']);
galleryAssert(count($images) === 1, 'La migración no conservó exactamente una imagen inicial del producto demo.');
galleryAssert((string) $images[0]['url'] === (string) $demo['imagen_url'], 'La imagen inicial de la galería no coincide con la principal existente.');
galleryAssert((int) $images[0]['orden'] === 1, 'La imagen inicial no quedó con orden 1.');

$insert = $db->prepare('INSERT INTO producto_imagenes (producto_id, url, alt_text, orden) VALUES (?, ?, ?, ?)');
$insert->execute([(int) $demo['id'], '/uploads/productos/ci-segunda.webp', 'Vestido Aurora vista secundaria', 2]);

$images = ProductRepository::images($db, (int) $demo['id']);
galleryAssert(count($images) === 2, 'El repositorio no devolvió las dos imágenes de la galería.');
galleryAssert((int) $images[0]['orden'] === 1 && (int) $images[1]['orden'] === 2, 'La galería no respeta el orden persistido.');

$duplicateOrderRejected = false;
try {
    $insert->execute([(int) $demo['id'], '/uploads/productos/ci-duplicada.webp', 'Duplicada', 2]);
} catch (PDOException $e) {
    $duplicateOrderRejected = true;
}
galleryAssert($duplicateOrderRejected, 'MariaDB permitió dos imágenes con el mismo orden dentro del producto.');

$db->prepare('DELETE FROM producto_imagenes WHERE producto_id = ? AND orden = 2')->execute([(int) $demo['id']]);

echo "Nova gallery MariaDB integration: OK\n";
