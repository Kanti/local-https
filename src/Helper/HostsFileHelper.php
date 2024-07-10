<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Helper;

use Kanti\LetsencryptClient\Dto\DomainList;
use Kanti\LetsencryptClient\Dto\HostsFile;
use Symfony\Component\Console\Output\OutputInterface;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Utility\ProcessUtility;

final class HostsFileHelper
{
    public function updateHostsFiles(DomainList $domainList, OutputInterface $output): void
    {
        $networkName = ConfigUtility::getEnv('DDNS_INTERFACE', 'eth0');
        if ($networkName === 'off') {
            return;
        }

        $vmIp = null;

        if (file_exists('/wsl-hosts-file/hosts')) {
            $hostsFile = new HostsFile('/wsl-hosts-file/hosts');

            $vmIp = $this->getVmIp($networkName);
            if (!$vmIp) {
                $output->writeln('<warning>could not find vmIp for network ' . $networkName . ' (to disable this Feature use DDNS_INTERFACE=off)</warning>');
                return;
            }

            foreach ($domainList as $domain) {
                $hostsFile->addOrReplaceDomain($domain, $vmIp);
            }

            $hostsFile->write();
        }

        if (file_exists('/windows-hosts-file/hosts')) {
            $hostsFile = new HostsFile('/windows-hosts-file/hosts');

            $vmIp ??= $this->getVmIp($networkName);
            if (!$vmIp) {
                $output->writeln('<warning>could not find vmIp for network ' . $networkName . ' (to disable this Feature use DDNS_INTERFACE=off)</warning>');
                return;
            }

            foreach ($domainList as $domain) {
                $hostsFile->addOrReplaceDomain($domain, $vmIp);
            }

            $hostsFile->write();
        }
    }

    private function getVmIp(string $networkName): string
    {
        return trim(ProcessUtility::runProcess('ip addr show ' . $networkName . ' | grep "inet\b" | awk \'{print $2}\' | cut -d/ -f1')->getOutput());
    }
}
