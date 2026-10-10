<?php

declare(strict_types=1);

namespace App\Tests\I18n;

use App\I18n\InterfaceTexts;
use App\I18n\Locale;
use PHPUnit\Framework\TestCase;

final class InterfaceTextsTest extends TestCase
{
    private InterfaceTexts $texts;

    protected function setUp(): void
    {
        $this->texts = new InterfaceTexts(dirname(__DIR__, 2) . '/lang');
    }

    public function testReadsTheTextOfAKeyInEachLanguage(): void
    {
        self::assertSame('Página não encontrada', $this->texts->textFor(Locale::Pt, 'error.404.title'));
        self::assertSame('Page not found', $this->texts->textFor(Locale::En, 'error.404.title'));
    }

    public function testUnknownKeyIsReturnedAsIs(): void
    {
        self::assertSame('chave.inexistente', $this->texts->textFor(Locale::Pt, 'chave.inexistente'));
    }

    public function testEveryLanguageHasTheSameKeys(): void
    {
        $portugueseKeys = array_keys($this->texts->textsFor(Locale::Pt));
        $englishKeys = array_keys($this->texts->textsFor(Locale::En));

        sort($portugueseKeys);
        sort($englishKeys);

        self::assertSame($portugueseKeys, $englishKeys);
    }
}
