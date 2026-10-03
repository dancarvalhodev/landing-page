<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Config;
use App\Content\ContentRepository;
use App\I18n\Locale;
use PHPUnit\Framework\TestCase;

final class ContentRepositoryTest extends TestCase
{
    private ContentRepository $content;

    protected function setUp(): void
    {
        $this->content = new ContentRepository(new Config(dirname(__DIR__, 2), 'production', 'https://meusite.com'));
    }

    public function testReadsSiteAndProfile(): void
    {
        self::assertSame('Daniel Carvalho', $this->content->site()['name']);

        $profile = $this->content->profile(Locale::En);

        self::assertIsString($profile['role']);
        self::assertStringContainsString('<p>I am a web developer', (string) $profile['html']);
    }

    public function testEveryPostHasTheFieldsThePagesNeed(): void
    {
        foreach (Locale::cases() as $locale) {
            foreach ($this->content->posts($locale) as $post) {
                self::assertArrayHasKey('title', $post, "artigo {$post['slug']} sem title");
                self::assertArrayHasKey('description', $post, "artigo {$post['slug']} sem description");
                self::assertArrayHasKey('date', $post, "artigo {$post['slug']} sem date");
            }
        }
    }

    public function testEveryLanguageHasProjectsWithTheFieldsThePagesNeed(): void
    {
        foreach (Locale::cases() as $locale) {
            $projects = $this->content->projects($locale);

            self::assertNotEmpty($projects);

            foreach ($projects as $project) {
                self::assertArrayHasKey('title', $project, "projeto {$project['slug']} sem title");
                self::assertArrayHasKey('summary', $project, "projeto {$project['slug']} sem summary");
                self::assertArrayHasKey('order', $project, "projeto {$project['slug']} sem order");
            }
        }
    }

    public function testHtmlWrittenInMarkdownIsShownAsText(): void
    {
        $rootDir = sys_get_temp_dir() . '/landing-page-test-' . uniqid();
        $file = $rootDir . '/content/blog/pt/teste.md';
        mkdir(dirname($file), 0o777, true);
        file_put_contents($file, "<img src=x onerror=alert(1)>\n\n[perigoso](javascript:alert(1)) [seguro](https://exemplo.com)");

        try {
            $content = new ContentRepository(new Config($rootDir, 'production', 'https://meusite.com'));
            $html = (string) $content->posts(Locale::Pt)[0]['html'];
        } finally {
            unlink($file);
            rmdir(dirname($file));
            rmdir(dirname($file, 2));
            rmdir(dirname($file, 3));
            rmdir($rootDir);
        }

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<a href="https://exemplo.com">seguro</a>', $html);
    }

    public function testPostBodyBecomesHtml(): void
    {
        $post = $this->content->posts(Locale::Pt)[0];

        self::assertSame('ola-mundo', $post['slug']);
        self::assertStringContainsString('<code class="language-php">', (string) $post['html']);
    }
}
