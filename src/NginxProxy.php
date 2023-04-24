<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient;

use Exception;
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
}
