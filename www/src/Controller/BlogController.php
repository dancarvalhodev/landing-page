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
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));

        return $this->render($request, $response, 'blog/index.html.twig', [
            'posts' => $this->search($this->content->posts($locale), $query),
            'q' => $query,
            'switchUrl' => $this->urls->path($locale->other(), '/blog'),
        ]);
    }

    public function tag(ServerRequestInterface $request, ResponseInterface $response, string $tag): ResponseInterface
    {
        $locale = $this->locale($request);

        $posts = array_values(array_filter(
            $this->content->posts($locale),
            fn(array $post): bool => in_array($tag, $post['tags'] ?? [], true),
        ));

        return $this->render($request, $response, 'blog/tag.html.twig', [
            'tag' => $tag,
            'posts' => $posts,
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
     * @param list<array<mixed>> $posts
     * @return list<array<mixed>>
     */
    private function search(array $posts, string $query): array
    {
        if ($query === '') {
            return $posts;
        }

        return array_values(array_filter($posts, function (array $post) use ($query): bool {
            $haystack = $post['title'] . ' ' . $post['description'] . ' ' . implode(' ', $post['tags'] ?? []);

            return mb_stripos($haystack, $query) !== false;
        }));
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
