<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Helper;

use Kanti\LetsencryptClient\Dto\DomainList;
use Kanti\LetsencryptClient\Dto\HostsFile;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Utility\ProcessUtility;

class HostsFileHelper
{
    public function updateHostsFiles(DomainList $domainList): void
    {
        $networkName = ConfigUtility::getEnv('DDNS_INTERFACE', 'eth0');
        if ($networkName === 'off') {
            return;
        }

        if (file_exists('/wsl-hosts-file/hosts')) {
            $hostsFile = new HostsFile('/wsl-hosts-file/hosts');

            $vmIp = trim(ProcessUtility::runProcess('ip addr show ' . $networkName . ' | grep "inet\b" | awk \'{print $2}\' | cut -d/ -f1')->getOutput());

            foreach ($domainList as $domain) {
                $hostsFile->addOrReplaceDomain($domain, $vmIp);
            }

            $hostsFile->write();
        }

        if (file_exists('/windows-hosts-file/hosts')) {
            $hostsFile = new HostsFile('/windows-hosts-file/hosts');
            foreach ($domainList as $domain) {
                $hostsFile->addOrReplaceDomain($domain, '127.0.0.1');
            }

            $hostsFile->write();
        }
    }
}
