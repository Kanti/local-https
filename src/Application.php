<?php

namespace Kanti\LetsencryptClient;

use Kanti\LetsencryptClient\Command\EntrypointCommand;
use Kanti\LetsencryptClient\Command\NotifyCommand;
use Kanti\LetsencryptClient\Exception\CertbotException;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Application extends SymfonyApplication
{
    public static ContainerInterface $container;

    public function __construct()
    {
        parent::__construct();

        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator('/app/config'));
        $loader->load('/app/config/services.yaml');


        $input = new ArgvInput();
        $output = new ConsoleOutput();

        $container->register(InputInterface::class, ArgvInput::class)->setSynthetic(true);
        $container->register(OutputInterface::class, ConsoleOutput::class)->setSynthetic(true);

        $container->compile();

        $container->set(InputInterface::class, $input);
        $container->set(OutputInterface::class, $output);

        self::$container = $container;

        $this->add($container->get(EntrypointCommand::class));
        $this->add($container->get(NotifyCommand::class));

        try {
            $this->run($input, $output);
        } catch (CertbotException $certbotException) {
            $logPath = '/var/log/letsencrypt/letsencrypt.log';
            $lastNLines = 200;
            $output->writeln(sprintf("<info>last %d Lines from %s</info>", $lastNLines, $logPath));
            $output->writeln(ProcessUtility::runProcess(sprintf("tail -%d %s", $lastNLines, $logPath))->getOutput());

            throw $certbotException;
        }
    }
}
