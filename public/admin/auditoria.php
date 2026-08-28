<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$events = $db->query(
    'SELECT actor, action, entity_type, entity_id, details_json, created_at
     FROM audit_events ORDER BY id DESC LIMIT 300'
)->fetchAll();
adminStart('Auditoría');
?>
<p class="muted">Últimos cambios administrativos. Los secretos de pago nunca se registran en esta bitácora.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Fecha</th><th>Actor</th><th>Acción</th><th>Entidad</th><th>Detalle</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?= Security::e($event['created_at']) ?></td><td><?= Security::e($event['actor']) ?></td><td><strong><?= Security::e($event['action']) ?></strong></td><td><?= Security::e($event['entity_type']) ?><?= $event['entity_id'] !== null ? ' #' . Security::e($event['entity_id']) : '' ?></td><td class="muted"><?= Security::e($event['details_json'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php adminEnd(); ?>
