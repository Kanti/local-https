<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Dto;

use Exception;

final class HostsFile
{
    /** @var string[]  */
    private array $lines = [];

    /** @var string[]  */
    private array $initalLines = [];

    public function __construct(private string $filePath)
    {
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
            throw new Exception(sprintf('Unable to read file: %s', $filePath));
        }

        $content = file_get_contents($this->filePath);
        $this->lines = explode("\n", $content);
        $this->initalLines = explode("\n", $content);
    }

    public function addOrReplaceDomain(Domain $domain, string $ip): void
    {
        foreach ($this->lines as &$line) {
            preg_match('#^\s*(?<ip>\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3})\s+(?<domain>[^\s]+)\s*$#', $line, $matches);
            if (($matches['domain'] ?? '') !== (string)$domain) {
                continue;
            }

            $line = str_replace($matches['ip'], $ip, $line);
            return;
        }

        $lineEnd = str_ends_with($this->lines[0] ?? '', "\r") ? "\r" : '';
        $this->lines[] = $ip . ' ' . $domain . $lineEnd;
    }

    public function write(): void
    {
        if ($this->lines === $this->initalLines) {
            return;
        }

        file_put_contents($this->filePath, implode("\n", $this->lines));
    }
}
