<?php

declare(strict_types=1);

use App\Config;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

return [
    Twig::class => function (ContainerInterface $container): Twig {
        $config = $container->get(Config::class);

        $cache = false;

        if (!$config->isDevelopment()) {
            $cache = $config->rootDir . '/var/cache/twig';
        }

        return Twig::create($config->rootDir . '/templates', [
            'cache' => $cache,
            'auto_reload' => true,
            'strict_variables' => true,
        ]);
    },
];
