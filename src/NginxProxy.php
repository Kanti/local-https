<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;


use Exception;
use function Safe\sprintf;

final class NginxProxy
{
    /** @var ?string */
    private $dockerGenContainer;

    public function __construct()
    {
        $this->getDockerGenContainer();
    }

    public function restart(): void
    {
        $result = shell_exec(sprintf(
            "docker restart %s",
            $this->getDockerGenContainer()
        ));
        echo $result . PHP_EOL . 'Nginx Restarted.' . PHP_EOL;
    }

    private function getDockerGenContainer(): string
    {
        if ($this->dockerGenContainer === null) {
            $result = shell_exec(sprintf('docker ps -f "label=com.github.kanti.local_https.nginx_proxy" -q'));
            if (!$result) {
                throw new Exception('ERROR NginxProxy Not found. did you not set the label=com.github.kanti.local_https.nginx_proxy on jwilder/nginx-proxy');
            }
            $this->dockerGenContainer = trim($result);
        }
        return $this->dockerGenContainer;
    }
}
