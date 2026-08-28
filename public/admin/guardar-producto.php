<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$uploadedPaths = [];
$filesToDeleteAfterCommit = [];
$galleryLock = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    Security::verifyCsrf($_POST['csrf'] ?? null);
    $wasUpdate = !empty($_POST['id']);
    $galleryLock = adminAcquireGalleryLock();

    $db->beginTransaction();
    try {
        $id = ProductRepository::save($db, $_POST);
        $name = trim((string) ($_POST['nombre'] ?? 'Producto'));

        $existingImages = ProductRepository::images($db, $id);
        $removeIdsRaw = is_array($_POST['eliminar_imagen'] ?? null) ? $_POST['eliminar_imagen'] : [];
        $removeIds = array_fill_keys(array_map('intval', $removeIdsRaw), true);
        $principalId = max(0, (int) ($_POST['imagen_principal'] ?? 0));

        $imageList = [];
        foreach ($existingImages as $image) {
            $imageId = (int) $image['id'];
            if (isset($removeIds[$imageId])) {
                $localPath = adminUploadedFilePath((string) $image['url']);
                if ($localPath !== null) {
                    $filesToDeleteAfterCommit[] = $localPath;
                }
                continue;
            }
            $imageList[] = [
                'id' => $imageId,
                'url' => (string) $image['url'],
                'alt_text' => trim((string) ($image['alt_text'] ?? '')) ?: $name,
            ];
        }

        if ($principalId > 0) {
            foreach ($imageList as $index => $image) {
                if ((int) $image['id'] !== $principalId) {
                    continue;
                }
                $principalImage = $image;
                array_splice($imageList, $index, 1);
                array_unshift($imageList, $principalImage);
                break;
            }
        }

        $uploadNames = $_FILES['imagenes']['name'] ?? [];
        $uploadTmp = $_FILES['imagenes']['tmp_name'] ?? [];
        $uploadErrors = $_FILES['imagenes']['error'] ?? [];
        $uploadSizes = $_FILES['imagenes']['size'] ?? [];
        if (!is_array($uploadNames)) {
            $uploadNames = [];
        }

        $uploadDir = adminUploadDir();
        $uploadUrl = adminUploadUrl();
        $pendingUploads = 0;
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        foreach ($uploadNames as $index => $unusedOriginalName) {
            $error = (int) ($uploadErrors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $pendingUploads++;
            if ($pendingUploads > 6) {
                throw new RuntimeException('Puedes subir hasta 6 fotos a la vez.');
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Una de las fotos no pudo subirse. Inténtalo de nuevo.');
            }
            if ((int) ($uploadSizes[$index] ?? 0) > 8 * 1024 * 1024) {
                throw new RuntimeException('Cada foto debe pesar máximo 8 MB.');
            }
            if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                throw new RuntimeException('La carpeta de imágenes no está preparada para recibir archivos.');
            }

            $tmpPath = (string) ($uploadTmp[$index] ?? '');
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new RuntimeException('La carga de una imagen no es válida.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($tmpPath);
            if (!isset($allowed[$mime]) || @getimagesize($tmpPath) === false) {
                throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WebP válidas.');
            }

            $fileName = sprintf('producto-%d-%s.%s', $id, bin2hex(random_bytes(8)), $allowed[$mime]);
            $destination = $uploadDir . '/' . $fileName;
            if (!move_uploaded_file($tmpPath, $destination)) {
                throw new RuntimeException('No se pudo guardar una de las imágenes.');
            }
            @chmod($destination, 0644);
            $uploadedPaths[] = $destination;
            $imageList[] = [
                'id' => 0,
                'url' => $uploadUrl . '/' . $fileName,
                'alt_text' => $name,
            ];
        }

        if (count($imageList) > 8) {
            throw new RuntimeException('Cada producto puede tener hasta 8 fotos.');
        }

        $deleteImages = $db->prepare('DELETE FROM producto_imagenes WHERE producto_id = ?');
        $deleteImages->execute([$id]);
        $insertImage = $db->prepare(
            'INSERT INTO producto_imagenes (producto_id, url, alt_text, orden) VALUES (?, ?, ?, ?)'
        );
        foreach ($imageList as $index => $image) {
            $insertImage->execute([$id, $image['url'], $image['alt_text'], $index + 1]);
        }

        $mainImageUrl = $imageList[0]['url'] ?? null;
        $updateMain = $db->prepare('UPDATE productos SET imagen_url = ? WHERE id = ?');
        $updateMain->execute([$mainImageUrl, $id]);

        AuditLog::record(
            $db,
            (string) ($_SESSION['nova_admin_email'] ?? 'admin'),
            $wasUpdate ? 'product.update' : 'product.create',
            'product',
            (string) $id,
            [
                'nombre' => $name,
                'precio' => (float) ($_POST['precio'] ?? 0),
                'stock' => (int) ($_POST['stock'] ?? 0),
                'activo' => !empty($_POST['activo']),
                'imagenes' => count($imageList),
            ]
        );
        $db->commit();

        foreach ($filesToDeleteAfterCommit as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($uploadedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $e;
    }

    adminReleaseGalleryLock($galleryLock);
    $galleryLock = null;
    header('Location: /admin/producto.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    adminReleaseGalleryLock($galleryLock);
    $galleryLock = null;
    http_response_code(422);
    adminStart('No se pudo guardar');
    echo '<div class="notice">' . Security::e($e->getMessage()) . '</div><a class="pill" href="/admin/productos.php">Volver</a>';
    adminEnd();
}
