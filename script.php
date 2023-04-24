#!/usr/bin/php
<?php

declare(strict_types=1);

use Kanti\LetsencryptClient\Application;
use Symfony\Component\ErrorHandler\ErrorHandler;

require_once __DIR__ . '/vendor/autoload.php';

ErrorHandler::register();

new Application();
