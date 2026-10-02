<?php

declare(strict_types=1);

namespace App\Tests;

use App\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    private string|false $originalEnv;

    protected function setUp(): void
    {
        $this->originalEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv('APP_ENV');

            return;
        }

        putenv('APP_ENV=' . $this->originalEnv);
    }

    public function testReadsEnvironmentFromAppEnv(): void
    {
        putenv('APP_ENV=preview');

        $config = Config::fromEnv('/app');

        self::assertSame('preview', $config->env);
        self::assertSame('/app', $config->rootDir);
    }

    public function testMissingAppEnvThrows(): void
    {
        putenv('APP_ENV');

        $this->expectException(RuntimeException::class);

        Config::fromEnv('/app');
    }

    public function testUnknownEnvironmentThrows(): void
    {
        putenv('APP_ENV=prod');

        $this->expectException(InvalidArgumentException::class);

        Config::fromEnv('/app');
    }

    public function testEmptyEnvironmentThrows(): void
    {
        putenv('APP_ENV=');

        $this->expectException(InvalidArgumentException::class);

        Config::fromEnv('/app');
    }

    public function testOnlyDevelopmentIsDevelopment(): void
    {
        $development = new Config('/app', 'development');
        $preview = new Config('/app', 'preview');
        $production = new Config('/app', 'production');

        self::assertTrue($development->isDevelopment());
        self::assertFalse($preview->isDevelopment());
        self::assertFalse($production->isDevelopment());
    }
}
