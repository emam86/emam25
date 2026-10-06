<?php
declare(strict_types=1);

namespace Bnc;

final class Audit
{
    /**
     * Record who did what. $actor overrides the signed-in user (e.g. "api:n8n").
     */
    public static function log(string $action, string $entity, int|string|null $entityId = null, string $summary = '', ?array $details = null, ?string $actor = null): void
    {
        $user = $actor === null ? Auth::user() : null;
        Db::insert('audit_log', [
            'user_id' => $user['id'] ?? null,
            'actor' => $actor ?? ($user['email'] ?? 'system'),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'summary' => mb_substr($summary, 0, 255),
            'details' => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => PHP_SAPI === 'cli' ? null : Request::ip(),
        ]);
    }
}
