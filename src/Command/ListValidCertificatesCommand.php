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

#[AsCommand(name: 'list')]
class ListValidCertificatesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>TODO.</info>');
        return 2;
    }
}
