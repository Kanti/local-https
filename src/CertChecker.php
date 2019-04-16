<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Kanti\LetsencryptClient\Certificate\LetsEncryptCertificate;
use Kanti\LetsencryptClient\Certificate\CertificateWithDomainCheck;

/**
 * combine all HTTPS domains per docker-compose.yml
 * -> remove all that are not in HTTPS_MAIN_DOMAIN
 * -> is there a valid cert for all the domains?
 *  -NO> create one
 *  -NO> save them in the right place
 *  -NO> reload nginx
 * -> hook done.
 */
final class CertChecker
{
    /** @var array<int, string> */
    private $invalidDomains = [];

    public function createIfNotExists(array $domains): bool
    {
        if (!$this->haveAllDomainsValidCertificates($domains)) {
            $certs = $this->createCertificatesWithDomains($this->invalidDomains);
            foreach ($certs as $cert) {
                $this->copyCertificateToDomains($cert, $cert->getDomains());
            }
            return true;
        }
        return false;
    }

    private function haveAllDomainsValidCertificates(array $domains): bool
    {
        $this->invalidDomains = [];
        foreach ($domains as $domain) {
            $cert = new CertificateWithDomainCheck('/etc/nginx/certs/' . $domain, [$domain]);
            if (!$cert->areAllDomainsValid()) {
                $domains = $cert->getInvalidDomains();
                array_push($this->invalidDomains, ...$domains);
            }
        }
        return count($this->invalidDomains) === 0;
    }

    private function createCertificatesWithDomains(array $domains): array
    {
        return LetsEncryptCertificate::fromDomainList($domains);
    }

    private function copyCertificateToDomains(LetsEncryptCertificate $cert, $domains): void
    {
        $crtPath = $cert->getCrtPath();
        $keyPath = $cert->getKeyPath();
        foreach ($domains as $domain) {
            copy($crtPath, '/etc/nginx/certs/' . $domain . '.crt');
            copy($keyPath, '/etc/nginx/certs/' . $domain . '.key');
        }
    }
}
