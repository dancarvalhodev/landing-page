<?php

declare(strict_types=1);

namespace App\Tests\I18n;

use App\Config;
use App\I18n\Locale;
use App\I18n\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    private UrlGenerator $urls;

    protected function setUp(): void
    {
        $this->urls = new UrlGenerator(new Config('/app', 'production', 'https://meusite.com'));
    }

    public function testHomePathHasNoTrailingSlashInEnglish(): void
    {
        self::assertSame('/', $this->urls->path(Locale::Pt));
        self::assertSame('/en', $this->urls->path(Locale::En));
    }

    public function testInnerPathsGetTheLanguagePrefix(): void
    {
        self::assertSame('/blog', $this->urls->path(Locale::Pt, '/blog'));
        self::assertSame('/en/blog', $this->urls->path(Locale::En, '/blog'));
    }

    public function testSlashesAroundThePathAreNormalized(): void
    {
        self::assertSame('/en/blog/tag/php', $this->urls->path(Locale::En, 'blog/tag/php/'));
    }

    public function testAbsoluteUrlStartsWithTheSiteUrl(): void
    {
        self::assertSame('https://meusite.com/', $this->urls->absolute(Locale::Pt));
        self::assertSame('https://meusite.com/en/blog', $this->urls->absolute(Locale::En, '/blog'));
    }
}
