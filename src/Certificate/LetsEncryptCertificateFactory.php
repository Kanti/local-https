<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use InvalidArgumentException;
use Kanti\LetsencryptClient\Dto\DomainList;
use Kanti\LetsencryptClient\Dto\LetsEncryptCertificate;
use Kanti\LetsencryptClient\Exception\CertbotException;
use Kanti\LetsencryptClient\SlackNotification;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function Amp\Parallel\Worker\create;
use function in_array;
use function sprintf;

final class LetsEncryptCertificateFactory
{
    public function __construct(private OutputInterface $output, private SlackNotification $slackNotification)
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
            throw new CertbotException(sprintf('This should never happen, cert got not created? domains: %s', $domainList), 0, $processFailedException);
        }

        $this->notifyUser($result, $domainList);
        $data = $this->getCurrentCertificateInformation();
        foreach ($data['Found the following certs'] ?? [] as $certInfo) {
            if (!str_contains($certInfo['Expiry Date'], 'INVALID TEST_CERT')) {
                $containedDomains = DomainList::fromCommaString($certInfo['Domains']);
                if ((string)$containedDomains === (string)$domainList) {
                    return new LetsEncryptCertificate($certInfo['Certificate Path'], $certInfo['Private Key Path'], $domainList);
                }
            }
        }

        throw new CertbotException(sprintf('This should never happen, cert got not created? domains: %s', $domainList));
    }

    /**
     * @return array<LetsEncryptCertificate>
     */
    public function fromDomainList(DomainList $domainList, OutputInterface $output): array
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

        $result = [];
        foreach (array_keys($renewOrUseCertificates) as $key) {
            $result[] = $this->create(DomainList::fromCommaString($key));
        }

        if (count($remainingDomains)) {
            foreach (array_chunk($remainingDomains->toArray(), 50) as $chunkedTestDomains) {
                $result[] = $this->create(new DomainList(...$chunkedTestDomains));
            }
        }

        return $result;
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
        if (str_contains($result, 'Certificate not yet due for renewal; no action taken.')) {
            $this->output->writeln(sprintf('Certificate for %s is still valid.', $domainList));
            $this->slackNotification->sendNotification(':white_circle:CERTIFICATE is still valid for:' . PHP_EOL . $hrefLinks);
        } elseif (str_contains($result, 'Congratulations! Your certificate and chain have been saved at:')) {
            $this->output->writeln(sprintf('Certificate for %s created.', $domainList));
            $this->slackNotification->sendNotification(':new:CERTIFICATE created for:' . PHP_EOL . $hrefLinks);
        } else {
            $this->output->writeln(sprintf('Certificate for %s renewed.', $domainList));
            $this->slackNotification->sendNotification(':recycle:CERTIFICATE renewed for:' . PHP_EOL . $hrefLinks);
        }
    }
}
