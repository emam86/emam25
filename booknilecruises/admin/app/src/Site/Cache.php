<?php
declare(strict_types=1);
namespace Bnc\Site;

use Bnc\Config;
use Bnc\Db;
use Bnc\Settings;

/** File cache outside the document root; version and publication deadline are checked on every hit. */
final class Cache
{
    private string $directory;
    public function __construct()
    {
        $this->directory=rtrim((string)(Config::get('site_cache_dir') ?: BNC_APP.'/cache/site'), '/');
    }

    public static function state(): array
    {
        // Fast path for ordinary visits: two plain reads, no lock. Only when a scheduled post's
        // time has passed (or nothing is recorded yet) does one request take the lock and advance it.
        $rows = array_column(Db::all("SELECT `key`, `value` FROM settings WHERE `key` IN ('site_next_publication', 'content_version')"), 'value', 'key');
        $stored = $rows['site_next_publication'] ?? null;
        if ($stored !== null && ($stored === '' || $stored > date('Y-m-d H:i:s'))) {
            return ['version' => (string) ($rows['content_version'] ?? '0'), 'deadline' => $stored === '' ? null : strtotime($stored), 'year' => date('Y'), 'noindex' => App::noindex()];
        }
        Db::run("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('site_next_publication', '')");
        return Db::tx(function (): array {
            $stored=Db::value("SELECT `value` FROM settings WHERE `key` = 'site_next_publication' FOR UPDATE");
            $now=date('Y-m-d H:i:s');
            if ($stored && $stored <= $now) {
                Db::run("INSERT INTO settings (`key`, `value`) VALUES ('content_version', '1') ON DUPLICATE KEY UPDATE `value` = CAST(COALESCE(`value`, '0') AS UNSIGNED) + 1");
            }
            $next=Db::value("SELECT MIN(published_at) FROM posts WHERE status = 'published' AND published_at > ?",[$now]);
            if ((string)$stored !== (string)$next) Settings::set('site_next_publication', $next ?: '');
            $version=(string)(Db::value("SELECT `value` FROM settings WHERE `key` = 'content_version'") ?? '0');
            Settings::reset();
            return ['version'=>$version,'deadline'=>$next ? strtotime($next) : null, 'year'=>date('Y'), 'noindex'=>App::noindex()];
        });
    }

    private function file(string $path): string { return $this->directory.'/'.hash('sha256',$path).'.json'; }

    public function get(string $path, array $state): ?array
    {
        $file=$this->file($path);
        if (!is_file($file)) return null;
        $data=json_decode((string)file_get_contents($file),true);
        if (!is_array($data) || ($data['state'] ?? null)!==$state || (isset($data['state']['deadline']) && time()>=$data['state']['deadline'])) return null;
        return $data['response'] ?? null;
    }

    public function put(string $path, array $state, array $response): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory,0700,true) && !is_dir($this->directory)) { error_log('[bnc] Unable to create site cache'); return; }
        $temporary=@tempnam($this->directory,'.page-');
        if (!$temporary) return;
        $data=json_encode(['state'=>$state,'response'=>$response],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if (@file_put_contents($temporary,$data,LOCK_EX)!==false) { @chmod($temporary,0600); if (!@rename($temporary,$this->file($path))) @unlink($temporary); }
        else @unlink($temporary);
    }
}
