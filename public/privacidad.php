<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$controller = trim((string) env('PRIVACY_CONTROLLER_NAME', 'Nova Boutique'));
$address = trim((string) env('PRIVACY_ADDRESS', ''));
$email = trim((string) env('PRIVACY_CONTACT_EMAIL', ''));
pageStart('Aviso de privacidad');
?>
<main class="wrap" style="max-width:860px"><div class="section-head"><div><span class="eyebrow">Privacidad</span><h2>Aviso de privacidad</h2></div></div><div class="panel"><p><strong><?= Security::e($controller) ?></strong> es responsable del tratamiento de los datos personales recabados durante el proceso de compra.</p><?php if ($address !== ''): ?><p>Domicilio del responsable: <?= Security::e($address) ?>.</p><?php endif; ?><p>Podemos tratar nombre, correo electrónico, teléfono, dirección o referencia de entrega y datos asociados al pedido para procesar compras, pagos, entrega, atención al cliente, prevención de fraude y cumplimiento de obligaciones aplicables.</p><p>Los datos de pago sensibles no se almacenan directamente en Nova Boutique; cuando la pasarela está activa, el pago se procesa mediante el proveedor configurado.</p><p>No se usarán los datos para finalidades incompatibles con la compra sin informar previamente cuando corresponda.</p><?php if ($email !== ''): ?><p>Para solicitudes relacionadas con acceso, rectificación, cancelación, oposición o eliminación, escribe a <a href="mailto:<?= Security::e($email) ?>"><?= Security::e($email) ?></a>.</p><?php else: ?><p>El canal de privacidad debe configurarse antes de aceptar pedidos reales.</p><?php endif; ?><p class="muted">Última actualización: 28 de agosto de 2026.</p></div></main>
<?php pageEnd(); ?>
