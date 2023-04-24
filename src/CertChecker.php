<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Kanti\LetsencryptClient\Certificate\CertificateWithDomainCheck;
use Kanti\LetsencryptClient\Dto\LetsEncryptCertificate;
use Kanti\LetsencryptClient\Certificate\LetsEncryptCertificateFactory;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\DomainList;
use Symfony\Component\Console\Output\OutputInterface;

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
    private DomainList $invalidDomains;

    public function __construct(
        private OutputInterface $output,
        private LetsEncryptCertificateFactory $letsEncryptCertificateFactory,
        private CertificateWithDomainCheck $certificateWithDomainCheck,
    ) {
    }

    public function createIfNotExists(DomainList $domainList): bool
    {
        $this->output->writeln(sprintf('testing domains: <info>%s</info>', $domainList));
        if (!$this->haveAllDomainsValidCertificates($domainList)) {
            $this->output->writeln('not all are valid...');
            $certs = $this->letsEncryptCertificateFactory->fromDomainList($this->invalidDomains, $this->output);
            foreach ($certs as $cert) {
                $this->copyCertificateToDomains($cert);
            }

            return true;
        }

        return false;
    }

    private function haveAllDomainsValidCertificates(DomainList $domainList): bool
    {
        $this->invalidDomains = new DomainList();
        foreach ($domainList as $domain) {
            if (!$this->certificateWithDomainCheck->checkCertificate('/etc/nginx/certs/' . $domain, $domain)) {
                $this->invalidDomains->add($domain);
            }
        }

        return count($this->invalidDomains) === 0;
    }

    private function copyCertificateToDomains(LetsEncryptCertificate $cert): void
    {
        $crtPath = $cert->getCrtPath();
        $keyPath = $cert->getKeyPath();
        foreach ($cert->getDomainList() as $domain) {
            copy($crtPath, '/etc/nginx/certs/' . $domain . '.crt');
            copy($keyPath, '/etc/nginx/certs/' . $domain . '.key');
        }
    }
}
