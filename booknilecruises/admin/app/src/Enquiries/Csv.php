<?php
declare(strict_types=1);

namespace Bnc\Enquiries;

final class Csv
{
    public function __construct(public readonly string $body) {}
}
