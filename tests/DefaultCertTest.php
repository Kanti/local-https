<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Kanti\LetsencryptClient\Certificate\SelfSignedCertificate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class DefaultCertTest extends TestCase
{
    public function testCreateIfNotExists(): void
    {
        $defaultCert = new SelfSignedCertificate(new NullOutput());
        $this->assertTrue($defaultCert->createIfNotExists('tests/fixtures/certs/nothingThere'));
        `rm -f tests/fixtures/certs/nothingThere.*`;
    }
}
