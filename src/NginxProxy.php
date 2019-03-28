<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;


use function Safe\sprintf;

final class NginxProxy
{
    /** @var ?string */
    private $dockerGenContainer;

    public function reload(): void
    {
        shell_exec(sprintf(
            'docker exec -it %s sh -c \'/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf; /usr/sbin/nginx -s reload\'',
            $this->getDockerGenContainer()
        ));
        echo 'Nginx Reloaded.' . PHP_EOL;
    }

    private function getDockerGenContainer(): string
    {
        if ($this->dockerGenContainer === null) {
            $this->dockerGenContainer = trim(shell_exec(sprintf('docker ps -f "label=com.github.kanti.local_https.nginx_proxy" -q')));
        }
        return $this->dockerGenContainer;
    }
}
