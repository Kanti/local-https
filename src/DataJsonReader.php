<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use function Safe\file_get_contents;
use function Safe\json_decode;

final class DataJsonReader
{
    /** @var string */
    private $httpsMainDomain;
    /** @var string */
    private $dataFilePath;

    public function __construct(string $httpsMainDomain, string $dataFilePath)
    {
        $this->httpsMainDomain = $httpsMainDomain;
        $this->dataFilePath = $dataFilePath;
    }

    public function getDomains(): array
    {
        $string = file_get_contents($this->dataFilePath);
        $domains = json_decode($string, true);
        return $this->filterDomains($domains);
    }

    /**
     * @param array<int, string> $domainArray
     * @return array
     */
    private function filterDomains(array $domainArray): array
    {
        $result = [];
        foreach (array_filter($domainArray) as $domain) {
            if (strpos($domain, ',') !== false) {
                array_push($result, null, ...$this->filterDomains(explode(',', $domain)));
            } elseif ($this->isValidDomain($domain)) {
                $result[] = $domain;
            }
        }
        return array_values(array_filter(array_unique($result)));
    }

    /**
     * Domains are invalid if they start with ~
     * ... then they are Regular Expressions.
     *
     * @param string $domains
     * @return bool
     */
    private function isValidDomain(string $domains): bool
    {
        if (StringUtility::startsWith($domains, '~')) {
            return false;
        }
        if (!StringUtility::endsWith($domains, $this->httpsMainDomain)) {
            return false;
        }
        return true;
    }
}
