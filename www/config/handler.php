<?php

declare(strict_types=1);

use App\Handler\HttpErrorHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;

return [
    ResponseFactoryInterface::class => DI\create(ResponseFactory::class),
    HttpErrorHandler::class => DI\autowire(),
];
