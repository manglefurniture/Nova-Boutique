<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$db = Database::connection();
$order = OrderService::findByNumber($db, trim((string) ($_GET['order'] ?? '')));

if ($order && !empty($_GET['payment_id']) && !empty($order['payment_credential_id'])) {
    try {
        $credential = PaymentGatewayConfig::byId($db, (int) $order['payment_credential_id']);
        if ($credential) {
            $payment = MercadoPago::payment((string) $credential['access_token'], (string) $_GET['payment_id']);
            if (trim((string) ($payment['external_reference'] ?? '')) !== (string) $order['numero_pedido']) {
                throw new RuntimeException('El pago retornado no corresponde al pedido.');
            }
            OrderService::applyPayment($db, $payment, (int) $credential['credential_id']);
        }
    } catch (Throwable $e) {
        error_log('[nova][payment-return] ' . $e->getMessage());
    }
}

header('Location: /gracias.php?order=' . rawurlencode((string) ($order['numero_pedido'] ?? '')));
exit;
