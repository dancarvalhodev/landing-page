<?php

declare(strict_types=1);

namespace App\Tests;

use App\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    private const array VARIABLES = ['APP_ENV', 'SITE_URL'];

    /** @var array<string, string|false> */
    private array $originalValues = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->originalValues[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);

                continue;
            }

            putenv($name . '=' . $value);
        }
    }

    public function testReadsValuesFromEnvironment(): void
    {
        putenv('APP_ENV=preview');
        putenv('SITE_URL=https://preview.meusite.com');

        $config = Config::fromEnv('/app');

        self::assertSame('/app', $config->rootDir);
        self::assertSame('preview', $config->env);
        self::assertSame('https://preview.meusite.com', $config->siteUrl);
    }

    public function testMissingAppEnvThrows(): void
    {
        putenv('APP_ENV');
        putenv('SITE_URL=https://meusite.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV');

        Config::fromEnv('/app');
    }

    public function testMissingSiteUrlThrows(): void
    {
        putenv('APP_ENV=production');
        putenv('SITE_URL');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SITE_URL');

        Config::fromEnv('/app');
    }

    public function testUnknownEnvironmentThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Config('/app', 'prod', 'https://meusite.com');
    }

    public function testEmptyEnvironmentThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Config('/app', '', 'https://meusite.com');
    }

    public function testSiteUrlMustBeAnHttpUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Config('/app', 'production', 'meusite.com');
    }

    public function testSiteUrlLosesTrailingSlash(): void
    {
        $config = new Config('/app', 'production', 'https://meusite.com/');

        self::assertSame('https://meusite.com', $config->siteUrl);
    }

    public function testOnlyDevelopmentIsDevelopment(): void
    {
        $development = new Config('/app', 'development', 'http://localhost:8080');
        $preview = new Config('/app', 'preview', 'https://preview.meusite.com');
        $production = new Config('/app', 'production', 'https://meusite.com');

        self::assertTrue($development->isDevelopment());
        self::assertFalse($preview->isDevelopment());
        self::assertFalse($production->isDevelopment());
    }
}
