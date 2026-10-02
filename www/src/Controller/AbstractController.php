<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;

abstract class AbstractController
{
    public function __construct(private readonly Twig $twig) {}

    /**
     * @param array<string, mixed> $data
     */
    protected function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $response = $this->twig->render($response, $template, $data);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
