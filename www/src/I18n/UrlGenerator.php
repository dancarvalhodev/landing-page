<?php

declare(strict_types=1);

namespace App\I18n;

use App\Config;

final readonly class UrlGenerator
{
    public function __construct(private Config $config) {}

    /**
     * @param string $path caminho sem o prefixo do idioma, ex.: /blog
     */
    public function path(Locale $locale, string $path = '/'): string
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return $locale->homePath();
        }

        return $locale->pathPrefix() . $path;
    }

    public function absolute(Locale $locale, string $path = '/'): string
    {
        return $this->config->siteUrl . $this->path($locale, $path);
    }
}
