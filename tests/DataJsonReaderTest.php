<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Generator;
use Kanti\LetsencryptClient\DataJsonReader;
use PHPUnit\Framework\TestCase;

class DataJsonReaderTest extends TestCase
{
    /**
     * @dataProvider getProjectDomainsProvider
     * @param string $httpsMainDomain
     * @param string $dataFilePath
     * @param array $expectedList
     */
    public function testGetProjectDomains(string $httpsMainDomain, string $dataFilePath, array $expectedList)
    {
        $dataJsonReader = new DataJsonReader($httpsMainDomain, $dataFilePath);
        $result = $dataJsonReader->getDomains();
        $this->assertEquals($expectedList, $result);
    }

    public function getProjectDomainsProvider(): ?Generator
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
