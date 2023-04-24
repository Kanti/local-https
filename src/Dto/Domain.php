<?php

namespace Kanti\LetsencryptClient\Dto;

use Stringable;

class Domain implements Stringable
{
    public function __construct(private string $domainString)
    {
    }

    public function __toString(): string
    {
        return $this->domainString;
    }
}
