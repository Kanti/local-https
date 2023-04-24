<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Exception;
use Kanti\LetsencryptClient\Application;
use Kanti\LetsencryptClient\Certificate\SelfSignedCertificate;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultCertTest extends TestCase
{
    public function testCreateIfNotExists(): void
    {
        DefaultCertTest::setupApplicationContainer();
        $defaultCert = new SelfSignedCertificate(new NullOutput());
        self::assertTrue($defaultCert->createIfNotExists('tests/fixtures/certs/nothingThere'));
        `rm -f tests/fixtures/certs/nothingThere.*`;
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
