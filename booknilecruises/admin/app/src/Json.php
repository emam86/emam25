<?php
declare(strict_types=1);

namespace Bnc;

final class Json
{
    public function __construct(public readonly array $data) {}

    public function body(): string
    {
        return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
