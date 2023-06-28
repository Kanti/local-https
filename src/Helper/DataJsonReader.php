<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Helper;

use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\DomainList;
use Symfony\Component\Console\Output\OutputInterface;

use function Safe\file_get_contents;
use function Safe\json_decode;

final class DataJsonReader
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function getDomainList(Domain $httpsMainDomain, string $dataFilePath): DomainList
    {
        $string = file_get_contents($dataFilePath);
        $data = json_decode($string, true, 512, JSON_THROW_ON_ERROR);
        return $this->convertToDomainLists($data, $httpsMainDomain);
    }

    /**
     * @param array<int, string|null> $data
     */
    private function convertToDomainLists(array $data, Domain $httpsMainDomain): DomainList
    {
        $result = [];
        foreach (array_filter($data) as $virtualHosts) {
            foreach (array_filter(explode(',', $virtualHosts)) as $singleDomain) {
                if ($this->isValidDomain($singleDomain, $httpsMainDomain)) {
                    $result[] = new Domain($singleDomain);
                } else {
                    $this->output->writeln(sprintf("<comment>Warning</comment> <options=bold>%s</> is not a valid domain", $singleDomain));
                }
            }
        }

        return new DomainList(...$result);
    }

    /**
     * Domains are invalid if they start with ~
     * ... then they are Regular Expressions.
     */
    private function isValidDomain(string $domains, Domain $httpsMainDomain): bool
    {
        if (str_starts_with($domains, '~')) {
            return false;
        }

        return str_ends_with($domains, (string)$httpsMainDomain);
    }
}
