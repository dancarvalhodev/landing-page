<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Config;
use App\Twig\AssetExtension;
use PHPUnit\Framework\TestCase;

final class AssetExtensionTest extends TestCase
{
    private AssetExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AssetExtension(new Config(dirname(__DIR__, 2), 'development', 'http://localhost:8080'));
    }

    public function testExistingFileGetsVersionFromModificationTime(): void
    {
        $file = dirname(__DIR__, 2) . '/public/assets/fonts/LICENSES.md';

        self::assertSame(
            '/assets/fonts/LICENSES.md?v=' . filemtime($file),
            $this->extension->assetUrl('fonts/LICENSES.md'),
        );
    }

    public function testMissingFileKeepsPlainUrl(): void
    {
        self::assertSame('/assets/nao-existe.css', $this->extension->assetUrl('/nao-existe.css'));
    }
}
