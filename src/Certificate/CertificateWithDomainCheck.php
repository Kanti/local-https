<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use DateTimeImmutable;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class CertificateWithDomainCheck
{
    public function __construct(private OutputInterface $output)
    {
    }

    public function checkCertificate(string $certPathAndName, Domain $domain): bool
    {
        $this->output->writeln(sprintf('Testing Certificate for <fg=magenta>%s</>', $domain));

        if (!file_exists($certPathAndName . '.crt')) {
            $this->output->writeln(sprintf('file not found %s' . PHP_EOL, $certPathAndName . '.crt'));
            return false;
        }

        if (!file_exists($certPathAndName . '.key')) {
            $this->output->writeln(sprintf('file not found %s', $certPathAndName . '.crt'));
            return false;
        }

        return $this->isValidForAllDomains($certPathAndName, $domain);
    }

    private function isValidForAllDomains(string $certPathAndName, Domain $domain): bool
    {
        $result = ProcessUtility::runProcess(sprintf('openssl x509 -noout -subject -in %s', $certPathAndName . '.crt'))->getOutput();
        if (!$result) {
            $this->output->writeln(sprintf('Cert %s has no subject', $certPathAndName));
            return false;
        }

        $acceptedDomain = trim(str_replace('subject=CN =', '', $result));
        $acceptedDomains = [];

        $result = ProcessUtility::runProcess(sprintf('openssl x509 -noout -ext subjectAltName -in %s', $certPathAndName . '.crt'))->getOutput();
        if ($result) {
            $lines = explode(PHP_EOL, $result);
            $dnsEntries = explode(',', $lines[1]);
            $dnsEntries = array_map('\trim', $dnsEntries);
            /** @var string[] $acceptedDomains */
            $acceptedDomains = str_replace('DNS:', '', $dnsEntries);
            assert(is_array($acceptedDomains));
        }

        $acceptedDomains[] = $acceptedDomain;
        if (!in_array((string)$domain, $acceptedDomains, true)) {
            $this->output->writeln(sprintf('Domain %s is not in the list of Acceptable Domains: %s', (string)$domain, implode(',', $acceptedDomains)));
            return false;
        }

        $result = ProcessUtility::runProcess(sprintf('openssl x509 -noout -enddate -in "%s" | cut -d "=" -f 2', $certPathAndName . '.crt'))->getOutput();
        assert(is_string($result));
        $certDate = new DateTimeImmutable($result);
        if ($certDate < new DateTimeImmutable('+1 week')) {
            $this->output->writeln('Certificate is not valid in 1 week from now');
            return false;
        }

        return true;
    }
}
