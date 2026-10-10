<?php

declare(strict_types=1);

namespace App\I18n;

use Psr\Http\Message\ServerRequestInterface;

final readonly class LocalizerFactory
{
    public function __construct(
        private InterfaceTexts $texts,
        private UrlGenerator $urls,
    ) {}

    public function fromRequest(ServerRequestInterface $request): Localizer
    {
        $locale = Locale::fromPath($request->getUri()->getPath());

        return new Localizer($locale, $this->texts, $this->urls);
    }
}
