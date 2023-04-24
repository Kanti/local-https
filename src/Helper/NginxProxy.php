<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Helper;

use Exception;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\WildCardCert;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class NginxProxy
{
    private string $dockerGenContainer;

    public function __construct(
        private OutputInterface $output,
    ) {
        $result = ProcessUtility::runProcess('docker ps -f "label=com.github.kanti.local_https.nginx_proxy" -q')->getOutput();
        if (!$result) {
            throw new Exception('ERROR NginxProxy Not found. did you not set the label=com.github.kanti.local_https.nginx_proxy on jwilder/nginx-proxy');
        }

        $this->dockerGenContainer = trim($result);
    }

    public function restart(): void
    {
        $result = ProcessUtility::runProcess(sprintf("docker restart %s", $this->dockerGenContainer))->getOutput();
        $this->output->writeln($result . PHP_EOL . '<info>Nginx Restarted.</info>');
    }

    public function copyToLocation(WildCardCert $wildCardCert): self
    {
        $crtPath = $wildCardCert->getCrtPath();
        $keyPath = $wildCardCert->getKeyPath();
        $domain = $wildCardCert->getDomain();
        copy($crtPath, '/etc/nginx/certs/' . $domain . '.crt');
        copy($keyPath, '/etc/nginx/certs/' . $domain . '.key');
        $this->output->writeln(sprintf('install %s into nginx', $domain));
        return $this;
    }

    public function copyToDefaultLocation(Domain $domain): self
    {
        copy('/etc/nginx/certs/' . $domain . '.crt', '/etc/nginx/certs/default.crt');
        copy('/etc/nginx/certs/' . $domain . '.key', '/etc/nginx/certs/default.key');
        $this->output->writeln(sprintf('install %s into nginx as default', $domain));
        return $this;
    }
}
