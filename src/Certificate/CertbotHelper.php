<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use InvalidArgumentException;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\WildCardCert;
use Kanti\LetsencryptClient\Exception\CertbotException;
use Kanti\LetsencryptClient\Helper\SlackNotification;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

use function Sentry\captureMessage;
use function sprintf;

final class CertbotHelper
{
    public function __construct(private OutputInterface $output, private SlackNotification $slackNotification)
    {
    }

    public function create(Domain $domain): WildCardCert
    {
        $cmd = sprintf(
            'certbot certonly -n --dns-cloudflare --dns-cloudflare-credentials var/cloudflare.ini -d %s --register-unsafely-without-email --agree-tos --dns-cloudflare-propagation-seconds=20',
            $domain . ',*.' . $domain
        );
        try {
            $result = ProcessUtility::runProcess($cmd)->getOutput();
        } catch (ProcessFailedException $processFailedException) {
            throw new CertbotException(
                sprintf('This should never happen, cert got not created? (process error) domains: %s', $domain),
                0,
                $processFailedException
            );
        }

        $this->notifyUser($result, $domain);

        return new WildCardCert(
            '/etc/letsencrypt/live/' . $domain . '/fullchain.pem',
            '/etc/letsencrypt/live/' . $domain . '/privkey.pem',
            $domain
        );
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

        ProcessUtility::runProcess(sprintf('certbot delete -n --cert-name %s', $certificateName));
    }

    private function notifyUser(string $result, Domain $domain): void
    {
        if (str_contains($result, 'Certificate not yet due for renewal')) {
            $this->output->writeln(sprintf('Certificate for *.%s is still valid.', $domain));
            $this->slackNotification->sendNotification(sprintf(':white_circle:CERTIFICATE is still valid for <https://%s|*.%s>', $domain, $domain));
            return;
        }

        if (str_contains($result, 'Requesting a certificate for')) {
            $this->output->writeln(sprintf('Certificate for *.%s created.', $domain));
            $this->slackNotification->sendNotification(sprintf(':new:CERTIFICATE created for <https://%s|*.%s>', $domain, $domain));
            return;
        }

        if (str_contains($result, 'Renewing an existing certificate for')) {
            $this->output->writeln(sprintf('Certificate for *.%s renewed.', $domain));
            $this->slackNotification->sendNotification(sprintf(':recycle:CERTIFICATE renewed for <https://%s|*.%s>', $domain, $domain));
        }

        captureMessage('notifyUser without specific message' . PHP_EOL . $result);
    }
}
