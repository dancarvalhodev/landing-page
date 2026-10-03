<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\ContentRepository;
use App\I18n\LocalizerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class HomeController extends AbstractController
{
    public function __construct(
        Twig $twig,
        LocalizerFactory $localizerFactory,
        private readonly ContentRepository $content,
    ) {
        parent::__construct($twig, $localizerFactory);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $projects = $this->content->projects($locale);

        return $this->render($request, $response, 'home/index.html.twig', [
            'site' => $this->content->site(),
            'profile' => $this->content->profile($locale),
            'projects' => $projects,
            'projectCategories' => array_values(array_unique(array_column($projects, 'category'))),
        ]);
    }
}
