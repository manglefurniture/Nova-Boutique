<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

try {
    $db = Database::connection();
    $credentials = PaymentGatewayConfig::candidates($db);
} catch (Throwable $e) {
    error_log('[nova][mercadopago-webhook-config] ' . $e->getMessage());
    http_response_code(500);
    exit;
}

$signature = trim((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
$dataId = trim((string) ($_GET['data_id'] ?? $_GET['id'] ?? ''));
if ($credentials === [] || $signature === '' || $dataId === '') {
    http_response_code(401);
    exit;
}

$ts = '';
$v1 = '';
foreach (explode(',', $signature) as $part) {
    [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($key === 'ts') {
        $ts = $value;
    } elseif ($key === 'v1') {
        $v1 = $value;
    }
}
if ($v1 === '') {
    http_response_code(401);
    exit;
}

$manifestParts = ['id:' . $dataId];
if ($requestId !== '') {
    $manifestParts[] = 'request-id:' . $requestId;
}
if ($ts !== '') {
    $manifestParts[] = 'ts:' . $ts;
}
$manifest = implode(';', $manifestParts) . ';';

try {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $type = is_array($body) ? (string) ($body['type'] ?? '') : '';
    if ($type !== '' && $type !== 'payment') {
        http_response_code(200);
        echo '{"ok":true}';
        exit;
    }

    $signatureMatched = false;
    $resolvedPayment = null;
    $resolvedCredentialId = null;
    $lastFetchError = null;

    foreach ($credentials as $credential) {
        $secret = trim((string) ($credential['webhook_secret'] ?? ''));
        $accessToken = trim((string) ($credential['access_token'] ?? ''));
        if ($secret === '' || $accessToken === '') {
            continue;
        }

        $expected = hash_hmac('sha256', $manifest, $secret);
        if (!hash_equals($expected, $v1)) {
            continue;
        }

        $signatureMatched = true;
        try {
            $payment = MercadoPago::payment($accessToken, $dataId);
            $externalReference = trim((string) ($payment['external_reference'] ?? ''));
            if ($externalReference !== '') {
                $order = OrderService::findByNumber($db, $externalReference);
                if ($order && (int) ($order['payment_credential_id'] ?? 0) !== (int) $credential['credential_id']) {
                    continue;
                }
            }
            $resolvedPayment = $payment;
            $resolvedCredentialId = (int) $credential['credential_id'];
            break;
        } catch (Throwable $fetchError) {
            $lastFetchError = $fetchError;
        }
    }

    if (!$signatureMatched) {
        http_response_code(401);
        exit;
    }
    if (!is_array($resolvedPayment) || !$resolvedCredentialId) {
        if ($lastFetchError !== null) {
            throw $lastFetchError;
        }
        throw new RuntimeException('No se pudo resolver la versión histórica del pago.');
    }

    OrderService::applyPayment($db, $resolvedPayment, $resolvedCredentialId);
    http_response_code(200);
    echo '{"ok":true}';
} catch (Throwable $e) {
    error_log('[nova][mercadopago-webhook] ' . $e->getMessage());
    http_response_code(500);
}
