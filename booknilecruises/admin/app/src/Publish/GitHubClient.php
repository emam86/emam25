<?php
declare(strict_types=1);

namespace Bnc\Publish;

interface GitHubClient
{
    /** @return array{status:int,message:string} */
    public function dispatch(int $jobId): array;
}
