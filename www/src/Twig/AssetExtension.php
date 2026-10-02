<?php

declare(strict_types=1);

namespace App\Twig;

use App\Config;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AssetExtension extends AbstractExtension
{
    public function __construct(private readonly Config $config) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', $this->assetUrl(...)),
        ];
    }

    /**
     * O Caddy manda o navegador guardar /assets por um ano; o ?v= muda quando o arquivo muda.
     */
    public function assetUrl(string $path): string
    {
        $url = '/assets/' . ltrim($path, '/');
        $file = $this->config->rootDir . '/public' . $url;

        if (!is_file($file)) {
            return $url;
        }

        return $url . '?v=' . filemtime($file);
    }
}
