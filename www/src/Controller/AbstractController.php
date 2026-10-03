<?php

declare(strict_types=1);

namespace App\Controller;

use App\I18n\Locale;
use App\I18n\LocalizerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

abstract class AbstractController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly LocalizerFactory $localizerFactory,
    ) {}

    protected function locale(ServerRequestInterface $request): Locale
    {
        return $this->localizerFactory->fromRequest($request)->locale;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $template,
        array $data = [],
    ): ResponseInterface {
        $data['i18n'] = $this->localizerFactory->fromRequest($request);

        $response = $this->twig->render($response, $template, $data);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
