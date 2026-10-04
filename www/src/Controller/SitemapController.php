<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Content\ContentRepository;
use App\I18n\Locale;
use App\I18n\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class SitemapController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly Config $config,
        private readonly ContentRepository $content,
        private readonly UrlGenerator $urls,
    ) {}

    public function sitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $urls = [];

        foreach (Locale::cases() as $locale) {
            $urls[] = ['loc' => $this->urls->absolute($locale, '/'), 'lastmod' => null];
            $urls[] = ['loc' => $this->urls->absolute($locale, '/blog'), 'lastmod' => null];

            foreach ($this->content->posts($locale) as $post) {
                $urls[] = [
                    'loc' => $this->urls->absolute($locale, '/blog/' . $post['slug']),
                    'lastmod' => date('Y-m-d', $post['date']),
                ];
            }
        }

        $response = $this->twig->render($response, 'sitemap.xml.twig', ['urls' => $urls]);

        return $response->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(
            "User-agent: *\nAllow: /\n\nSitemap: {$this->config->siteUrl}/sitemap.xml\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
