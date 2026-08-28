<?php
declare(strict_types=1);

final class Cart
{
    public static function all(): array
    {
        return is_array($_SESSION['nova_cart'] ?? null) ? $_SESSION['nova_cart'] : [];
    }

    public static function count(): int
    {
        return array_sum(array_map('intval', self::all()));
    }

    public static function add(int $productId, int $quantity = 1): void
    {
        $cart = self::all();
        $cart[$productId] = min(20, max(1, (int) ($cart[$productId] ?? 0) + max(1, $quantity)));
        $_SESSION['nova_cart'] = $cart;
    }

    public static function update(array $quantities): void
    {
        $cart = [];
        foreach ($quantities as $id => $qty) {
            $id = (int) $id;
            $qty = (int) $qty;
            if ($id > 0 && $qty > 0) {
                $cart[$id] = min(20, $qty);
            }
        }
        $_SESSION['nova_cart'] = $cart;
    }

    public static function remove(int $productId): void
    {
        $cart = self::all();
        unset($cart[$productId]);
        $_SESSION['nova_cart'] = $cart;
    }

    public static function clear(): void
    {
        unset($_SESSION['nova_cart']);
    }

    public static function detailed(PDO $db): array
    {
        $cart = self::all();
        if ($cart === []) {
            return [];
        }
        $ids = array_map('intval', array_keys($cart));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT id, slug, nombre, precio, stock, imagen_url FROM productos WHERE id IN ($placeholders) AND activo = 1 AND eliminado_en IS NULL");
        $stmt->execute($ids);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['id'];
            $qty = max(1, min((int) $cart[$id], max(0, (int) $row['stock'])));
            if ($qty <= 0) {
                continue;
            }
            $row['cantidad'] = $qty;
            $row['subtotal'] = $qty * (float) $row['precio'];
            $items[] = $row;
        }
        return $items;
    }

    public static function total(array $items): float
    {
        return array_sum(array_map(static fn(array $item): float => (float) $item['subtotal'], $items));
    }
}
