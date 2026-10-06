<?php
declare(strict_types=1);

namespace Bnc\Seo;

interface IndexNowClient
{
    public function submit(array $body): int;
}
