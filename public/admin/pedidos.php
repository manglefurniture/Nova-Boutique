<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$orders = $db->query("SELECT numero_pedido,cliente_nombre,cliente_email,total,estado,estado_pago,creado_en FROM pedidos ORDER BY id DESC LIMIT 200")->fetchAll();
$reviewCount = 0;
foreach ($orders as $order) {
    if ($order['estado'] === 'payment_review') {
        $reviewCount++;
    }
}
adminStart('Pedidos');
if ($reviewCount > 0): ?><div class="notice"><strong><?= $reviewCount ?> pedido(s) requieren revisión de pago.</strong> Mercado Pago confirmó el cobro después de liberar la reserva y no había inventario suficiente para readquirirla automáticamente.</div><?php endif; ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Pedido</th><th>Pago</th><th>Fecha</th></tr></thead><tbody><?php foreach ($orders as $o): ?><tr><td><strong><?= Security::e($o['numero_pedido']) ?></strong></td><td><?= Security::e($o['cliente_nombre']) ?><div class="muted"><?= Security::e($o['cliente_email']) ?></div></td><td>$<?= number_format((float) $o['total'], 0) ?></td><td><?php if ($o['estado'] === 'payment_review'): ?><span class="badge">REVISAR PAGO</span><?php else: ?><?= Security::e($o['estado']) ?><?php endif; ?></td><td><?= Security::e($o['estado_pago']) ?></td><td><?= Security::e($o['creado_en']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php adminEnd(); ?>
