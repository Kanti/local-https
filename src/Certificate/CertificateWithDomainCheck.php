<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use DateTimeImmutable;
use function Safe\sprintf;

final class CertificateWithDomainCheck
{
    /**@var string */
    private $certPathAndName;
    /** @var array<int, string> */
    private $domains;
    /** @var array<int, string> */
    private $invalidDomains = [];

    public function __construct(string $certPathAndName, array $domains)
    {
        $this->certPathAndName = $certPathAndName;
        $this->domains = $domains;
    }

    public function areAllDomainsValid(): bool
    {
        if (!file_exists($this->certPathAndName . '.crt')) {
            echo sprintf('file not found %s' . PHP_EOL, $this->certPathAndName . '.crt');
            $this->invalidDomains = $this->domains;
            return false;
        }
        if (!file_exists($this->certPathAndName . '.key')) {
            echo sprintf('file not found %s' . PHP_EOL, $this->certPathAndName . '.key');
            $this->invalidDomains = $this->domains;
            return false;
        }
        return $this->isValidForAllDomains();
    }

    private function isValidForAllDomains(): bool
    {
        $result = shell_exec(sprintf('openssl x509 -noout -subject -in %s', $this->certPathAndName . '.crt'));
        if (!$result) {
            echo sprintf('Cert %s has no subject' . PHP_EOL, $this->certPathAndName);
            return false;
        }
        $acceptedDomain = trim(str_replace('subject=CN =', '', $result));
        $acceptedDomains = [];

        $result = shell_exec($x = sprintf('openssl x509 -noout -ext subjectAltName -in %s', $this->certPathAndName . '.crt'));
        if ($result) {
            $lines = explode(PHP_EOL, $result);
            $dnsEntries = explode(',', $lines[1]);
            $dnsEntries = array_map('\trim', $dnsEntries);
            $acceptedDomains = str_replace('DNS:', '', $dnsEntries);
        }
        $acceptedDomains[] = $acceptedDomain;
        foreach ($this->domains as $domain) {
            if (!in_array($domain, $acceptedDomains, true)) {
                $this->invalidDomains[] = $domain;
                echo sprintf('Domain %s is not in the list of Acceptable Domains: %s' . PHP_EOL, $domain, implode(',', $acceptedDomains));
            }
        }
        $result = shell_exec(sprintf('openssl x509 -noout -enddate -in "%s" | cut -d "=" -f 2', $this->certPathAndName . '.crt'));
        $certDate = new DateTimeImmutable($result);
        if ($certDate < new DateTimeImmutable('+1 week')) {
            echo sprintf('Certificate is not valid in 1 week from now' . PHP_EOL);
            $this->invalidDomains = $this->domains;
        }
        return count($this->invalidDomains) === 0;
    }

    public function getInvalidDomains(): array
    {
        return $this->invalidDomains;
    }
}
