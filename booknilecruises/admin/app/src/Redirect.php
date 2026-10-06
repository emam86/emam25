<?php
declare(strict_types=1);

namespace Bnc;

/** Returned by a controller to send the browser elsewhere inside the panel. */
final class Redirect
{
    public function __construct(public readonly string $path, public readonly array $query = [])
    {
    }

    public function url(): string
    {
        return url($this->path, $this->query);
    }
}
