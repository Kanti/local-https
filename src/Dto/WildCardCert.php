<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Dto;

final class WildCardCert
{
    public function __construct(private string $crtPath, private string $keyPath, private Domain $domain)
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

    public function getDomain(): Domain
    {
        return $this->domain;
    }
}
