<?php

declare(strict_types=1);

namespace App\Handler;

use App\Config;
use App\Content\ContentRepository;
use App\I18n\LocalizerFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Views\Twig;

final class HttpErrorHandler extends ErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        private readonly Twig $twig,
        private readonly LocalizerFactory $localizerFactory,
        private readonly Config $config,
        private readonly ContentRepository $content,
    ) {
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function respond(): ResponseInterface
    {
        if ($this->exception instanceof HttpNotFoundException) {
            return $this->errorPage(404, 'errors/404.html.twig');
        }

        if ($this->exception instanceof HttpException) {
            return parent::respond();
        }

        if ($this->displayErrorDetails) {
            return parent::respond();
        }

        return $this->errorPage(500, 'errors/500.html.twig');
    }

    protected function writeToErrorLog(): void
    {
        // 404 e 405 são tráfego normal (robôs, links quebrados); registrá-los encheria o log.
        if ($this->exception instanceof HttpException) {
            return;
        }

        parent::writeToErrorLog();
    }

    private function errorPage(int $status, string $template): ResponseInterface
    {
        $i18n = $this->localizerFactory->fromRequest($this->request);

        $data = [
            'i18n' => $i18n,
            'site' => $this->content->site(),
            'profile' => $this->content->profile($i18n->locale),
            'canonical' => $this->config->siteUrl . $this->request->getUri()->getPath(),
        ];

        $response = $this->responseFactory->createResponse($status);
        $response = $this->twig->render($response, $template, $data);

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
