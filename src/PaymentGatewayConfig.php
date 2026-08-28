<?php
declare(strict_types=1);

final class PaymentGatewayConfig
{
    private const PROVIDER = 'MERCADO_PAGO';

    public static function current(PDO $db, bool $forCheckout = false): ?array
    {
        $lock = $forCheckout && $db->inTransaction() ? ' LOCK IN SHARE MODE' : '';
        $stmt = $db->prepare(
            "SELECT c.provider, c.configured, c.active, c.current_credential_id,
                    v.id, v.credential_ref, v.environment, v.public_key,
                    v.access_token_enc, v.webhook_secret_enc, v.account_id, v.account_label
             FROM payment_gateway_config c
             LEFT JOIN payment_gateway_credentials v
               ON v.provider = c.provider AND v.id = c.current_credential_id
             WHERE c.provider = ? LIMIT 1" . $lock
        );
        $stmt->execute([self::PROVIDER]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['configured'] !== 1 || empty($row['id'])) {
            return null;
        }
        return self::hydrate($row, (int) $row['active'] === 1);
    }

    public static function byId(PDO $db, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $db->prepare(
            "SELECT id, provider, credential_ref, environment, public_key,
                    access_token_enc, webhook_secret_enc, account_id, account_label
             FROM payment_gateway_credentials
             WHERE id = ? AND provider = ? LIMIT 1"
        );
        $stmt->execute([$id, self::PROVIDER]);
        $row = $stmt->fetch();
        return $row ? self::hydrate($row, false) : null;
    }

    public static function candidates(PDO $db): array
    {
        $configStmt = $db->prepare(
            'SELECT configured, current_credential_id FROM payment_gateway_config WHERE provider = ? LIMIT 1'
        );
        $configStmt->execute([self::PROVIDER]);
        $config = $configStmt->fetch();
        if (!$config || (int) $config['configured'] !== 1) {
            return [];
        }

        $currentId = (int) ($config['current_credential_id'] ?? 0);
        $stmt = $db->prepare(
            "SELECT id, provider, credential_ref, environment, public_key,
                    access_token_enc, webhook_secret_enc, account_id, account_label
             FROM payment_gateway_credentials
             WHERE provider = ?
             ORDER BY (id = ?) DESC, id DESC"
        );
        $stmt->execute([self::PROVIDER, $currentId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $credential = self::hydrate($row, (int) $row['id'] === $currentId);
            if ($credential['access_token'] === '' || $credential['webhook_secret'] === '') {
                continue;
            }
            $result[] = $credential;
        }
        return $result;
    }

    public static function save(PDO $db, array $input, string $actor): void
    {
        $environment = strtoupper(trim((string) ($input['environment'] ?? 'PRODUCTION')));
        if (!in_array($environment, ['TEST', 'PRODUCTION'], true)) {
            throw new InvalidArgumentException('Ambiente de Mercado Pago no válido.');
        }

        $accessToken = trim((string) ($input['access_token'] ?? ''));
        $webhookSecret = trim((string) ($input['webhook_secret'] ?? ''));
        $publicKey = trim((string) ($input['public_key'] ?? ''));
        $active = !empty($input['active']) ? 1 : 0;
        if ($accessToken === '' || $webhookSecret === '') {
            throw new InvalidArgumentException('Access Token y Webhook Secret son obligatorios para guardar una versión.');
        }

        $account = MercadoPago::currentUser($accessToken);
        $accountId = trim((string) ($account['id'] ?? $input['account_id'] ?? ''));
        $accountLabel = trim((string) ($input['account_label'] ?? $account['nickname'] ?? ''));

        $ownTransaction = !$db->inTransaction();
        if ($ownTransaction) {
            $db->beginTransaction();
        }

        try {
            $lock = $db->prepare(
                'SELECT provider FROM payment_gateway_config WHERE provider = ? FOR UPDATE'
            );
            $lock->execute([self::PROVIDER]);
            if (!$lock->fetch()) {
                throw new RuntimeException('La migración de pasarela no está aplicada.');
            }

            $ref = 'cred_' . bin2hex(random_bytes(16));
            $cipher = self::cipher();
            $insert = $db->prepare(
                "INSERT INTO payment_gateway_credentials
                 (provider, credential_ref, environment, public_key, access_token_enc, webhook_secret_enc,
                  account_id, account_label, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([
                self::PROVIDER,
                $ref,
                $environment,
                $publicKey !== '' ? $publicKey : null,
                $cipher->encrypt($accessToken, self::PROVIDER, $ref, 'access_token'),
                $cipher->encrypt($webhookSecret, self::PROVIDER, $ref, 'webhook_secret'),
                $accountId !== '' ? mb_substr($accountId, 0, 120, 'UTF-8') : null,
                $accountLabel !== '' ? mb_substr($accountLabel, 0, 190, 'UTF-8') : null,
                mb_substr($actor, 0, 120, 'UTF-8'),
            ]);

            $credentialId = (int) $db->lastInsertId();
            $update = $db->prepare(
                "UPDATE payment_gateway_config
                 SET configured = 1, active = ?, current_credential_id = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE provider = ?"
            );
            $update->execute([$active, $credentialId, mb_substr($actor, 0, 120, 'UTF-8'), self::PROVIDER]);

            if ($ownTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function hydrate(array $row, bool $active): array
    {
        $ref = trim((string) ($row['credential_ref'] ?? ''));
        $cipher = self::cipher();
        return [
            'provider' => self::PROVIDER,
            'credential_id' => (int) $row['id'],
            'active' => $active,
            'environment' => (string) ($row['environment'] ?? 'PRODUCTION'),
            'public_key' => trim((string) ($row['public_key'] ?? '')),
            'access_token' => $cipher->decrypt((string) $row['access_token_enc'], self::PROVIDER, $ref, 'access_token'),
            'webhook_secret' => $cipher->decrypt((string) $row['webhook_secret_enc'], self::PROVIDER, $ref, 'webhook_secret'),
            'account_id' => trim((string) ($row['account_id'] ?? '')),
            'account_label' => trim((string) ($row['account_label'] ?? '')),
        ];
    }

    private static function cipher(): PaymentCredentialCipher
    {
        $secret = trim((string) env('PAYMENT_GATEWAY_CONFIG_KEY'));
        if ($secret === '') {
            throw new RuntimeException('PAYMENT_GATEWAY_CONFIG_KEY no está configurada.');
        }
        return new PaymentCredentialCipher($secret);
    }
}
