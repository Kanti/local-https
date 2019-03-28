<?php
declare(strict_types=1);

namespace Kanti\LetsencryptClient;

final class StringUtility
{
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }
}
