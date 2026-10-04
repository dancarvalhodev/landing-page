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

        return $this->render($request, $response, 'home/index.html.twig', [
            'projects' => $projects,
            'projectCategories' => array_values(array_unique(array_column($projects, 'category'))),
            // A home existe sempre nos dois idiomas, então sempre tem par de hreflang.
            'altUrl' => $this->urls->absolute($locale->other(), '/'),
        ]);
    }
}
