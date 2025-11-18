<?php

namespace Kanti\LetsencryptClient\Dto;

use Kanti\LetsencryptClient\Exception\InvalidDomainException;
use Stringable;

final readonly class Domain implements Stringable
{
    public const string VALIDATION_REGEX = '/^([a-z0-9]+[a-z0-9-.]+[a-z0-9]+)$/';

    public function __construct(private string $domainString)
    {
        if (!preg_match(self::VALIDATION_REGEX, $this->domainString)) {
            throw new InvalidDomainException(sprintf('%s is not a valid domain', $this->domainString));
        }
    }

    public function __toString(): string
    {
        return $this->domainString;
    }
}
