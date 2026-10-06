<?php
declare(strict_types=1);

namespace Bnc\Publish;

use Bnc\Config;

final class CurlGitHubClient implements GitHubClient
{
    public function dispatch(int $jobId): array
    {
        $repo = (string) Config::get('github.repo');
        $workflow = (string) Config::get('github.workflow');
        $body = json_encode(['ref' => Config::get('github.ref', 'main'), 'inputs' => ['job_id' => (string) $jobId]], JSON_THROW_ON_ERROR);
        $url = 'https://api.github.com/repos/' . $repo . '/actions/workflows/' . rawurlencode($workflow) . '/dispatches';
        $fake = Config::get('github.fake');
        if (is_string($fake) && $fake !== '') {
            // The test recorder deliberately excludes the Authorization header.
            file_put_contents($fake, json_encode(['url' => $url, 'body' => json_decode($body, true)], JSON_THROW_ON_ERROR));
            return ['status' => (int) Config::get('github.fake_status', 204), 'message' => 'Fake GitHub response'];
        }
        $token = (string) Config::get('github.token');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: bnc-admin', 'Content-Type: application/json']]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        $message = is_array($decoded) && is_string($decoded['message'] ?? null) ? $decoded['message'] : ($status ? 'GitHub returned HTTP ' . $status : 'GitHub request failed or timed out');
        return ['status' => $status, 'message' => mb_substr(str_replace($token, '[redacted]', $message), 0, 255)];
    }
}
