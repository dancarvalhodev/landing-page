<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bootstrap\Main;
use App\Config;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AppTest extends TestCase
{
    private Main $main;

    protected function setUp(): void
    {
        $this->main = new Main(new Config(dirname(__DIR__, 2), 'production'));
    }

    public function testHomeRespondsOk(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('landing-page</h1>', (string) $response->getBody());
    }

    public function testPagesLoadTheStylesheetWithAVersion(): void
    {
        $response = $this->request('GET', '/');

        self::assertMatchesRegularExpression(
            '#<link rel="stylesheet" href="/assets/css/app\.css\?v=\d+">#',
            (string) $response->getBody(),
        );
    }

    public function testUnknownPathRendersNotFoundPage(): void
    {
        $response = $this->request('GET', '/nao-existe');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Página não encontrada', (string) $response->getBody());
    }

    public function testTrailingSlashRedirectsKeepingQuery(): void
    {
        $response = $this->request('GET', '/blog/?q=php');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/blog?q=php', $response->getHeaderLine('Location'));
    }

    public function testDoubleSlashNeverRedirectsToAnotherHost(): void
    {
        $response = $this->request('GET', 'http://localhost//evil.example/');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/evil.example', $response->getHeaderLine('Location'));
    }

    public function testOtherMethodsAreRejected(): void
    {
        $response = $this->request('POST', '/');

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));
    }

    private function request(string $method, string $uri): ResponseInterface
    {
        $requestFactory = new ServerRequestFactory();
        $request = $requestFactory->createServerRequest($method, $uri);

        return $this->main->handle($request);
    }
}
