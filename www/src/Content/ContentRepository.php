<?php

declare(strict_types=1);

namespace App\Content;

use App\Config;
use App\I18n\Locale;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\HighlightExtension;

final readonly class ContentRepository
{
    private string $folder;
    private MarkdownConverter $markdown;

    public function __construct(Config $config)
    {
        $this->folder = $config->rootDir . '/content';

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HighlightExtension());

        $this->markdown = new MarkdownConverter($environment);
    }

    /**
     * @return array<mixed> nome, e-mail e redes sociais
     */
    public function site(): array
    {
        return $this->readFile($this->folder . '/site.md');
    }

    /**
     * @return array<mixed> perfil, experiência, habilidades e depoimentos; o "sobre" fica em "html"
     */
    public function profile(Locale $locale): array
    {
        return $this->readFile($this->folder . '/' . $locale->value . '.md');
    }

    /**
     * @return list<array<mixed>> do mais novo para o mais antigo
     */
    public function posts(Locale $locale): array
    {
        $posts = $this->readFolder('blog/' . $locale->value);

        usort($posts, fn(array $a, array $b): int => $b['date'] <=> $a['date']);

        return $posts;
    }

    /**
     * @return list<array<mixed>> na ordem do campo "order"
     */
    public function projects(Locale $locale): array
    {
        $projects = $this->readFolder('projects/' . $locale->value);

        usort($projects, fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return $projects;
    }

    /**
     * @return list<array<mixed>>
     */
    private function readFolder(string $folder): array
    {
        $items = [];

        foreach (glob($this->folder . '/' . $folder . '/*.md') ?: [] as $file) {
            $items[] = $this->readFile($file);
        }

        return $items;
    }

    /**
     * @return array<mixed> os campos da etiqueta, mais "slug" (nome do arquivo) e "html" (o corpo)
     */
    private function readFile(string $file): array
    {
        $result = $this->markdown->convert((string) file_get_contents($file));

        $item = [];

        if ($result instanceof RenderedContentWithFrontMatter) {
            $item = (array) $result->getFrontMatter();
        }

        $item['slug'] = basename($file, '.md');
        $item['html'] = $result->getContent();

        return $item;
    }
}
