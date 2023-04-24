<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Generator;
use Kanti\LetsencryptClient\Application;
use Kanti\LetsencryptClient\Helper\DataJsonReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class DataJsonReaderTest extends TestCase
{
    /**
     * @dataProvider getProjectDomainsProvider
     * @param array<string> $expectedList
     */
    public function testGetProjectDomains(string $httpsMainDomain, string $dataFilePath, array $expectedList): void
    {
        DefaultCertTest::setupApplicationContainer();
        $dataJsonReader = new DataJsonReader(new NullOutput());
        $result = $dataJsonReader->getDomainLists($httpsMainDomain, $dataFilePath);
        self::assertEquals($expectedList, $result);
    }

    public function getProjectDomainsProvider(): Generator
    {
        yield ['kanti.dev', 'tests/fixtures/domainList.json', [
            'kanti.dev',
            'www.kanti.dev',
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
        ]];
        yield ['kanti.kanti.dev', 'tests/fixtures/domainList.json', [
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
        ]];
        yield ['kanti.kanti.dev', 'tests/fixtures/domainList2.json', [
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
            'pro2.kanti.kanti.dev',
            'solr.pro2.kanti.kanti.dev',
        ]];
    }
}
