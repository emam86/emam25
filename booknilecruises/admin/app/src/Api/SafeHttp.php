<?php
declare(strict_types=1);

namespace Bnc\Api;

/** DNS is validated once and pinned to curl; redirects are never followed. */
final class SafeHttp
{
    public static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        $packed = inet_pton($ip);
        if ($packed === false) return false;
        if (strlen($packed) === 16) {
            // Only ordinary global unicast. Exclude transition/special-purpose and documentation ranges.
            $head = unpack('n', substr($packed, 0, 2))[1];
            $next = unpack('n', substr($packed, 2, 2))[1];
            return ($head & 0xe000) === 0x2000 && $head !== 0x2002
                && !($head === 0x2001 && ($next < 0x0200 || $next === 0x0db8))
                && !($head === 0x3fff && ($next & 0xf000) === 0);
        }
        $number = unpack('N', $packed)[1];
        foreach ([['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 3]] as [$base, $bits]) {
            if (($number >> (32 - $bits)) === (unpack('N', inet_pton($base))[1] >> (32 - $bits))) return false;
        }
        return true;
    }

    /** The optional resolver is for deterministic, network-free guard tests. */
    public static function target(string $url, bool $allowPrivate = false, ?callable $resolver = null): array
    {
        $p = parse_url($url);
        if (!$p || !isset($p['host']) || isset($p['user']) || isset($p['pass']) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || (($p['scheme'] ?? '') !== 'https' && !($allowPrivate && ($p['scheme'] ?? '') === 'http'))) throw new \RuntimeException('blocked: HTTPS required');
        $host = trim($p['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
        elseif ($resolver) $ips = $resolver($host);
        else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = [];
            foreach ($records ?: [] as $record) if (isset($record['ip']) || isset($record['ipv6'])) $ips[] = $record['ip'] ?? $record['ipv6'];
        }
        if (!$ips) throw new \RuntimeException('blocked: unresolved host');
        foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP) || (!$allowPrivate && !self::publicIp($ip))) throw new \RuntimeException('blocked: private address');
        return [$host, (int) ($p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80)), $ips[0]];
    }

    public static function handle(string $url, bool $allowPrivate = false): \CurlHandle
    {
        [$host, $port, $ip] = self::target($url, $allowPrivate);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_PROTOCOLS => $allowPrivate ? CURLPROTO_HTTP | CURLPROTO_HTTPS : CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? "[$ip]" : $ip)], CURLOPT_RETURNTRANSFER => true]);
        return $ch;
    }

    public static function image(string $url): string
    {
        $ch = self::handle($url);
        $tmp = tempnam(sys_get_temp_dir(), 'bnc-image-');
        $file = fopen($tmp, 'wb'); $bytes = 0;
        try {
            curl_setopt_array($ch, [CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use ($file, &$bytes): int {
                $bytes += strlen($chunk);
                if ($bytes > 15 * 1024 * 1024) return 0;
                return fwrite($file, $chunk);
            }]);
            $ok = curl_exec($ch);
            if ($ok === false || (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) throw new \RuntimeException('Image download failed');
            return $tmp;
        } catch (\Throwable $e) { unlink($tmp); throw $e; }
        finally { fclose($file); curl_close($ch); }
    }
}
