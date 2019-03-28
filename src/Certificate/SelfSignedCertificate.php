<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient\Certificate;

use DateTimeImmutable;
use function Safe\sprintf;

final class SelfSignedCertificate
{
    private const CN_NAME = 'kanti-local-https-client';

    /**@var string */
    private $certPathAndName;

    public function __construct(string $certPathAndName)
    {
        $this->certPathAndName = $certPathAndName;
    }

    public function createIfNotExists(): bool
    {
        if (!file_exists($this->certPathAndName . '.crt')) {
            return $this->forceCreateNew();
        }
        if (!file_exists($this->certPathAndName . '.key')) {
            return $this->forceCreateNew();
        }
        if (!$this->certIsValid()) {
            return $this->forceCreateNew();
        }
        return false;
    }

    public function forceCreateNew(): bool
    {
        shell_exec(sprintf('openssl req -x509 \
            -newkey rsa:4096 -sha256 -nodes -days 365 \
            -subj "/CN=%s" \
            -keyout /tmp/new.key \
            -out /tmp/new.crt \
            && mv /tmp/new.key %s \
            && mv /tmp/new.crt %s',
            self::CN_NAME,
            $this->certPathAndName . '.key',
            $this->certPathAndName . '.crt'
        ));
        return true;
    }

    private function certIsValid(): bool
    {
        $result = shell_exec(sprintf('openssl x509 -noout -subject -in %s', $this->certPathAndName . '.crt'));
        if (trim($result) !== sprintf('subject=CN = %s', self::CN_NAME)) {
            return false;
        }
        $result = shell_exec(sprintf('openssl x509 -noout -enddate -in "%s" | cut -d "=" -f 2', $this->certPathAndName . '.crt'));
        $certDate = new DateTimeImmutable($result);
        return $certDate > new DateTimeImmutable('+3 months');
    }
}
