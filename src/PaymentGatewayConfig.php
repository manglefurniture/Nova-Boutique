<?php
declare(strict_types=1);

final class PaymentGatewayConfig
{
    private const PROVIDER = 'MERCADO_PAGO';

    public static function current(PDO $db, bool $forCheckout = false): ?array
    {
        $lock = $forCheckout && $db->inTransaction() ? ' LOCK IN SHARE MODE' : '';
        $stmt = $db->prepare("SELECT c.provider,c.configured,c.active,c.current_credential_id,v.id,v.credential_ref,v.environment,v.public_key,v.access_token_enc,v.webhook_secret_enc,v.account_id,v.account_label FROM payment_gateway_config c LEFT JOIN payment_gateway_credentials v ON v.provider=c.provider AND v.id=c.current_credential_id WHERE c.provider=? LIMIT 1" . $lock);
        $stmt->execute([self::PROVIDER]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['configured'] !== 1 || empty($row['id'])) {
            return null;
        }
        $ref = (string) $row['credential_ref'];
        $cipher = self::cipher();
        return ['provider'=>self::PROVIDER,'credential_id'=>(int)$row['id'],'active'=>(int)$row['active']===1,'environment'=>(string)$row['environment'],'public_key'=>trim((string)($row['public_key']??'')),'access_token'=>$cipher->decrypt((string)$row['access_token_enc'],self::PROVIDER,$ref,'access_token'),'webhook_secret'=>$cipher->decrypt((string)$row['webhook_secret_enc'],self::PROVIDER,$ref,'webhook_secret'),'account_id'=>trim((string)($row['account_id']??'')),'account_label'=>trim((string)($row['account_label']??''))];
    }

    public static function byId(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("SELECT id,provider,credential_ref,environment,public_key,access_token_enc,webhook_secret_enc FROM payment_gateway_credentials WHERE id=? AND provider=? LIMIT 1");
        $stmt->execute([$id,self::PROVIDER]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $ref = (string) $row['credential_ref'];
        $cipher = self::cipher();
        return ['credential_id'=>(int)$row['id'],'environment'=>(string)$row['environment'],'public_key'=>trim((string)($row['public_key']??'')),'access_token'=>$cipher->decrypt((string)$row['access_token_enc'],self::PROVIDER,$ref,'access_token'),'webhook_secret'=>$cipher->decrypt((string)$row['webhook_secret_enc'],self::PROVIDER,$ref,'webhook_secret')];
    }

    public static function save(PDO $db, array $input, string $actor): void
    {
        $environment = strtoupper(trim((string)($input['environment']??'PRODUCTION')));
        if (!in_array($environment,['TEST','PRODUCTION'],true)) throw new InvalidArgumentException('Ambiente de Mercado Pago no válido.');
        $accessToken = trim((string)($input['access_token']??''));
        $webhookSecret = trim((string)($input['webhook_secret']??''));
        $publicKey = trim((string)($input['public_key']??''));
        $active = !empty($input['active']) ? 1 : 0;
        if ($accessToken === '' || $webhookSecret === '') throw new InvalidArgumentException('Access Token y Webhook Secret son obligatorios para guardar una versión.');
        $own = !$db->inTransaction(); if ($own) $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT provider FROM payment_gateway_config WHERE provider=? FOR UPDATE'); $lock->execute([self::PROVIDER]);
            if (!$lock->fetch()) throw new RuntimeException('La migración de pasarela no está aplicada.');
            $ref='cred_'.bin2hex(random_bytes(16)); $cipher=self::cipher();
            $insert=$db->prepare("INSERT INTO payment_gateway_credentials (provider,credential_ref,environment,public_key,access_token_enc,webhook_secret_enc,account_id,account_label,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $insert->execute([self::PROVIDER,$ref,$environment,$publicKey!==''?$publicKey:null,$cipher->encrypt($accessToken,self::PROVIDER,$ref,'access_token'),$cipher->encrypt($webhookSecret,self::PROVIDER,$ref,'webhook_secret'),trim((string)($input['account_id']??''))?:null,trim((string)($input['account_label']??''))?:null,mb_substr($actor,0,120,'UTF-8')]);
            $credentialId=(int)$db->lastInsertId();
            $update=$db->prepare("UPDATE payment_gateway_config SET configured=1,active=?,current_credential_id=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE provider=?");
            $update->execute([$active,$credentialId,mb_substr($actor,0,120,'UTF-8'),self::PROVIDER]);
            if ($own) $db->commit();
        } catch (Throwable $e) { if ($own && $db->inTransaction()) $db->rollBack(); throw $e; }
    }

    private static function cipher(): PaymentCredentialCipher
    {
        $secret=trim((string)env('PAYMENT_GATEWAY_CONFIG_KEY')); if($secret==='') throw new RuntimeException('PAYMENT_GATEWAY_CONFIG_KEY no está configurada.');
        return new PaymentCredentialCipher($secret);
    }
}
