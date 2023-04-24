<?php

namespace Kanti\LetsencryptClient\Command;

use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Main;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'entrypoint')]
class EntrypointCommand extends Command
{
    public function __construct(private Main $main)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('dockerCMD', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>entrypoint start...</info>');
        $this->main->deleteOldCertificates();
        $this->main->createIfNotExistsDefaultCertificate();

        $email = ConfigUtility::getEnv('DNS_CLOUDFLARE_EMAIL');
        $apiKey = ConfigUtility::getEnv('DNS_CLOUDFLARE_API_KEY');

        ProcessUtility::runProcess('mkdir -p var');

        $lines = [];
        $lines[] = 'dns_cloudflare_email=' . $email;
        $lines[] = 'dns_cloudflare_api_key=' . $apiKey;
        file_put_contents('var/cloudflare.ini', implode(PHP_EOL, $lines));
        ProcessUtility::runProcess('chmod 0700 var/cloudflare.ini');

        $output->writeln('<info>entrypoint end</info>');
        $dockerCMD = $input->getArgument('dockerCMD');
        if ($dockerCMD) {
            $output->writeln(sprintf('starting <info>%s</info>', implode(' ', $dockerCMD)));
            passthru(implode(' ', $dockerCMD));
        }

        return 0;
    }
}
