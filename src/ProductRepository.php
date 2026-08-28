<?php
declare(strict_types=1);

final class ProductRepository
{
    public static function publicList(PDO $db): array
    {
        return $db->query("SELECT id, slug, nombre, descripcion, precio, stock, imagen_url FROM productos WHERE activo = 1 AND eliminado_en IS NULL ORDER BY destacado DESC, creado_en DESC")->fetchAll();
    }

    public static function adminList(PDO $db): array
    {
        return $db->query("SELECT id, slug, nombre, precio, stock, version, activo, destacado, imagen_url, actualizado_en FROM productos WHERE eliminado_en IS NULL ORDER BY creado_en DESC")->fetchAll();
    }

    public static function findPublicBySlug(PDO $db, string $slug): ?array
    {
        $stmt = $db->prepare("SELECT * FROM productos WHERE slug = ? AND activo = 1 AND eliminado_en IS NULL LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM productos WHERE id = ? AND eliminado_en IS NULL LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function save(PDO $db, array $input): int
    {
        $id = max(0, (int) ($input['id'] ?? 0));
        $version = max(0, (int) ($input['version'] ?? 0));
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $slug = self::slugify((string) ($input['slug'] ?? $nombre));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $precio = (float) ($input['precio'] ?? 0);
        $stock = max(0, (int) ($input['stock'] ?? 0));
        $imagen = trim((string) ($input['imagen_url'] ?? ''));
        $activo = !empty($input['activo']) ? 1 : 0;
        $destacado = !empty($input['destacado']) ? 1 : 0;

        if ($nombre === '' || $slug === '' || $precio < 0) {
            throw new InvalidArgumentException('Nombre, slug y precio válido son obligatorios.');
        }
        if ($imagen !== '' && filter_var($imagen, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('La URL de imagen no es válida.');
        }

        if ($id > 0) {
            if ($version <= 0) {
                throw new RuntimeException('Falta la versión del producto. Vuelve a abrir la ficha antes de guardar.');
            }
            $stmt = $db->prepare(
                "UPDATE productos
                 SET slug=?, nombre=?, descripcion=?, precio=?, stock=?, imagen_url=?, activo=?, destacado=?,
                     version=version+1, actualizado_en=CURRENT_TIMESTAMP
                 WHERE id=? AND version=? AND eliminado_en IS NULL"
            );
            $stmt->execute([
                $slug,
                $nombre,
                $descripcion !== '' ? $descripcion : null,
                $precio,
                $stock,
                $imagen !== '' ? $imagen : null,
                $activo,
                $destacado,
                $id,
                $version,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'El producto cambió mientras lo editabas (por una reserva, venta u otra edición). Recarga la ficha antes de guardar.'
                );
            }
            return $id;
        }

        $stmt = $db->prepare("INSERT INTO productos (slug, nombre, descripcion, precio, stock, imagen_url, activo, destacado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$slug, $nombre, $descripcion !== '' ? $descripcion : null, $precio, $stock, $imagen !== '' ? $imagen : null, $activo, $destacado]);
        return (int) $db->lastInsertId();
    }

    public static function softDelete(PDO $db, int $id): void
    {
        $stmt = $db->prepare("UPDATE productos SET activo = 0, eliminado_en = CURRENT_TIMESTAMP, version=version+1, actualizado_en = CURRENT_TIMESTAMP WHERE id = ? AND eliminado_en IS NULL");
        $stmt->execute([$id]);
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($trans !== false) {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
