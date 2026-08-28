<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Security::verifyCsrf($_POST['csrf'] ?? null);
        $actor = (string) ($_SESSION['nova_admin_email'] ?? 'admin');
        $db->beginTransaction();
        try {
            PaymentGatewayConfig::save($db, $_POST, $actor);
            AuditLog::record(
                $db,
                $actor,
                'payment.credentials.rotate',
                'payment_gateway',
                'MERCADO_PAGO',
                [
                    'environment' => strtoupper(trim((string) ($_POST['environment'] ?? 'PRODUCTION'))),
                    'active' => !empty($_POST['active']),
                    'account_id' => trim((string) ($_POST['account_id'] ?? '')),
                    'account_label' => trim((string) ($_POST['account_label'] ?? '')),
                ]
            );
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $message = 'Nueva versión de credenciales guardada.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$stmt = $db->query("SELECT configured,active,updated_at FROM payment_gateway_config WHERE provider='MERCADO_PAGO' LIMIT 1");
$current = $stmt->fetch();
adminStart('Mercado Pago');
if ($message): ?><div class="notice success"><?= Security::e($message) ?></div><?php endif;
if ($error): ?><div class="notice"><?= Security::e($error) ?></div><?php endif; ?>
<div class="panel"><p>Estado: <span class="badge <?= !empty($current['active']) ? 'on' : 'off' ?>"><?= !empty($current['active']) ? 'Activo' : 'Inactivo' ?></span></p><p class="muted">Los secretos guardados nunca se vuelven a mostrar. Guardar crea una nueva versión histórica inmutable.</p></div><br>
<form class="panel form-grid" method="post"><input type="hidden" name="csrf" value="<?= Security::e(Security::csrfToken()) ?>"><div class="field"><label>Ambiente</label><select name="environment"><option>PRODUCTION</option><option>TEST</option></select></div><div class="field"><label>Public Key</label><input name="public_key"></div><div class="field"><label>Access Token</label><input type="password" name="access_token" autocomplete="new-password" required></div><div class="field"><label>Webhook Secret</label><input type="password" name="webhook_secret" autocomplete="new-password" required></div><div class="field"><label>ID de cuenta</label><input name="account_id"></div><div class="field"><label>Etiqueta</label><input name="account_label" placeholder="Cuenta principal"></div><div class="field full"><label><input type="checkbox" name="active" value="1"> Activar para nuevos checkouts</label></div><div class="field full"><button class="btn" type="submit">Guardar nueva versión</button></div></form>
<?php adminEnd(); ?>
