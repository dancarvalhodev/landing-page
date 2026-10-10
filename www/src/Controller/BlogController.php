<?php

declare(strict_types=1);

namespace App\Controller;

use App\I18n\Locale;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

final class BlogController extends AbstractController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));

        return $this->render($request, $response, 'blog/index.html.twig', [
            'posts' => $this->search($this->content->posts($locale), $query),
            'q' => $query,
            'switchUrl' => $this->urls->path($locale->other(), '/blog'),
            // A lista existe sempre nos dois idiomas, então sempre tem par de hreflang.
            'altUrl' => $this->urls->absolute($locale->other(), '/blog'),
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

        $other = $locale->other();
        $translatedSlug = $this->translatedSlugFor($other, $post);

        return $this->render($request, $response, 'blog/show.html.twig', [
            'post' => $post,
            // Sem tradução, o link de idioma cai no índice do blog do outro idioma.
            'switchUrl' => $translatedSlug !== null
                ? $this->urls->path($other, '/blog/' . $translatedSlug)
                : $this->urls->path($other, '/blog'),
            // hreflang não aceita esse mesmo fallback: sem tradução de verdade, não existe par.
            'altUrl' => $translatedSlug !== null
                ? $this->urls->absolute($other, '/blog/' . $translatedSlug)
                : null,
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
    private function translatedSlugFor(Locale $otherLocale, array $post): ?string
    {
        foreach ($this->content->posts($otherLocale) as $candidate) {
            if (($candidate['translation_key'] ?? null) === ($post['translation_key'] ?? null)) {
                return $candidate['slug'];
            }
        }

        return null;
    }
}
