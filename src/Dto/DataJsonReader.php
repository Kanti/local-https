<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Dto;

use Symfony\Component\Console\Output\OutputInterface;

use function Safe\file_get_contents;
use function Safe\json_decode;

final class DataJsonReader
{
    public function __construct(private OutputInterface $output, private string $httpsMainDomain, private string $dataFilePath)
    {
    }

    /**
     * @return array<DomainList>
     */
    public function getDomainLists(): array
    {
        $string = file_get_contents($this->dataFilePath);
        $data = json_decode($string, true, 512, JSON_THROW_ON_ERROR);
        return $this->convertToDomainLists($data);
    }

    /**
     * @param array<int, array<int, string>|null> $data
     * @return array<DomainList>
     */
    private function convertToDomainLists(array $data): array
    {
        $domainLists = [];
        foreach (array_filter($data) as $domainArray) {
            $result = [];
            foreach (array_filter($domainArray) as $domain) {
                foreach (array_filter(explode(',', $domain)) as $singleDomain) {
                    if ($this->isValidDomain($singleDomain)) {
                        $result[] = new Domain($singleDomain);
                    } else {
                        $this->output->writeln(sprintf("<comment>Warning</comment> <options=bold>%s</> is not a valid domain", $singleDomain));
                    }
                }
            }

            if ($result) {
                $domainLists[] = new DomainList(...$result);
            }
        }

        return $domainLists;
    }

    /**
     * Domains are invalid if they start with ~
     * ... then they are Regular Expressions.
     */
    private function isValidDomain(string $domains): bool
    {
        if (str_starts_with($domains, '~')) {
            return false;
        }

        return str_ends_with($domains, $this->httpsMainDomain);
    }
}
