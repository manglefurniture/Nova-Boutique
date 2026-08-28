<?php
declare(strict_types=1);

final class OrderService
{
    public static function create(PDO $db, array $customer, array $cartItems): array
    {
        if ($cartItems === []) {
            throw new InvalidArgumentException('El carrito está vacío.');
        }

        $name = trim((string) ($customer['nombre'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $phone = trim((string) ($customer['telefono'] ?? ''));
        $address = trim((string) ($customer['direccion'] ?? ''));
        if ($name === '' || $phone === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Nombre, teléfono y correo válido son obligatorios.');
        }

        $ttl = max(5, min(180, (int) env('PAYMENT_RESERVATION_TTL_MINUTES', '30')));
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttl * 60));

        $db->beginTransaction();
        try {
            $config = PaymentGatewayConfig::current($db, true);
            $number = 'NOVA-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $total = 0.0;
            $locked = [];

            $select = $db->prepare(
                'SELECT id, nombre, precio, stock
                 FROM productos
                 WHERE id = ? AND activo = 1 AND eliminado_en IS NULL
                 FOR UPDATE'
            );
            foreach ($cartItems as $cartItem) {
                $select->execute([(int) $cartItem['id']]);
                $product = $select->fetch();
                if (!$product) {
                    throw new RuntimeException('Uno de los productos ya no está disponible.');
                }
                $qty = (int) $cartItem['cantidad'];
                if ($qty <= 0 || (int) $product['stock'] < $qty) {
                    throw new RuntimeException('El stock cambió. Revisa tu carrito.');
                }
                $product['cantidad'] = $qty;
                $product['subtotal'] = $qty * (float) $product['precio'];
                $total += (float) $product['subtotal'];
                $locked[] = $product;
            }

            $insert = $db->prepare(
                "INSERT INTO pedidos
                 (numero_pedido, cliente_nombre, cliente_email, cliente_telefono, cliente_direccion,
                  total, estado, estado_pago, payment_credential_id, reserva_expira_en)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending_payment', 'pending', ?, ?)"
            );
            $insert->execute([
                $number,
                $name,
                $email,
                $phone,
                $address !== '' ? $address : null,
                $total,
                $config && $config['active'] ? (int) $config['credential_id'] : null,
                $expiresAt,
            ]);
            $orderId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO pedido_items
                 (pedido_id, producto_id, nombre_snapshot, precio_unitario, cantidad, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stockStmt = $db->prepare(
                'UPDATE productos SET stock = stock - ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?'
            );
            foreach ($locked as $item) {
                $itemStmt->execute([
                    $orderId,
                    (int) $item['id'],
                    (string) $item['nombre'],
                    (float) $item['precio'],
                    (int) $item['cantidad'],
                    (float) $item['subtotal'],
                ]);
                $stockStmt->execute([(int) $item['cantidad'], (int) $item['id']]);
            }

            $db->commit();
            return [
                'id' => $orderId,
                'numero_pedido' => $number,
                'cliente_nombre' => $name,
                'cliente_email' => $email,
                'total' => $total,
                'reserva_expira_en' => $expiresAt,
                'payment_config' => $config,
                'items' => $locked,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function attachPreference(PDO $db, int $orderId, string $preferenceId): void
    {
        $stmt = $db->prepare('UPDATE pedidos SET mp_preference_id = ? WHERE id = ?');
        $stmt->execute([$preferenceId, $orderId]);
    }

    public static function findByNumber(PDO $db, string $number): ?array
    {
        $stmt = $db->prepare('SELECT * FROM pedidos WHERE numero_pedido = ? LIMIT 1');
        $stmt->execute([$number]);
        return $stmt->fetch() ?: null;
    }

    public static function applyPayment(PDO $db, array $payment, int $credentialId): ?int
    {
        $externalReference = trim((string) ($payment['external_reference'] ?? ''));
        $paymentId = trim((string) ($payment['id'] ?? ''));
        $status = trim((string) ($payment['status'] ?? 'pending'));
        $currency = strtoupper(trim((string) ($payment['currency_id'] ?? '')));
        $amount = (float) ($payment['transaction_amount'] ?? -1);
        if ($externalReference === '' || $paymentId === '' || $credentialId <= 0) {
            return null;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM pedidos WHERE numero_pedido = ? FOR UPDATE');
            $stmt->execute([$externalReference]);
            $order = $stmt->fetch();
            if (!$order) {
                $db->rollBack();
                return null;
            }

            if ((int) ($order['payment_credential_id'] ?? 0) !== $credentialId) {
                throw new RuntimeException('El pago no corresponde a la versión de credenciales ligada al pedido.');
            }
            if ($currency !== 'MXN' || abs($amount - (float) $order['total']) > 0.009) {
                throw new RuntimeException('El importe o la moneda del pago no coincide con el pedido.');
            }

            $paid = $status === 'approved';
            $update = $db->prepare(
                "UPDATE pedidos
                 SET mp_payment_id = ?, estado_pago = ?,
                     estado = IF(?, 'paid', estado),
                     reserva_expira_en = IF(?, NULL, reserva_expira_en),
                     actualizado_en = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $update->execute([$paymentId, $status, $paid ? 1 : 0, $paid ? 1 : 0, (int) $order['id']]);
            $db->commit();
            return (int) $order['id'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function releaseExpiredReservations(PDO $db): int
    {
        $db->beginTransaction();
        try {
            $orders = $db->query(
                "SELECT id FROM pedidos
                 WHERE estado = 'pending_payment'
                   AND estado_pago <> 'approved'
                   AND reserva_liberada = 0
                   AND reserva_expira_en IS NOT NULL
                   AND reserva_expira_en <= CURRENT_TIMESTAMP
                 FOR UPDATE"
            )->fetchAll();

            $itemsStmt = $db->prepare('SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ?');
            $restore = $db->prepare('UPDATE productos SET stock = stock + ?, actualizado_en = CURRENT_TIMESTAMP WHERE id = ?');
            $cancel = $db->prepare(
                "UPDATE pedidos
                 SET estado = 'cancelled', reserva_liberada = 1, actualizado_en = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            foreach ($orders as $order) {
                $itemsStmt->execute([(int) $order['id']]);
                foreach ($itemsStmt->fetchAll() as $item) {
                    if (!empty($item['producto_id'])) {
                        $restore->execute([(int) $item['cantidad'], (int) $item['producto_id']]);
                    }
                }
                $cancel->execute([(int) $order['id']]);
            }

            $db->commit();
            return count($orders);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
