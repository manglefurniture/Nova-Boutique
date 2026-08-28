<?php
declare(strict_types=1);

final class AuditLog
{
    public static function record(
        PDO $db,
        string $actor,
        string $action,
        string $entityType,
        ?string $entityId = null,
        array $details = []
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO audit_events (actor, action, entity_type, entity_id, details_json)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            mb_substr(trim($actor) !== '' ? trim($actor) : 'system', 0, 190, 'UTF-8'),
            mb_substr($action, 0, 80, 'UTF-8'),
            mb_substr($entityType, 0, 80, 'UTF-8'),
            $entityId !== null ? mb_substr($entityId, 0, 120, 'UTF-8') : null,
            $details !== []
                ? json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
        ]);
    }
}
