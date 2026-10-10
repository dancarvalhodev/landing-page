<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use RuntimeException;

final readonly class Config
{
    private const array ENVIRONMENTS = ['development', 'preview', 'production'];

    public string $siteUrl;

    public function __construct(
        public string $rootDir,
        public string $env,
        string $siteUrl,
        public ?string $assetVersion = null,
    ) {
        if (!in_array($env, self::ENVIRONMENTS, true)) {
            $accepted = implode(', ', self::ENVIRONMENTS);

            throw new InvalidArgumentException("APP_ENV inválido: \"{$env}\". Valores aceitos: {$accepted}.");
        }

        $isHttpUrl = str_starts_with($siteUrl, 'http://') || str_starts_with($siteUrl, 'https://');

        if (!$isHttpUrl || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("SITE_URL inválido: \"{$siteUrl}\". Exemplo: https://meusite.com");
        }

        $this->siteUrl = rtrim($siteUrl, '/');
    }

    public static function fromEnv(string $rootDir): self
    {
        return new self(
            $rootDir,
            self::requiredEnv('APP_ENV'),
            self::requiredEnv('SITE_URL'),
            self::optionalEnv('ASSET_VERSION'),
        );
    }

    public function isDevelopment(): bool
    {
        return $this->env === 'development';
    }

    private static function requiredEnv(string $name): string
    {
        $value = getenv($name);

        if ($value === false) {
            throw new RuntimeException("A variável de ambiente {$name} é obrigatória.");
        }

        return $value;
    }

    private static function optionalEnv(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }
}
