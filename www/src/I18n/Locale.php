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

    /**
     * O Open Graph usa "pt_BR"/"en_US" (com underscore), diferente do htmlLang() ("pt-BR"/"en").
     */
    public function ogLocale(): string
    {
        return match ($this) {
            self::Pt => 'pt_BR',
            self::En => 'en_US',
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
