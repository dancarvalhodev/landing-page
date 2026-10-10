# Do arquivo Markdown ao artigo na tela — slug, carregamento e tradução

Arquivos explicados: `www/src/Content/ContentRepository.php`, `www/src/Controller/BlogController.php`
e os arquivos em `www/content/blog/{pt,en}/`.

Pense numa **estante com duas prateleiras**, uma com etiqueta "pt" e outra com etiqueta "en". Cada
arquivo `.md` é uma pasta guardada numa das prateleiras. As duas prateleiras não sabem nada uma da
outra — quem liga uma pasta de um lado a uma pasta do outro é só uma etiqueta escrita dentro de cada
uma, não a posição na estante nem o nome da pasta.

## O slug é o nome do arquivo, só isso

```php
// ContentRepository::readFile()
$item['slug'] = basename($file, '.md');
```

Não existe um campo "slug" na etiqueta YAML do topo do arquivo. O nome do arquivo em si **é** o
endereço do artigo:

| Arquivo | Slug | Endereço |
|---|---|---|
| `www/content/blog/pt/ola-mundo.md` | `ola-mundo` | `/blog/ola-mundo` |
| `www/content/blog/en/hello-world.md` | `hello-world` | `/en/blog/hello-world` |

Por isso renomear o arquivo muda a URL do artigo — vale lembrar disso antes de renomear um artigo já
publicado, porque quebra links antigos (que apontavam pro nome velho).

## Como os artigos são carregados (`ContentRepository::posts()`)

```php
public function posts(Locale $locale): array
{
    $posts = $this->readFolder('blog/' . $locale->value);
    usort($posts, fn(array $a, array $b): int => $b['date'] <=> $a['date']);
    return $posts;
}
```

`readFolder()` é só um `glob()` na pasta certa (`blog/pt` ou `blog/en`) seguido de um `readFile()`
para cada arquivo `.md` encontrado — não existe uma lista de artigos guardada em outro lugar; a
pasta **é** a lista. Depois de montar todos, ordena do mais novo pro mais antigo pela data.

Isso roda a cada requisição, sem cache (decisão do MVP, ver `.claude/plans/arquitetura-landing-page.md`,
seção "Conteúdo (MVP)"): adicionar um arquivo na pasta já é "publicar" um artigo novo, sem precisar
avisar o código em nenhum outro lugar.

## Como um post é montado (`ContentRepository::readFile()`)

Cada arquivo tem duas partes: a etiqueta (YAML, entre `---`) e a carta (o corpo, em Markdown). Uma
chamada só, `$this->markdown->convert()`, lê as duas de uma vez:

```php
$result = $this->markdown->convert((string) file_get_contents($file));

$item = [];
if ($result instanceof RenderedContentWithFrontMatter) {
    $item = (array) $result->getFrontMatter(); // os campos da etiqueta: title, description, date, tags...
}

$item['slug'] = basename($file, '.md');  // o nome do arquivo
$item['html'] = $result->getContent();   // o corpo, já convertido de Markdown pra HTML
```

O array final de um post é: **tudo que estava na etiqueta YAML**, mais `slug` e `html` adicionados
por fora. Nenhum campo é validado aqui — quem garante que `title`, `description` e `date` existem é
o teste `ContentRepositoryTest::testEveryPostHasTheFieldsThePagesNeed()`, que lê o conteúdo real do
repositório. Um artigo sem um campo obrigatório derruba o CI, não a página em produção.

### Uma pegadinha: `date` vira número, não texto

```php
var_dump($post['date']); // int(1790899200), não "2026-10-02"
```

A biblioteca de YAML (`symfony/yaml`) reconhece `2026-10-02` como uma data e converte sozinha para
um timestamp Unix (um inteiro). Por isso os templates formatam a data com o filtro `date` do Twig
(`post.date|date('d/m/Y')`), que aceita esse formato direto — nunca tratem `post.date` como string.

## A questão da tradução: `translation_key`, não o slug

As duas prateleiras (pt/en) não combinam os artigos pelo nome do arquivo — os nomes podem (e aqui,
de propósito, foram escolhidos para) ser diferentes: `ola-mundo.md` de um lado, `hello-world.md` do
outro. O que liga os dois é um campo da etiqueta, igual nos dois arquivos:

```yaml
# blog/pt/ola-mundo.md
translation_key: ola-mundo
```
```yaml
# blog/en/hello-world.md
translation_key: ola-mundo
```

Quando alguém troca de idioma dentro de um artigo, o `BlogController` não troca o endereço na marra
(trocar `/blog/ola-mundo` por `/en/blog/ola-mundo` quebraria, porque esse arquivo não existe em
inglês). Em vez disso, ele procura, entre os posts do outro idioma, um com a mesma `translation_key`
e devolve o slug dele, ou `null` se não houver tradução:

```php
private function translatedSlugFor(Locale $otherLocale, array $post): ?string
{
    foreach ($this->content->posts($otherLocale) as $candidate) {
        if (($candidate['translation_key'] ?? null) === ($post['translation_key'] ?? null)) {
            return $candidate['slug'];
        }
    }

    return null;
}
```

O `show()` chama esse método uma vez e usa o resultado em dois lugares, com regras diferentes:

```php
$translatedSlug = $this->translatedSlugFor($other, $post);

'switchUrl' => $translatedSlug !== null
    ? $this->urls->path($other, '/blog/' . $translatedSlug)
    : $this->urls->path($other, '/blog'),   // sem tradução: cai no índice do outro idioma
'altUrl' => $translatedSlug !== null
    ? $this->urls->absolute($other, '/blog/' . $translatedSlug)
    : null,                                 // sem tradução: não existe par de hreflang
```

Achou um post com a mesma etiqueta: monta a URL com o **slug daquele outro arquivo** (não do atual).
Não achou (o artigo ainda não foi traduzido): o link de idioma leva para o índice do blog no outro
idioma, em vez de dar 404 ou linkar para algo que não existe. Já o `altUrl`, que vira a etiqueta
`hreflang`, fica vazio: para o Google não se pode fingir uma tradução (ver `seo.md`).

Essa é a mesma ideia do `translationKey` descrita no plano de arquitetura, lá na seção "i18n":
"leva à tradução se existir, senão ao índice do blog do outro idioma".

## Regras práticas

- **Um artigo novo** é só um arquivo `.md` novo na pasta certa — o slug nasce do nome do arquivo,
  sem precisar editar nenhum outro lugar do código.
- **Para ligar duas traduções**, use o mesmo `translation_key` nos dois arquivos — os nomes de
  arquivo (e os slugs) não precisam, e normalmente não vão, ser iguais.
- **Um artigo sem tradução ainda funciona**: só não existe o link direto; quem troca de idioma cai
  no índice do blog, não numa página quebrada.
- **`date` chega como número** (timestamp), não como texto — sempre formate com o filtro `date` do
  Twig, nunca compare ou exiba como string direto.
