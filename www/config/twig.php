<?php

declare(strict_types=1);

use App\Config;
use App\Twig\AssetExtension;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

return [
    Twig::class => function (ContainerInterface $container): Twig {
        $config = $container->get(Config::class);

        $cache = false;

        if (!$config->isDevelopment()) {
            $cache = $config->rootDir . '/var/cache/twig';
        }

        $twig = Twig::create($config->rootDir . '/templates', [
            'cache' => $cache,
            'auto_reload' => true,
            'strict_variables' => true,
        ]);

        $twig->addExtension(new AssetExtension($config));

        return $twig;
    },
];
