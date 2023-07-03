<?php

namespace Kanti\LetsencryptClient\Command;

use Kanti\LetsencryptClient\Certificate\CertChecker;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Dto\LetsEncryptCertificate;
use Kanti\LetsencryptClient\Helper\HostsFileHelper;
use Kanti\LetsencryptClient\Helper\WildCardHelper;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Helper\DataJsonReader;
use Kanti\LetsencryptClient\Helper\CleanupHelper;
use Kanti\LetsencryptClient\Helper\NginxProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;

#[AsCommand(name: 'notify')]
class NotifyCommand extends Command
{
    public function __construct(
        private NginxProxy $nginxProxy,
        private CleanupHelper $cleanupHelper,
        private DataJsonReader $dataJsonReader,
        private WildCardHelper $wildCardHelper,
        private HostsFileHelper $hostsFileHelper,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = $this->lock();
        if (!$lock->acquire()) {
            $output->writeln('<error>notify still running. STOPPED</error>');
            return 0;
        }

        $output->writeln('<info>notify start...</info>');

        $mainDomain = new Domain(ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN'));

        $this->cleanupHelper->deleteInvalidCertificatesInNginxDir($mainDomain);
        $this->cleanupHelper->deleteOldCertificatesFromCertbot();

        $domainLists = $this->dataJsonReader->getDomainList($mainDomain, 'var/data.json');

        $this->hostsFileHelper->updateHostsFiles($domainLists);

        foreach ($domainLists->getWildCardDomainList() as $domain) {
            $wildCardCert = $this->wildCardHelper->createOrUpdate($domain);
            if ($wildCardCert) {
                $this->nginxProxy
                    ->copyToLocation($wildCardCert)
                    ->restart();
            }
        }

        $lock->release();

        $output->writeln('<info>notify end.</info>');
        return 0;
    }

    public function lock(): LockInterface
    {
        return (new LockFactory(new FlockStore()))->createLock('notify-cmd-execution');
    }
}
