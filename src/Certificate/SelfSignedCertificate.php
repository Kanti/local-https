<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use DateTimeImmutable;
use Kanti\LetsencryptClient\Utility\ProcessUtility;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

final class SelfSignedCertificate
{
    /** @var string */
    private const CN_NAME = 'kanti-local-https-client';

    public function __construct(private OutputInterface $output)
    {
    }

    public function createIfNotExists(string $certPathAndName): bool
    {
        $this->output->writeln(sprintf('Check SelfSignedCertificate for path %s', $certPathAndName));

        if (!file_exists($certPathAndName . '.crt')) {
            $this->output->writeln(sprintf('forceCreateNew SelfSignedCertificate %s.crt not found', $certPathAndName));
            return $this->forceCreateNew($certPathAndName);
        }

        if (!file_exists($certPathAndName . '.key')) {
            $this->output->writeln(sprintf('forceCreateNew SelfSignedCertificate %s.key not found', $certPathAndName));
            return $this->forceCreateNew($certPathAndName);
        }

        if (!$this->certIsValid($certPathAndName)) {
            $this->output->writeln(sprintf('forceCreateNew SelfSignedCertificate %s not valid anymore', $certPathAndName));
            return $this->forceCreateNew($certPathAndName);
        }

        return false;
    }

    public function forceCreateNew(string $certPathAndName): bool
    {
        ProcessUtility::runProcess(
            sprintf(
                'openssl req -x509 \
            -newkey rsa:4096 -sha256 -nodes -days 365 \
            -subj "/CN=%s" \
            -keyout /tmp/new.key \
            -out /tmp/new.crt \
            && mv /tmp/new.key %s \
            && mv /tmp/new.crt %s',
                self::CN_NAME,
                $certPathAndName . '.key',
                $certPathAndName . '.crt'
            )
        );
        return true;
    }

    private function certIsValid(string $certPathAndName): bool
    {
        $result = ProcessUtility::runProcess(sprintf('openssl x509 -noout -subject -in %s', $certPathAndName . '.crt'))->getOutput();
        if (trim($result) !== sprintf('subject=CN = %s', self::CN_NAME)) {
            return false;
        }

        $result = ProcessUtility::runProcess(sprintf('openssl x509 -noout -enddate -in "%s" | cut -d "=" -f 2', $certPathAndName . '.crt'))->getOutput();
        $certDate = new DateTimeImmutable($result);
        return $certDate > new DateTimeImmutable('+3 months');
    }
}
