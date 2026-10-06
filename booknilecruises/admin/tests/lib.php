<?php
declare(strict_types=1);

// Minimal test harness: test('name', fn () => ...); assert_* helpers; run_tests().

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends Exception {}

function assert_true(mixed $v, string $msg = ''): void
{
    if ($v !== true) throw new AssertionFailed($msg ?: 'expected true, got ' . var_export($v, true));
}

function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (!str_contains($haystack, $needle)) throw new AssertionFailed(($msg ? "$msg: " : '') . "missing '$needle'");
}

function assert_not_contains(string $needle, string $haystack, string $msg = ''): void
{
    if (str_contains($haystack, $needle)) throw new AssertionFailed(($msg ? "$msg: " : '') . "unexpected '$needle'");
}

function run_tests(): int
{
    $failed = 0;
    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        try {
            $fn();
            echo "  ok   $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FAIL $name\n       " . get_class($e) . ': ' . $e->getMessage() . "\n";
            if (!$e instanceof AssertionFailed) echo '       at ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }
    $total = count($GLOBALS['__tests']);
    echo "\n" . ($total - $failed) . "/$total passed\n";
    return $failed ? 1 : 0;
}

/** Cookie-keeping HTTP client for the end-to-end tests. */
final class Browser
{
    private string $jar;
    private string $lastBody = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'bnc-jar');
    }

    /** @return array{status:int,body:string,location:?string} */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST a form; adds the CSRF token from the last page unless $csrf === false. */
    public function post(string $path, array $fields = [], bool $csrf = true): array
    {
        if ($csrf && !isset($fields['_csrf'])) $fields['_csrf'] = $this->csrf();
        return $this->request('POST', $path, $fields);
    }

    /** Upload several files under the photos[] field using multipart/form-data. */
    public function upload(string $path, array $files, array $fields = [], bool $csrf = true): array
    {
        if ($csrf && !isset($fields['_csrf'])) $fields['_csrf'] = $this->csrf();
        foreach ($files as $i => $file) {
            $fields["photos[$i]"] = new CURLFile($file['path'], $file['mime'] ?? 'application/octet-stream', $file['name']);
        }
        return $this->request('POST', $path, $fields, true);
    }

    /** CSRF token for this session: from the last page seen, else from the first page that has a form. */
    public function csrf(): string
    {
        foreach ([null, '/admin/account', '/admin/login', '/admin/install'] as $path) {
            $body = $path === null ? $this->lastBody : $this->get($path)['body'];
            if (preg_match('/name="_csrf" value="([a-f0-9]+)"/', $body, $m)) return $m[1];
        }
        return '';
    }

    private function request(string $method, string $path, array $fields = [], bool $multipart = false): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $headerSize);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
        $this->lastBody = substr($raw, $headerSize);
        return ['status' => $status, 'body' => $this->lastBody, 'location' => $m[1] ?? null, 'headers' => $headers];
    }
}
