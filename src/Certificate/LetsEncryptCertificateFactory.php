<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use InvalidArgumentException;
use Kanti\LetsencryptClient\Dto\DomainList;
use Kanti\LetsencryptClient\Dto\LetsEncryptCertificate;
use Kanti\LetsencryptClient\Exception\CertbotException;
use Kanti\LetsencryptClient\NginxProxy;
use Kanti\LetsencryptClient\SlackNotification;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function in_array;
use function Sentry\captureMessage;
use function sprintf;

final class LetsEncryptCertificateFactory
{
    public function __construct(private OutputInterface $output, private SlackNotification $slackNotification, private NginxProxy $nginxProxy)
    {
    }

    public function create(DomainList $domainList): LetsEncryptCertificate
    {
        $domainList->sort();

        $cmd = sprintf(
            'certbot certonly -n --dns-cloudflare --dns-cloudflare-credentials var/cloudflare.ini -d %s --register-unsafely-without-email --agree-tos',
            $domainList
        );
        try {
            $result = ProcessUtility::runProcess($cmd)->getOutput();
        } catch (ProcessFailedException $processFailedException) {
            throw new CertbotException(sprintf('This should never happen, cert got not created? (process error) domains: %s', $domainList), 0, $processFailedException);
        }

        $data = $this->getCurrentCertificateInformation();
        foreach ($data['Found the following certs'] ?? [] as $certInfo) {
            if (!str_contains($certInfo['Expiry Date'], 'INVALID TEST_CERT')) {
                $containedDomains = DomainList::fromCommaString(str_replace(' ', ',', $certInfo['Domains']));
                $containedDomains->sort();
                if ((string)$containedDomains === (string)$domainList) {
                    $letsEncryptCertificate = new LetsEncryptCertificate($certInfo['Certificate Path'], $certInfo['Private Key Path'], $domainList);
                    $this->copyCertificateToDomains($letsEncryptCertificate);
                    $this->notifyUser($result, $domainList);
                    return $letsEncryptCertificate;
                }
            }
        }

        throw new CertbotException(sprintf('This should never happen, cert got not created? (cert check error) domains: %s', $domainList));
    }

    private function copyCertificateToDomains(LetsEncryptCertificate $cert): void
    {
        $crtPath = $cert->getCrtPath();
        $keyPath = $cert->getKeyPath();
        foreach ($cert->getDomainList() as $domain) {
            copy($crtPath, '/etc/nginx/certs/' . $domain . '.crt');
            copy($keyPath, '/etc/nginx/certs/' . $domain . '.key');
        }

        $this->nginxProxy->restart();
    }

    public function createFromDomainList(DomainList $domainList): void
    {
        $data = $this->getCurrentCertificateInformation();
        $renewOrUseCertificates = [];
        $remainingDomains = $domainList;
        foreach ($data['Found the following certs'] ?? [] as $certInfo) {
            if (!str_contains($certInfo['Expiry Date'], 'INVALID TEST_CERT')) {
                $containedDomains = explode(' ', $certInfo['Domains']);
                $tmpDomains = $remainingDomains;
                $remainingDomains = new DomainList();
                foreach ($tmpDomains as $domain) {
                    if (in_array($domain, $containedDomains, true)) {
                        $renewOrUseCertificates[implode(',', $containedDomains)] = true;
                    } else {
                        $remainingDomains->add($domain);
                    }
                }
            }
        }

        foreach (array_keys($renewOrUseCertificates) as $key) {
            $this->create(DomainList::fromCommaString($key));
        }

        if (count($remainingDomains)) {
            foreach (array_chunk($remainingDomains->toArray(), 50) as $chunkedTestDomains) {
                $this->create(new DomainList(...$chunkedTestDomains));
            }
        }
    }

    public function getCurrentCertificateInformation(): mixed
    {
        try {
            $result = ProcessUtility::runProcess('certbot certificates')->getOutput();
        } catch (ProcessFailedException $processFailedException) {
            throw new CertbotException('cloud not list all certificates', 0, $processFailedException);
        }

        $resultList = explode('- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -', $result);
        $yaml = trim($resultList[1]);
        $yaml = preg_replace('#Certificate Name: ([\S.]+)#', '$1:', $yaml);
        assert(is_string($yaml));
        $yaml = preg_replace('#\(VALID(:)#', '(VALID', $yaml);
        assert(is_string($yaml));
        $yaml = preg_replace('#\(INVALID(:)#', '(INVALID', $yaml);
        assert(is_string($yaml));
        return yaml_parse($yaml);
    }

    public function removeCertificate(string $certificateName): void
    {
        if (!$certificateName) {
            throw new InvalidArgumentException('$certificateName must not be empty');
        }

        ProcessUtility::runProcess(sprintf('certbot delete --cert-name %s', $certificateName));
    }

    private function notifyUser(string $result, DomainList $domainList): void
    {
        $hrefLinks = implode(PHP_EOL, array_map(static fn($domain): string => 'https://' . $domain, $domainList->toArray()));
        if (str_contains($result, 'Certificate not yet due for renewal')) {
            $this->output->writeln(sprintf('Certificate for %s is still valid.', $domainList));
            $this->slackNotification->sendNotification(':white_circle:CERTIFICATE is still valid for:' . PHP_EOL . $hrefLinks);
            return;
        }

        if (str_contains($result, 'Requesting a certificate for:')) {
            $this->output->writeln(sprintf('Certificate for %s created.', $domainList));
            $this->slackNotification->sendNotification(':new:CERTIFICATE created for:' . PHP_EOL . $hrefLinks);
            return;
        }

        if (str_contains($result, 'Renewing an existing certificate for')) {
            $this->output->writeln(sprintf('Certificate for %s renewed.', $domainList));
            $this->slackNotification->sendNotification(':recycle:CERTIFICATE renewed for:' . PHP_EOL . $hrefLinks);
        }

        captureMessage('notifyUser without specific message' . PHP_EOL .  $result);
    }
}
