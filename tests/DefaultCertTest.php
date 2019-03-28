<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient\Tests;

use Kanti\LetsencryptClient\Certificate\SelfSignedCertificate;
use PHPUnit\Framework\TestCase;

class DefaultCertTest extends TestCase
{
    public function testCreateIfNotExists()
    {
        $defaultCert = new SelfSignedCertificate('tests/fixtures/certs/nothingThere');
        $this->assertTrue($defaultCert->createIfNotExists());
        `rm -f tests/fixtures/certs/nothingThere.*`;
    }
}
