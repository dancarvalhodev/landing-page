<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CanonicalPathMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $canonicalPath = $this->canonicalPath($path);

        if ($canonicalPath === $path) {
            return $handler->handle($request);
        }

        $location = $canonicalPath;

        if ($uri->getQuery() !== '') {
            $location .= '?' . $uri->getQuery();
        }

        return $this->responseFactory->createResponse(301)->withHeader('Location', $location);
    }

    private function canonicalPath(string $path): string
    {
        $segments = explode('/', $path);
        $nonEmptySegments = [];

        // Descartar os segmentos vazios também impede que "//host/" vire um redirecionamento externo.
        foreach ($segments as $segment) {
            if ($segment !== '') {
                $nonEmptySegments[] = $segment;
            }
        }

        return '/' . implode('/', $nonEmptySegments);
    }
}
