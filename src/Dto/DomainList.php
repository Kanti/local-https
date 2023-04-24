<?php

namespace Kanti\LetsencryptClient\Dto;

use Stringable;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

use function Clue\StreamFilter\fun;

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
        $this->sort();
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
        usort($this->domains, static fn($a, $b): int => strlen($a) <=> strlen($b));
        usort($this->domains, static fn($a, $b): int => substr_count($a, '.') <=> substr_count($b, '.'));
        return $this;
    }

    public function __toString(): string
    {
        return implode(',', $this->domains);
    }

    /**
     * @param array<string> $domainStrings
     */
    public static function fromArray(array $domainStrings): DomainList
    {
        $domains = array_map(static fn($domain): Domain => new Domain($domain), $domainStrings);
        return (new self(...$domains))->sort();
    }

    public function getWildCardDomainList(): DomainList
    {
        $result = [];

        foreach ($this as $domain) {
            $domainParts = explode('.', (string)$domain);
            $currentDomainLevel = array_pop($domainParts); //pop top 2 levels (andersundsehr.com should not be getting a wildcard)
            $pop = array_pop($domainParts);
            $currentDomainLevel = $pop . '.' . $currentDomainLevel;

            do {
                $pop = array_pop($domainParts);
                $currentDomainLevel = $pop . '.' . $currentDomainLevel;
                $result[] = $currentDomainLevel;
            } while (count($domainParts) > 1);
        }

        return self::fromArray($result);
    }
}
