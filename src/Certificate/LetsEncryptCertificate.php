<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;


use Kanti\LetsencryptClient\SlackNotification;
use RuntimeException;
use function in_array;
use function Safe\sprintf;

final class LetsEncryptCertificate
{
    /** @var array<int, string> */
    private $domains;
    /** @var string */
    private $keyPath;
    /** @var string */
    private $crtPath;

    public function __construct(array $domains)
    {
        $this->domains = $domains;
        sort($this->domains);

        $result = shell_exec(sprintf(
            'certbot certonly -n --dns-cloudflare --dns-cloudflare-credentials var/cloudflare.ini -d %s --register-unsafely-without-email --agree-tos',
            implode(',', $this->domains)
        ));
        if (!$result) {
            throw new RuntimeException(sprintf('This should never happen, cert got not created? domains: %s', implode(',', $this->domains)));
        }
        $this->notifyUser($result);
        $data = self::getCurrentCertificateInformation();
        foreach ($data['Found the following certs'] ?? [] as $certName => $certInfo) {
            if (strpos($certInfo['Expiry Date'], 'INVALID TEST_CERT') === false) {
                $containedDomains = explode(' ', $certInfo['Domains']);
                sort($containedDomains);
                if ($containedDomains === $this->domains) {
                    $this->crtPath = str_replace('fullchain.pem', 'cert.pem', $certInfo['Certificate Path']);
                    $this->keyPath = $certInfo['Private Key Path'];
                    break;
                }
            }
        }
        if (!$this->crtPath || !$this->keyPath) {
            throw new RuntimeException(sprintf('This should never happen, cert got not created? domains: %s', implode(',', $this->domains)));
        }
    }

    public static function fromDomainList(array $domains): array
    {
        $data = self::getCurrentCertificateInformation();
        $renewOrUseCertificates = [];
        $remainingDomains = $domains;
        foreach ($data['Found the following certs'] ?? [] as $certName => $certInfo) {
            if (strpos($certInfo['Expiry Date'], 'INVALID TEST_CERT') === false) {
                $containedDomains = explode(' ', $certInfo['Domains']);
                $tmpDomains = $remainingDomains;
                $remainingDomains = [];
                foreach ($tmpDomains as $domain) {
                    if (in_array($domain, $containedDomains, true)) {
                        $renewOrUseCertificates[implode(',', $containedDomains)] = true;
                    } else {
                        $remainingDomains[] = $domain;
                    }
                }
            }
        }
        $result = [];
        foreach ($renewOrUseCertificates as $key => $_) {
            $result[] = new LetsEncryptCertificate(explode(',', $key));
        }
        if ($remainingDomains) {
            foreach (array_chunk($remainingDomains, 99) as $chunkedTestDomains) {
                $result[] = new LetsEncryptCertificate($chunkedTestDomains);
            }
        }
        return $result;
    }

    private static function getCurrentCertificateInformation()
    {
        $result = shell_exec(sprintf('certbot certificates'));
        $resultList = explode('- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -', $result);
        $yaml = trim($resultList[1]);
        $yaml = preg_replace('/Certificate Name: ([\S.]+)/', '$1:', $yaml);
        $yaml = preg_replace('/\(VALID(:)/', '(VALID', $yaml);
        $yaml = preg_replace('/\(INVALID(:)/', '(INVALID', $yaml);
        return yaml_parse($yaml);
    }

    public function getDomains(): array
    {
        return $this->domains;
    }

    public function getCrtPath(): string
    {
        return $this->crtPath;
    }

    public function getKeyPath(): string
    {
        return $this->keyPath;
    }

    /**
     * @param string|null $result
     */
    private function notifyUser(string $result): void
    {
        $hrefLinks = implode(PHP_EOL, array_map(static function ($domain) {
            return 'https://' . $domain;
        }, $this->domains));
        if (strpos($result, 'Certificate not yet due for renewal; no action taken.') !== false) {
            echo sprintf('Certificate for %s is still valid.' . PHP_EOL, implode(',', $this->domains));
            (new SlackNotification())->sendNotification([
                'text' => ':white_circle:CERTIFICATE is still valid for:' . PHP_EOL . $hrefLinks,
            ]);
        } elseif (strpos($result, 'Congratulations! Your certificate and chain have been saved at:') !== false) {
            echo sprintf('Certificate for %s created.' . PHP_EOL, implode(',', $this->domains));
            (new SlackNotification())->sendNotification([
                'text' => ':new:CERTIFICATE created for:' . PHP_EOL . $hrefLinks,
            ]);
        } else {
            echo sprintf('Certificate for %s renewed.' . PHP_EOL, implode(',', $this->domains));
            (new SlackNotification())->sendNotification([
                'text' => ':recycle:CERTIFICATE renewed for:' . PHP_EOL . $hrefLinks,
            ]);
        }
    }
}
