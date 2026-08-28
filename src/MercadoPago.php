<?php
declare(strict_types=1);

final class MercadoPago
{
    public static function createPreference(array $config, array $order, array $items): array
    {
        $appUrl = rtrim((string) env('APP_URL', 'https://nova.hacheinteractive.com'), '/');
        $payloadItems = [];
        foreach ($items as $item) {
            $payloadItems[] = [
                'title' => (string) $item['nombre'],
                'quantity' => (int) $item['cantidad'],
                'unit_price' => (float) $item['precio'],
                'currency_id' => 'MXN',
            ];
        }

        $payload = [
            'items' => $payloadItems,
            'external_reference' => (string) $order['numero_pedido'],
            'notification_url' => $appUrl . '/webhooks/mercadopago.php',
            'back_urls' => [
                'success' => $appUrl . '/resultado.php?order=' . rawurlencode((string) $order['numero_pedido']),
                'pending' => $appUrl . '/resultado.php?order=' . rawurlencode((string) $order['numero_pedido']),
                'failure' => $appUrl . '/resultado.php?order=' . rawurlencode((string) $order['numero_pedido']),
            ],
            'auto_return' => 'approved',
            'payer' => [
                'name' => (string) $order['cliente_nombre'],
                'email' => (string) $order['cliente_email'],
            ],
        ];

        if (!empty($order['reserva_expira_en'])) {
            $expiration = new DateTimeImmutable((string) $order['reserva_expira_en'], new DateTimeZone('America/Cancun'));
            $payload['expires'] = true;
            $payload['expiration_date_to'] = $expiration->format(DateTimeInterface::ATOM);
        }

        return self::request(
            'POST',
            'https://api.mercadopago.com/checkout/preferences',
            (string) $config['access_token'],
            $payload
        );
    }

    public static function payment(string $accessToken, string $paymentId): array
    {
        if (!preg_match('/^[0-9]+$/', $paymentId)) {
            throw new InvalidArgumentException('ID de pago no válido.');
        }
        return self::request(
            'GET',
            'https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId),
            $accessToken
        );
    }

    public static function currentUser(string $accessToken): array
    {
        return self::request('GET', 'https://api.mercadopago.com/users/me', $accessToken);
    }

    private static function request(string $method, string $url, string $accessToken, ?array $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL es obligatorio para Mercado Pago.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar la conexión con Mercado Pago.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('No se pudo conectar con Mercado Pago.');
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Mercado Pago devolvió una respuesta inválida.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'Operación rechazada.');
            throw new RuntimeException('Mercado Pago: ' . $message);
        }
        return $decoded;
    }
}
