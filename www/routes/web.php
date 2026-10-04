<?php

declare(strict_types=1);

use App\Controller\BlogController;
use App\Controller\HomeController;
use App\Controller\SitemapController;
use App\I18n\Locale;
use Slim\App;

return function (App $app): void {
    foreach (Locale::cases() as $locale) {
        $app->get($locale->homePath(), [HomeController::class, 'index']);
        $app->get($locale->pathPrefix() . '/blog', [BlogController::class, 'index']);
        $app->get($locale->pathPrefix() . '/blog/tag/{tag}', [BlogController::class, 'tag']);
        $app->get($locale->pathPrefix() . '/blog/{slug}', [BlogController::class, 'show']);
    }

    // Um só, sem prefixo de idioma: cobre as duas versões no mesmo arquivo.
    $app->get('/sitemap.xml', [SitemapController::class, 'sitemap']);
    $app->get('/robots.txt', [SitemapController::class, 'robots']);
};
