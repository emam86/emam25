<?php
declare(strict_types=1);

namespace Bnc\Publish;

use Bnc\{Audit, Config, Db};
use Bnc\Api\{ApiException, Input};
use Bnc\Webhooks\Delivery;

final class Publisher
{
    public static function configured(): bool
    {
        return (string) Config::get('github.token', '') !== '' && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#D', (string) Config::get('github.repo', '')) && preg_match('/^[A-Za-z0-9_.-]+$/D', (string) Config::get('github.workflow', ''));
    }

    public static function start(string $actor, ?GitHubClient $client = null): int
    {
        if (!self::configured()) throw new ApiException(422, 'Publishing is not configured');
        $id = Db::tx(function () use ($actor): int {
            Db::run("INSERT IGNORE INTO api_locks (name) VALUES ('publish')");
            Db::one("SELECT name FROM api_locks WHERE name = 'publish' FOR UPDATE");
            if (Db::value("SELECT id FROM publish_jobs WHERE status IN ('queued','running') AND created_at > ? LIMIT 1", [date('Y-m-d H:i:s', time() - 1200)])) throw new ApiException(409, 'A publish job is already in progress');
            $id = Db::insert('publish_jobs', ['status' => 'queued', 'triggered_by' => $actor]);
            Audit::log('create', 'publish_job', $id, 'طلب نشر الموقع', null, str_starts_with($actor, 'api:') ? $actor : null);
            return $id;
        });
        try { $result = ($client ?? new CurlGitHubClient())->dispatch($id); }
        catch (\Throwable) { $result = ['status' => 0, 'message' => 'GitHub request failed']; }
        if ($result['status'] !== 204) {
            Db::tx(function () use ($id, $result, $actor): void {
                Db::update('publish_jobs', ['status' => 'failed', 'message' => mb_substr($result['message'], 0, 255), 'finished_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
                Audit::log('update', 'publish_job', $id, 'فشل طلب نشر الموقع', ['http_status' => $result['status']], str_starts_with($actor, 'api:') ? $actor : null);
            });
            throw new ApiException(502, 'GitHub dispatch failed; see publish job history');
        }
        return $id;
    }

    public static function status(array $d, ?\Bnc\Seo\IndexNowClient $indexNow = null): void
    {
        if (!is_int($d['job_id'] ?? null) || $d['job_id'] < 1) Input::invalid('job_id');
        $status = Input::text($d, 'status', 20, true);
        if (!in_array($status, ['running', 'succeeded', 'failed'], true)) Input::invalid('status');
        $url = Input::text($d, 'run_url', 500, true);
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') !== 'github.com' || isset($p['user']) || isset($p['pass']) || isset($p['port']) || preg_match('/[\x00-\x20\\\\]/', $url)) Input::invalid('run_url');
        $message = Input::text($d, 'message', 255);
        $changed = Db::tx(function () use ($d, $status, $url, $message): bool {
            $old = Db::one('SELECT * FROM publish_jobs WHERE id = ? FOR UPDATE', [$d['job_id']]);
            if (!$old) throw new ApiException(404, 'Publish job not found');
            // Workflow retries are idempotent; a delayed running callback cannot reopen a finished job.
            if (in_array($old['status'], ['succeeded', 'failed'], true)) {
                if ($old['status'] !== $status) throw new ApiException(409, 'Publish job has already finished');
                return false;
            }
            Db::update('publish_jobs', ['status' => $status, 'run_url' => $url, 'message' => $message, 'finished_at' => $status === 'running' ? null : date('Y-m-d H:i:s')], 'id = ?', [$d['job_id']]);
            Audit::log('update', 'publish_job', $d['job_id'], 'تحديث حالة نشر الموقع', ['status' => $status], 'publish-workflow');
            return true;
        });
        if ($changed && $status === 'succeeded') \Bnc\Seo\IndexNow::submit($d['job_id'], $indexNow);
        if ($changed && $status === 'succeeded') Delivery::fire('site.published', ['job_id' => $d['job_id'], 'run_url' => $url, 'message' => $message], 'publish-workflow');
    }
}
