<?php
declare(strict_types=1);

namespace Bnc\Api;

use Bnc\{Audit, Db};

final class Keys
{
    public const SCOPES = ['posts.write', 'posts.publish', 'trips.read', 'enquiries.read', 'publish'];

    public static function create(string $name, array $scopes, int $user): string
    {
        if ($name === '' || mb_strlen($name) > 100) Input::invalid('name');
        if (!$scopes || array_diff($scopes, self::SCOPES)) Input::invalid('scopes');
        $token = 'bnc_' . bin2hex(random_bytes(20));
        Db::tx(function () use ($name, $scopes, $user, $token): void {
            $id = Db::insert('api_keys', ['name' => $name, 'scopes' => json_encode(array_values(array_unique($scopes))), 'key_hash' => hash('sha256', $token), 'key_prefix' => substr($token, 0, 8), 'created_by' => $user]);
            Audit::log('create', 'api_key', $id, 'إنشاء مفتاح API', ['name' => $name, 'scopes' => $scopes]);
        });
        return $token;
    }

    public static function authenticate(string $token, string $scope): array
    {
        if (!preg_match('/^bnc_[a-f0-9]{40}$/D', $token)) throw new ApiException(401, 'Invalid token');
        $hash = hash('sha256', $token);
        $key = Db::one('SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1', [$hash]);
        if (!$key || !hash_equals($key['key_hash'], $hash)) throw new ApiException(401, 'Invalid token');
        $key['scopes'] = json_decode($key['scopes'], true);
        RateLimit::take('key:' . $key['id'], [['key:' . $key['id'], 60, 120]]);
        // last_used_at records use; an audit row per request would bury the real history under polling.
        Db::update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$key['id']]);
        self::scope($key, $scope);
        return $key;
    }

    public static function scope(array $key, string $scope): void
    {
        if (!in_array($scope, $key['scopes'], true)) throw new ApiException(403, 'Missing scope: ' . $scope);
    }
}
