<?php

namespace Kanti\LetsencryptClient\Dto;

use Stringable;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<Domain>
 */
class DomainList implements IteratorAggregate, Countable, Stringable
{
    /**
     * @var array<Domain>
     */
    private array $domains = [];

    public function __construct(Domain ...$domains)
    {
        $this->domains = array_unique($domains);
    }

    public function add(Domain $domain): void
    {
        $this->domains[] = $domain;
    }

    /**
     * @return Traversable<Domain>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->domains);
    }

    /**
     * @return array<Domain>
     */
    public function toArray(): array
    {
        return $this->domains;
    }

    public function count(): int
    {
        return count($this->domains);
    }

    public function sort(): self
    {
        sort($this->domains);
        return $this;
    }

    public function __toString(): string
    {
        return implode(',', $this->domains);
    }

    public static function fromCommaString(string $commaString): DomainList
    {
        $domainStrings = array_filter(explode(',', $commaString));
        $domains = array_map(static fn($domain): Domain => new Domain($domain), $domainStrings);
        return (new self(...$domains))->sort();
    }
}
