<?php

declare(strict_types=1);

namespace Kanti\LetsencryptClient\Utility;

use Exception;

final class ConfigUtility
{
    public static function getEnv(string $key): string
    {
        $env = getenv($key);
        if (empty($env)) {
            throw new Exception(sprintf('ENVIRONMENT variable %s must be set.', $key));
        }

        return $env;
    }
}
