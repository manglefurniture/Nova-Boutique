<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$order = OrderService::findByNumber(Database::connection(), trim((string) ($_GET['order'] ?? '')));
$paymentUnavailable = (string) ($_GET['payment'] ?? '') === 'unavailable';
pageStart('Pedido recibido');
?>
<main class="wrap">
<div class="panel" style="max-width:720px;margin:60px auto">
<span class="eyebrow">Gracias</span>
<h1 style="font:400 54px Georgia,serif">Pedido recibido.</h1>
<?php if ($paymentUnavailable): ?>
<div class="notice">Mercado Pago no respondió en este momento. Tu pedido quedó registrado y reservado temporalmente; no se generó un segundo pedido.</div>
<?php endif; ?>
<?php if ($order): ?>
<p>Tu número es <strong><?= Security::e($order['numero_pedido']) ?></strong>.</p>
<p>Estado de pago: <span class="badge"><?= Security::e($order['estado_pago']) ?></span></p>
<?php if (!empty($order['reserva_expira_en']) && $order['estado_pago'] !== 'approved'): ?>
<p class="muted">La pieza queda reservada hasta <?= Security::e($order['reserva_expira_en']) ?>.</p>
<?php endif; ?>
<?php endif; ?>
<a class="btn" href="/">Volver a Nova</a>
</div>
</main>
<?php pageEnd(); ?>
