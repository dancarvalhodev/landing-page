<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config;
use App\Handler\HttpErrorHandler;
use App\Middleware\CanonicalPathMiddleware;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

final class Main
{
    private Container $container;

    /** @var App<Container> */
    private App $app;

    public function __construct(private readonly Config $config)
    {
        $this->buildContainer();
        $this->createApp();
        $this->registerMiddlewares();
        $this->registerRoutes();
    }

    public function run(): void
    {
        $this->app->run();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    private function buildContainer(): void
    {
        $rootDir = $this->config->rootDir;

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([Config::class => $this->config]);
        $builder->addDefinitions($rootDir . '/config/i18n.php');
        $builder->addDefinitions($rootDir . '/config/twig.php');
        $builder->addDefinitions($rootDir . '/config/handler.php');

        $this->container = $builder->build();
    }

    private function createApp(): void
    {
        $this->app = Bridge::create($this->container);
    }

    private function registerMiddlewares(): void
    {
        $this->app->add(CanonicalPathMiddleware::class);

        // O Slim executa primeiro o último middleware adicionado: o de erros fica por fora de todos.
        $errorMiddleware = $this->app->addErrorMiddleware(
            $this->config->isDevelopment(), // displayErrorDetails
            true, // logErrors
            true, // logErrorDetails
        );

        $errorHandler = $this->container->get(HttpErrorHandler::class);

        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }

    private function registerRoutes(): void
    {
        $registerRoutes = require $this->config->rootDir . '/routes/web.php';

        $registerRoutes($this->app);
    }
}
