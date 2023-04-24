<?php

namespace Kanti\LetsencryptClient\Command;

use Kanti\LetsencryptClient\CertChecker;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Dto\DataJsonReader;
use Kanti\LetsencryptClient\Main;
use Kanti\LetsencryptClient\NginxProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'notify')]
class NotifyCommand extends Command
{
    public function __construct(
        private NginxProxy $nginxProxy,
        private Main $main,
        private CertChecker $certChecker
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>notify start...</info>');
        $this->main->deleteReallyOldCertificates();
        $this->main->createIfNotExistsDefaultCertificate();

        $mainDomain = ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN');
        $dataJsonReader = new DataJsonReader($output, $mainDomain, 'var/data.json');
        $domainLists = $dataJsonReader->getDomainLists();
        foreach ($domainLists as $domainList) {
            if ($this->certChecker->createIfNotExists($domainList)) {
                $this->nginxProxy->restart();
            }
        }

        $output->writeln('<info>notify end.</info>');
        return 0;
    }
}
