<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    Database::connection()->query('SELECT 1')->fetchColumn();
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'service' => 'nova-boutique',
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[nova][health] ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'service' => 'nova-boutique',
    ], JSON_THROW_ON_ERROR);
}
