<?php

namespace Kanti\LetsencryptClient\Command;

use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Helper\WildCardHelper;
use Kanti\LetsencryptClient\Helper\NginxProxy;
use Kanti\LetsencryptClient\Utility\ConfigUtility;
use Kanti\LetsencryptClient\Helper\CleanupHelper;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'entrypoint')]
class EntrypointCommand extends Command
{
    public function __construct(private CleanupHelper $cleanupHelper, private WildCardHelper $wildCardHelper, private NginxProxy $nginxProxy)
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
        $mainDomain = new Domain(ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN'));
        $this->cleanupHelper->deleteInvalidCertificatesInNginxDir($mainDomain);
        $this->cleanupHelper->deleteOldCertificatesFromCertbot();

        $email = ConfigUtility::getEnv('DNS_CLOUDFLARE_EMAIL');
        $apiKey = ConfigUtility::getEnv('DNS_CLOUDFLARE_API_KEY');

        ProcessUtility::runProcess('mkdir -p var');

        $lines = [];
        $lines[] = 'dns_cloudflare_email=' . $email;
        $lines[] = 'dns_cloudflare_api_key=' . $apiKey;
        file_put_contents('var/cloudflare.ini', implode(PHP_EOL, $lines));
        ProcessUtility::runProcess('chmod 0700 var/cloudflare.ini');


        $mainDomain = new Domain(ConfigUtility::getEnv('HTTPS_MAIN_DOMAIN'));
        $wildCardCert = $this->wildCardHelper->createOrUpdate($mainDomain);
        if ($wildCardCert) {
            $this->nginxProxy
                ->copyToLocation($wildCardCert)
                ->restart();
        }

        if (!file_exists('/etc/nginx/certs/default.crt')) {
            $this->nginxProxy
                ->copyToDefaultLocation($mainDomain)
                ->restart();
        }

        $output->writeln('<info>entrypoint end</info>');
        $dockerCMD = $input->getArgument('dockerCMD');
        if ($dockerCMD) {
            $output->writeln(sprintf('starting <info>%s</info>', implode(' ', $dockerCMD)));
            passthru(implode(' ', $dockerCMD));
        }

        return 0;
    }
}
