<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Kanti\LetsencryptClient\Certificate\LetsEncryptCertificateFactory;
use Kanti\LetsencryptClient\Certificate\SelfSignedCertificate;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use DateTimeImmutable;

use function sprintf;

final class Main
{
    public function __construct(
        private NginxProxy $nginxProxy,
        private SlackNotification $slackNotification,
        private SelfSignedCertificate $selfSignedCertificate,
        private LetsEncryptCertificateFactory $letsEncryptCertificateFactory
    ) {
    }

    public function createIfNotExistsDefaultCertificate(): void
    {
        $mainDomain = ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN');

        if ($this->selfSignedCertificate->createIfNotExists('/etc/nginx/certs/default')) {
            $this->slackNotification->sendNotification(sprintf(':selfie: CERTIFICATE self signed created %s.', $mainDomain));
            $this->nginxProxy->restart();
        }
    }

    public function deleteOldCertificates(): void
    {
        $data = $this->letsEncryptCertificateFactory->getCurrentCertificateInformation();
        foreach ($data['Found the following certs'] ?? [] as $name => $certInfo) {
            preg_match('#(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})#', $certInfo['Expiry Date'], $matches);
            $certDate = new DateTimeImmutable($matches[0]);
            if ($certDate < new DateTimeImmutable('-1 month')) {
                $this->letsEncryptCertificateFactory->removeCertificate($name);
            }
        }
    }
}
