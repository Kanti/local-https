<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Dto;

final class LetsEncryptCertificate
{
    public function __construct(private string $crtPath, private string $keyPath, private DomainList $domainList)
    {
    }

    public function getCrtPath(): string
    {
        return $this->crtPath;
    }

    public function getKeyPath(): string
    {
        return $this->keyPath;
    }

    public function getDomainList(): DomainList
    {
        return $this->domainList;
    }
}
