<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

try {
    $released = OrderService::releaseExpiredReservations(Database::connection());
    fwrite(STDOUT, "Reservas liberadas: {$released}\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
