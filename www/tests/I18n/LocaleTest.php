<?php

declare(strict_types=1);

namespace App\Tests\I18n;

use App\I18n\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testPortugueseIsTheDefaultForPathsWithoutPrefix(): void
    {
        self::assertSame(Locale::Pt, Locale::fromPath('/'));
        self::assertSame(Locale::Pt, Locale::fromPath('/blog'));
        self::assertSame(Locale::Pt, Locale::fromPath('/blog/meu-artigo'));
    }

    public function testEnglishIsDetectedByThePrefix(): void
    {
        self::assertSame(Locale::En, Locale::fromPath('/en'));
        self::assertSame(Locale::En, Locale::fromPath('/en/blog'));
    }

    public function testPrefixMustBeAWholeSegment(): void
    {
        self::assertSame(Locale::Pt, Locale::fromPath('/entrevista'));
        self::assertSame(Locale::Pt, Locale::fromPath('/blog/en'));
    }

    public function testOtherSwitchesBetweenTheTwoLanguages(): void
    {
        self::assertSame(Locale::En, Locale::Pt->other());
        self::assertSame(Locale::Pt, Locale::En->other());
    }
}
