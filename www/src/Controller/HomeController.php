<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeController extends AbstractController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $projects = $this->content->projects($locale);
        $site = $this->content->site();
        $profile = $this->content->profile($locale);

        $personJsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $site['name'],
            'jobTitle' => $profile['role'],
            'email' => 'mailto:' . $site['email'],
            'url' => $this->urls->absolute($locale, '/'),
            'sameAs' => array_column($site['social'], 'url'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->render($request, $response, 'home/index.html.twig', [
            'projects' => $projects,
            'projectCategories' => array_values(array_unique(array_column($projects, 'category'))),
            // A home existe sempre nos dois idiomas, então sempre tem par de hreflang.
            'altUrl' => $this->urls->absolute($locale->other(), '/'),
            'personJsonLd' => $personJsonLd,
        ]);
    }
}
