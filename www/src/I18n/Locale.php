<?php

declare(strict_types=1);

namespace App\I18n;

enum Locale: string
{
    case Pt = 'pt';
    case En = 'en';

    public static function fromPath(string $path): self
    {
        foreach (self::cases() as $locale) {
            $prefix = $locale->pathPrefix();

            if ($prefix === '') {
                continue;
            }

            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $locale;
            }
        }

        return self::Pt;
    }

    public function pathPrefix(): string
    {
        return match ($this) {
            self::Pt => '',
            self::En => '/en',
        };
    }

    public function homePath(): string
    {
        return match ($this) {
            self::Pt => '/',
            self::En => '/en',
        };
    }

    public function htmlLang(): string
    {
        return match ($this) {
            self::Pt => 'pt-BR',
            self::En => 'en',
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Pt => self::En,
            self::En => self::Pt,
        };
    }
}
