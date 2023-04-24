<?php

namespace Kanti\LetsencryptClient\Utility;

use Kanti\LetsencryptClient\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessUtility
{
    public static function runProcess(string $cmd): Process
    {
        $containerBuilder = Application::$container ?? null;
        $output = $containerBuilder?->get(OutputInterface::class);
        $output?->write(sprintf('running command <info>%s </info>...', $cmd));

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        $output?->writeln(sprintf('...done (%s)', $process->getExitCodeText()));

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output?->write(sprintf('<fg=red>%s</>', $process->getErrorOutput()));
        $output?->write(sprintf('<options=bold>%s</>', $process->getOutput()));
        return $process;
    }
}
