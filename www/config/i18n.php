<?php

declare(strict_types=1);

use App\Config;
use App\I18n\InterfaceTexts;
use Psr\Container\ContainerInterface;

return [
    InterfaceTexts::class => function (ContainerInterface $container): InterfaceTexts {
        $config = $container->get(Config::class);

        return new InterfaceTexts($config->rootDir . '/lang');
    },
];
