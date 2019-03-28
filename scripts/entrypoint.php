#!/usr/bin/php
<?php
declare(strict_types=1);

use Kanti\LetsencryptClient\Main;

require_once 'vendor/autoload.php';

$main = new Main($argv);
$main->entrypoint();
