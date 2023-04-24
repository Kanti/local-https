<?php

namespace Kanti\LetsencryptClient\Command;

use Kanti\LetsencryptClient\CertChecker;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Helper\DataJsonReader;
use Kanti\LetsencryptClient\Main;
use Kanti\LetsencryptClient\NginxProxy;
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
        private Main $main,
        private CertChecker $certChecker,
        private DataJsonReader $dataJsonReader
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
        $this->main->deleteOldCertificates();
        $this->main->createIfNotExistsDefaultCertificate();

        $mainDomain = ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN');
        $domainLists = $this->dataJsonReader->getDomainLists($mainDomain, 'var/data.json');
        foreach ($domainLists as $domainList) {
            if ($this->certChecker->createIfNotExists($domainList)) {
                $this->nginxProxy->restart();
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
