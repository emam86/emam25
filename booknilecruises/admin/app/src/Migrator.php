<?php
declare(strict_types=1);

namespace Bnc;

/** Applies app/migrations/NNN_name.sql files once each, in order. */
final class Migrator
{
    /** @return list<string> names applied in this run */
    public static function run(): array
    {
        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = array_column(Db::all('SELECT name FROM schema_migrations'), 'name');
        $applied = [];
        foreach (self::files() as $name => $file) {
            if (in_array($name, $done, true)) continue;
            // MySQL DDL commits implicitly, so each statement stands alone; a failed file stops the run.
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                Db::pdo()->exec($sql);
            }
            Db::insert('schema_migrations', ['name' => $name]);
            $applied[] = $name;
        }
        Roles::seedPresets();
        return $applied;
    }

    /** @return list<string> */
    public static function pending(): array
    {
        try {
            $done = array_column(Db::all('SELECT name FROM schema_migrations'), 'name');
        } catch (\PDOException) {
            $done = [];
        }
        return array_values(array_diff(array_keys(self::files()), $done));
    }

    /** @return array<string,string> */
    private static function files(): array
    {
        $files = glob(BNC_APP . '/migrations/*.sql') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $f) $out[basename($f, '.sql')] = $f;
        return $out;
    }

    /** Split on semicolons at line ends, skipping -- comments. */
    public static function statements(string $sql): array
    {
        $lines = array_filter(explode("\n", $sql), fn ($l) => !str_starts_with(ltrim($l), '--'));
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }
}
