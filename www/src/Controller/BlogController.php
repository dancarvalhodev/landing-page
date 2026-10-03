<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\ContentRepository;
use App\I18n\Locale;
use App\I18n\LocalizerFactory;
use App\I18n\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class BlogController extends AbstractController
{
    public function __construct(
        Twig $twig,
        LocalizerFactory $localizerFactory,
        private readonly ContentRepository $content,
        private readonly UrlGenerator $urls,
    ) {
        parent::__construct($twig, $localizerFactory);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);

        return $this->render($request, $response, 'blog/index.html.twig', [
            'posts' => $this->content->posts($locale),
            'switchUrl' => $this->urls->path($locale->other(), '/blog'),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, string $slug): ResponseInterface
    {
        $locale = $this->locale($request);
        $post = $this->findBySlug($locale, $slug);

        if ($post === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->render($request, $response, 'blog/show.html.twig', [
            'post' => $post,
            'switchUrl' => $this->switchUrlFor($locale, $post),
        ]);
    }

    /**
     * @return array<mixed>|null
     */
    private function findBySlug(Locale $locale, string $slug): ?array
    {
        foreach ($this->content->posts($locale) as $post) {
            if ($post['slug'] === $slug) {
                return $post;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $post
     */
    private function switchUrlFor(Locale $locale, array $post): string
    {
        $other = $locale->other();

        foreach ($this->content->posts($other) as $candidate) {
            if (($candidate['translation_key'] ?? null) === ($post['translation_key'] ?? null)) {
                return $this->urls->path($other, '/blog/' . $candidate['slug']);
            }
        }

        return $this->urls->path($other, '/blog');
    }
}
