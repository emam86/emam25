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
        if (in_array($entity, ['trip', 'term', 'post', 'media', 'redirect', 'seo_override', 'seo', 'settings', 'import'], true) || ($action === 'import' && $entity === 'site')) {
            Db::run("INSERT INTO settings (`key`, `value`) VALUES ('content_version', '1') ON DUPLICATE KEY UPDATE `value` = CAST(COALESCE(`value`, '0') AS UNSIGNED) + 1");
            // A save may add or move a scheduled post: forget the stored next go-live time so the
            // public site recalculates it on the next visit (Site\Cache::state).
            Db::run("DELETE FROM settings WHERE `key` = 'site_next_publication'");
            Settings::reset();
            if (preg_match('/^[a-f0-9]{32}$/D', (string) Settings::get('indexnow_key', ''))) {
                $paths = \Bnc\Seo\IndexNow::contentPaths($entity, $entityId, $details);
                Db::afterCommit(static fn () => \Bnc\Seo\IndexNow::submitPaths($paths));
            }
        }
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
