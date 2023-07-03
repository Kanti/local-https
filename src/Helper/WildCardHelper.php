<?php

namespace Kanti\LetsencryptClient\Helper;

use Kanti\LetsencryptClient\Certificate\CertificateWithDomainCheck;
use Kanti\LetsencryptClient\Certificate\CertbotHelper;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\WildCardCert;
use Kanti\LetsencryptClient\Exception\CertbotException;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;

class WildCardHelper
{
    public function __construct(
        private CertificateWithDomainCheck $certificateWithDomainCheck,
        private OutputInterface $output,
        private CertbotHelper $certbotHelper
    ) {
    }

    public function createOrUpdate(Domain $domain): ?WildCardCert
    {
        if ($this->valid($domain)) {
            $this->output->writeln(sprintf("<info>%s</info> is valid", $domain));
            return null;
        }

        $this->output->writeln(sprintf("<info>%s</info> needs creation...", $domain));
        return $this->certbotRenew($domain);
    }

    private function valid(Domain $domain): bool
    {
        return $this->certificateWithDomainCheck->checkCertificate('/etc/nginx/certs/' . $domain, $domain);
    }

    private function certbotRenew(Domain $domain): WildCardCert
    {
        return $this->certbotHelper->create($domain);
    }
}
