<?php

declare(strict_types=1);

namespace App\I18n;

final class InterfaceTexts
{
    /** @var array<string, array<string, string>> */
    private array $texts = [];

    public function __construct(private readonly string $langDir) {}

    /**
     * Uma chave sem texto volta como a própria chave, para o erro aparecer na página sem derrubá-la.
     */
    public function textFor(Locale $locale, string $key): string
    {
        $texts = $this->textsFor($locale);

        return $texts[$key] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    public function textsFor(Locale $locale): array
    {
        if (!isset($this->texts[$locale->value])) {
            $this->texts[$locale->value] = require $this->langDir . '/' . $locale->value . '.php';
        }

        return $this->texts[$locale->value];
    }
}
