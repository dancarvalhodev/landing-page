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
            new TwigFunction('asset_absolute', $this->absoluteAssetUrl(...)),
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

    /**
     * Com domínio e esquema: para quando quem lê o endereço não está numa página nossa
     * (og:image, por exemplo — lido por um servidor de fora, sem "relativo a quê?").
     */
    public function absoluteAssetUrl(string $path): string
    {
        return $this->config->siteUrl . $this->assetUrl($path);
    }
}
