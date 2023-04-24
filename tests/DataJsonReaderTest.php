<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Exception;
use Generator;
use Kanti\LetsencryptClient\Application;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\DomainList;
use Kanti\LetsencryptClient\Helper\DataJsonReader;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class DataJsonReaderTest extends TestCase
{
    /**
     * @dataProvider getProjectDomainsProvider
     */
    public function testGetProjectDomains(string $httpsMainDomain, string $dataFilePath, DomainList $expectedList): void
    {
        self::setupApplicationContainer();
        $dataJsonReader = new DataJsonReader(new NullOutput());
        $result = $dataJsonReader->getDomainList(new Domain($httpsMainDomain), $dataFilePath);
        self::assertEquals($expectedList, $result);
    }

    public function getProjectDomainsProvider(): Generator
    {
        yield ['kanti.dev', 'tests/fixtures/domainList.json', DomainList::fromArray([
            'kanti.dev',
            'www.kanti.dev',
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
        ])];
        yield ['kanti.kanti.dev', 'tests/fixtures/domainList.json', DomainList::fromArray([
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
        ])];
        yield ['kanti.kanti.dev', 'tests/fixtures/domainList2.json', DomainList::fromArray([
            'kanti.kanti.dev',
            'pro1.kanti.kanti.dev',
            'cn.pro1.kanti.kanti.dev',
            'pro2.kanti.kanti.dev',
            'solr.pro2.kanti.kanti.dev',
        ])];
    }

    public static function setupApplicationContainer(): void
    {
        Application::$container = new class implements ContainerInterface {
            public function get(string $id)
            {
                return match ($id) {
                    OutputInterface::class => new NullOutput(),
                    default => throw new Exception('not mocked'),
                };
            }

            public function has(string $id)
            {
                try {
                    $this->get($id);
                } catch (Exception) {
                    return false;
                }

                return true;
            }
        };
    }
}
