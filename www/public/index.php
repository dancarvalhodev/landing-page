<?php

declare(strict_types=1);

use App\Bootstrap\Main;
use App\Config;

$rootDir = dirname(__DIR__);

require $rootDir . '/vendor/autoload.php';

$config = Config::fromEnv($rootDir);

$main = new Main($config);
$main->run();
