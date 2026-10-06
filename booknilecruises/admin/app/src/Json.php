<?php
declare(strict_types=1);

namespace Bnc;

final class Json
{
    public function __construct(public readonly array $data, public readonly int $status = 200, private readonly ?string $rawBody = null) {}

    public function body(): string
    {
        return $this->rawBody ?? json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
