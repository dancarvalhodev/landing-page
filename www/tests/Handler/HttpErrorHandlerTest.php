<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Config;
use App\Content\ContentRepository;
use App\Handler\HttpErrorHandler;
use App\I18n\InterfaceTexts;
use App\I18n\LocalizerFactory;
use App\I18n\UrlGenerator;
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
        $response = $this->handle('/', new RuntimeException('senha do banco: 123'), false);
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Erro interno', $body);
        self::assertStringNotContainsString('senha do banco', $body);
    }

    public function testGenericPageFollowsTheLanguageOfThePath(): void
    {
        $response = $this->handle('/en/blog', new RuntimeException('falha'), false);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Internal error', (string) $response->getBody());
    }

    public function testUnexpectedErrorShowsDetailsInDevelopment(): void
    {
        $response = $this->handle('/', new RuntimeException('falha de teste'), true);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('falha de teste', (string) $response->getBody());
    }

    private function handle(string $path, RuntimeException $exception, bool $displayErrorDetails): ResponseInterface
    {
        $rootDir = dirname(__DIR__, 2);
        $config = new Config($rootDir, 'development', 'http://localhost:8080');

        $twig = Twig::create($rootDir . '/templates');
        $twig->addExtension(new AssetExtension($config));

        $localizerFactory = new LocalizerFactory(new InterfaceTexts($rootDir . '/lang'), new UrlGenerator($config));

        $handler = new HttpErrorHandler(
            new CallableResolver(),
            new ResponseFactory(),
            $twig,
            $localizerFactory,
            $config,
            new ContentRepository($config),
        );

        $requestFactory = new ServerRequestFactory();
        $request = $requestFactory->createServerRequest('GET', $path);

        return $handler($request, $exception, $displayErrorDetails, false, false);
    }
}
