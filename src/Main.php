<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Exception;
use Kanti\LetsencryptClient\Certificate\SelfSignedCertificate;
use function Safe\sprintf;

final class Main
{
    /** @var array<int, string> */
    private $argv;
    /** @var NginxProxy */
    private $nginxProxy;

    public function __construct(array $argv)
    {
        $this->nginxProxy = new NginxProxy();
        $this->argv = $argv;
    }

    public function notify(): void
    {
        $mainDomain = $this->getRequiredEnv('HTTPS_MAIN_DOMAIN');
        $this->createIfNotExistsDefaultCertificate($mainDomain);

        $dataJsonReader = new DataJsonReader($mainDomain, 'var/data.json');
        $domains = $dataJsonReader->getDomains();
        $certChecker = new CertChecker();
        if ($certChecker->createIfNotExists($domains)) {
            $this->nginxProxy->reload();
        }
    }

    public function entrypoint(): void
    {
        $mainDomain = $this->getRequiredEnv('HTTPS_MAIN_DOMAIN');
        $this->createIfNotExistsDefaultCertificate($mainDomain);

        $email = $this->getRequiredEnv('DNS_CLOUDFLARE_EMAIL');
        $apiKey = $this->getRequiredEnv('DNS_CLOUDFLARE_API_KEY');

        shell_exec('mkdir -p var');

        $lines = [];
        $lines[] = 'dns_cloudflare_email=' . $email;
        $lines[] = 'dns_cloudflare_api_key=' . $apiKey;
        file_put_contents('var/cloudflare.ini', implode(PHP_EOL, $lines));
        shell_exec('chmod 0700 var/cloudflare.ini');

        $argv = $this->argv;
        //first is /app/entrypoint.php
        array_shift($argv);
        passthru(implode(' ', $argv));
    }

    private function getRequiredEnv(string $key): string
    {
        $env = getenv($key);
        if (empty($env)) {
            throw new Exception(sprintf('ENVIRONMENT variable %s must be set.', $key));
        }
        return $env;
    }

    private function createIfNotExistsDefaultCertificate(string $mainDomain): void
    {
        $cert = new SelfSignedCertificate('/etc/nginx/certs/default');
        if ($cert->createIfNotExists()) {
            (new SlackNotification())->sendNotification([
                'username' => 'localHttps',
                'text' => sprintf(':selfie: CERTIFICATE self signed created %s.', $mainDomain),
            ]);
            $this->nginxProxy->reload();
        }
    }
}
