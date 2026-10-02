<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Config;
use App\Handler\HttpErrorHandler;
use App\Twig\AssetExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class HttpErrorHandlerTest extends TestCase
{
    public function testUnexpectedErrorShowsGenericPageWhenDetailsAreHidden(): void
    {
        $response = $this->handle(new RuntimeException('senha do banco: 123'), false);
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('<h1>500</h1>', $body);
        self::assertStringNotContainsString('senha do banco', $body);
    }

    public function testUnexpectedErrorShowsDetailsInDevelopment(): void
    {
        $response = $this->handle(new RuntimeException('falha de teste'), true);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('falha de teste', (string) $response->getBody());
    }

    private function handle(RuntimeException $exception, bool $displayErrorDetails): ResponseInterface
    {
        $rootDir = dirname(__DIR__, 2);

        $twig = Twig::create($rootDir . '/templates');
        $twig->addExtension(new AssetExtension(new Config($rootDir, 'development')));

        $handler = new HttpErrorHandler(new CallableResolver(), new ResponseFactory(), $twig);

        $requestFactory = new ServerRequestFactory();
        $request = $requestFactory->createServerRequest('GET', '/');

        return $handler($request, $exception, $displayErrorDetails, false, false);
    }
}
