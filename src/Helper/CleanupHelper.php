<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Helper;

use Kanti\LetsencryptClient\Certificate\CertificateWithDomainCheck;
use Kanti\LetsencryptClient\Certificate\CertbotHelper;
use DateTimeImmutable;
use Kanti\LetsencryptClient\Dto\Domain;
use Kanti\LetsencryptClient\Exception\InvalidDomainException;
use Kanti\LetsencryptClient\Helper\NginxProxy;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use function Safe\preg_replace;

final class CleanupHelper
{
    public function __construct(
        private CertbotHelper $certbotHelper,
        private CertificateWithDomainCheck $certificateWithDomainCheck,
        private OutputInterface $output,
        private NginxProxy $nginxProxy,
    ) {
    }

    public function deleteOldCertificatesFromCertbot(): void
    {
        $this->output->writeln('deleteOldCertificatesFromCertbot...');
        $data = $this->certbotHelper->getCurrentCertificateInformation();
        foreach ($data['Found the following certs'] ?? [] as $name => $certInfo) {
            preg_match('#(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})#', $certInfo['Expiry Date'], $matches);
            $certDate = new DateTimeImmutable($matches[0]);
            if ($certDate < new DateTimeImmutable('-1 month')) {
                $this->certbotHelper->removeCertificate($name);
                continue;
            }

            [$name, $wildCard] = explode(' ', $certInfo['Domains'] . '    ');
            $isWildCardCertificate = ('*.' . $name) === $wildCard;
            if (!$isWildCardCertificate) {
                $this->certbotHelper->removeCertificate($name);
            }
        }

        $this->output->writeln('...deleteOldCertificatesFromCertbot DONE');
    }

    public function deleteInvalidCertificatesInNginxDir(Domain $mainDomain): void
    {
        $this->output->writeln(sprintf('deleteInvalidCertificatesInNginxDir (%s)...', $mainDomain));
        $finder = new Finder();
        $finder->files()->in('/etc/nginx/certs/')->name('*.crt');

        $somethingChanged = false;

        foreach ($finder as $file) {
            $domainString = $file->getBasename('.crt');

            try {
                $testDomain = new Domain($domainString);
            } catch (InvalidDomainException) {
                $this->output->writeln(sprintf('<error>remove Certificate from  (InvalidDomainException) <options=bold> %s</></error>', $domainString));
                unlink($file->getPathname());
                $somethingChanged = true;
                continue;
            }

            if (!str_ends_with((string)$testDomain, (string)$mainDomain)) {
                continue;
            }

            if ($this->certificateWithDomainCheck->checkCertificate($file->getPathname(), $testDomain)) {
                continue;
            }

            $this->output->writeln(sprintf('<error>remove Certificate from nginx<options=bold> %s</></error>', $domainString));
            unlink($file->getPathname());
            $somethingChanged = true;
        }

        foreach ($finder->files()->in('/etc/nginx/certs/')->name('*.key') as $keyFile) {
            $crtFile = preg_replace('#(\.key)$#', '.crt', $keyFile->getPathname());
            assert(is_string($crtFile));
            if (file_exists($crtFile)) {
                continue;
            }

            unlink($keyFile->getPathname());
            $somethingChanged = true;
        }

        if ($somethingChanged) {
            $this->nginxProxy->restart();
        }

        $this->output->writeln('...deleteInvalidCertificatesInNginxDir DONE');
    }
}
