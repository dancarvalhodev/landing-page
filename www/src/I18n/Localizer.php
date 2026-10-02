<?php

declare(strict_types=1);

namespace App\I18n;

final readonly class Localizer
{
    public function __construct(
        public Locale $locale,
        private InterfaceTexts $texts,
        private UrlGenerator $urls,
    ) {}

    public function text(string $key): string
    {
        return $this->texts->textFor($this->locale, $key);
    }

    public function url(string $path = '/'): string
    {
        return $this->urls->path($this->locale, $path);
    }

    public function switchUrl(string $path = '/'): string
    {
        return $this->urls->path($this->locale->other(), $path);
    }

    public function htmlLang(): string
    {
        return $this->locale->htmlLang();
    }

    public function switchHtmlLang(): string
    {
        return $this->locale->other()->htmlLang();
    }
}
