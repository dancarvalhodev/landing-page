<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use RuntimeException;

final readonly class Config
{
    private const array ENVIRONMENTS = ['development', 'preview', 'production'];

    public function __construct(
        public string $rootDir,
        public string $env,
    ) {
        if (!in_array($env, self::ENVIRONMENTS, true)) {
            $accepted = implode(', ', self::ENVIRONMENTS);

            throw new InvalidArgumentException("APP_ENV inválido: \"{$env}\". Valores aceitos: {$accepted}.");
        }
    }

    public static function fromEnv(string $rootDir): self
    {
        $env = getenv('APP_ENV');

        if ($env === false) {
            throw new RuntimeException('A variável de ambiente APP_ENV é obrigatória.');
        }

        return new self($rootDir, $env);
    }

    public function isDevelopment(): bool
    {
        return $this->env === 'development';
    }
}
