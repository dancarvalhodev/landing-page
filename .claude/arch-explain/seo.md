# SEO — conceito e código de cada peça (etapa 7)

Este arquivo cresce a cada subetapa da etapa 7 (ver `.claude/plans/arquitetura-landing-page.md`).
Cada seção explica **o que o conceito resolve** antes de mostrar o código — o dev não tem
bagagem em SEO, então aqui a ordem é sempre conceito primeiro.

## Metadados básicos: título, descrição e canonical (etapa 7a)

Três problemas diferentes, cada um com sua peça.

### 1. `<title>` — o nome do produto na vitrine

Pensa no Google como um bibliotecário gigante que organiza bilhões de páginas. Quando alguém
busca "Daniel Carvalho desenvolvedor PHP", o Google mostra uma lista de resultados — e a
**primeira linha azul, clicável**, de cada resultado é exatamente o conteúdo da tag `<title>`.
É também o texto que aparece na aba do navegador.

Se toda página do site tivesse o mesmo título genérico, o Google (e a pessoa buscando) não
teriam como diferenciar a Home do artigo X do artigo Y — pareceria tudo a mesma coisa. Por isso
cada página tem um título específico, terminando com o nome do site (reforça a marca mesmo
quando a pessoa já sabe qual página é):

```twig
{# www/templates/layout/base.html.twig — padrão, se a página não disser nada diferente #}
<title>{% block title %}{{ site.name }}{% endblock %}</title>
```

Cada página sobrescreve esse bloco com algo mais específico:

```twig
{# www/templates/blog/show.html.twig #}
{% block title %}{{ post.title }} · {{ site.name }}{% endblock %}
{# → "Olá, mundo (exemplo) · Daniel Carvalho" #}
```

### 2. `<meta name="description">` — o resumo embaixo do título

Na busca do Google, embaixo da linha azul do título tem um texto cinza menor, resumindo a
página — vem (quase sempre) do `<meta name="description">`. Sem ele, o Google tenta adivinhar
pegando um pedaço qualquer do texto da página, o que fica feio e pouco convidativo.

```twig
{# base.html.twig — padrão: a frase de apresentação do pt.md/en.md #}
<meta name="description" content="{% block description %}{{ profile.intro }}{% endblock %}">
```
```twig
{# blog/show.html.twig — o artigo sobrescreve com a própria descrição #}
{% block description %}{{ post.description }}{% endblock %}
```

Isso não muda o **ranking** (a posição na busca) — muda se a pessoa **clica** ou não, porque é
o texto que ela lê pra decidir "isso é o que eu procuro?".

### 3. `<link rel="canonical">` — "este é o endereço oficial desta página"

O mais abstrato dos três. Problema que resolve: a mesma página pode ser alcançada por mais de um
endereço. Ex.: `/blog?q=php`, `/blog?q=exemplo` e `/blog` são, pro Google, três URLs
**diferentes** — mas o conteúdo "de verdade" (a lista de artigos) é essencialmente o mesmo
lugar. Sem avisar nada, o Google pode achar que são páginas competindo entre si e diluir a
relevância de cada uma entre as três.

O `canonical` é a página dizendo, com todas as letras: "o endereço oficial sou eu, sem a busca,
sem parâmetro extra — aponte a relevância pra aqui":

```php
// www/src/Controller/AbstractController.php, dentro de render()
$data['canonical'] = $this->config->siteUrl . $request->getUri()->getPath();
```
`getUri()->getPath()` devolve só o caminho, **sem a query string** — por isso `/blog?q=exemplo`
aponta o canonical pra `/blog` puro, nunca pra si mesmo com a busca incluída.

```twig
{# base.html.twig — só imprime o valor que o controller calculou #}
<link rel="canonical" href="{{ canonical }}">
```

### Como tudo chega pronto pra cada template

`site`, `profile` e `canonical` são injetados automaticamente em **toda** página — nenhum
controller precisa passar isso à mão:

```php
// AbstractController::render()
$data['site'] = $this->content->site();
$data['profile'] = $this->content->profile($this->locale($request));
$data['canonical'] = $this->config->siteUrl . $request->getUri()->getPath();
```

As páginas de erro (404/500) não passam por `AbstractController` (são tratadas pelo
`HttpErrorHandler`), por isso ele repete essa mesma injeção separadamente, no seu próprio
`errorPage()`.

## hreflang: "esta página tem uma versão equivalente em outro idioma, aqui" (etapa 7b)

O `canonical` resolve "qual é o endereço oficial desta página". O `hreflang` resolve um problema
parecido, mas **entre idiomas**: o site tem o mesmo artigo em português e em inglês — como o
Google sabe que são a mesma coisa, só traduzida, e não dois conteúdos concorrendo entre si? E
mais importante: como ele sabe **qual mostrar** pra cada pessoa (alguém buscando em inglês deve
cair na versão em inglês, não na em português)?

A tag `<link rel="alternate" hreflang="...">` responde isso: "esta página tem uma versão
equivalente, nesse idioma, neste endereço". O Google usa isso pra não tratar PT e EN como
conteúdo duplicado, e pra mostrar automaticamente a versão certa pra quem busca em cada idioma.

**Regra importante, já prevista no plano de arquitetura:** só emitir o par quando a tradução
existe de verdade. Um artigo sem tradução não pode "fingir" ter uma versão em inglês — isso
enganaria o Google. Por isso:

| Página | Tem par de hreflang? |
|---|---|
| Home | Sempre (existe nos dois idiomas) |
| Lista do blog (`/blog`) | Sempre |
| Artigo, com tradução (`translation_key` igual nos dois arquivos) | Sim, apontando pro slug traduzido |
| Artigo, sem tradução | Não |
| Página de tag | Não (tags não têm conceito de tradução, ver `.claude/arch-explain/blog-content.md`) |
| Páginas de erro (404/500) | Não |

### O código

Cada controller decide se existe um equivalente e, se sim, passa `altUrl` (a URL **absoluta** —
o Google exige endereço completo aqui, igual o `canonical`, relativo é ignorado):

```php
// HomeController::index() — a home sempre tem par
'altUrl' => $this->urls->absolute($locale->other(), '/'),
```
```php
// BlogController::show() — só quando a tradução existe de verdade
$translatedSlug = $this->translatedSlugFor($other, $post);

'altUrl' => $translatedSlug !== null
    ? $this->urls->absolute($other, '/blog/' . $translatedSlug)
    : null,
```

Quando `altUrl` não é passado (tag, 404, 500), o `AbstractController::render()` garante que ele
exista mesmo assim, só que como `null` — isso evita que o Twig quebre tentando ler uma variável
que ninguém passou:

```php
// AbstractController::render()
$data['altUrl'] ??= null;
```

E o template só desenha as tags quando existe um valor de verdade — e, quando existe, desenha
**duas**: uma apontando pra própria página (auto-referência, recomendação do Google) e outra
pro equivalente:

```twig
{# base.html.twig #}
{% if altUrl is defined and altUrl %}
    <link rel="alternate" hreflang="{{ i18n.htmlLang }}" href="{{ canonical }}">
    <link rel="alternate" hreflang="{{ i18n.switchHtmlLang }}" href="{{ altUrl }}">
{% endif %}
```

### Por que não reaproveitar o `switchUrl` do cabeçalho

O site já tinha um `switchUrl` (o link "English"/"Português" no topo da página), que também
decide pra onde ir no outro idioma — mas ele **sempre** aponta pra algum lugar, mesmo sem
tradução (cai no índice do blog, pra não deixar a pessoa sem opção). Isso é correto pra um
humano clicando num botão, mas seria uma mentira pro Google: diria que existe uma tradução onde
não existe. Por isso o `BlogController::show()` calcula o slug traduzido **uma vez**
(`translatedSlugFor()`) e usa esse resultado dos dois jeitos — com fallback pro `switchUrl`,
sem fallback pro `altUrl`.

## Open Graph e Twitter Card: o cartão de prévia ao colar o link (etapa 7c)

`<title>` e `<meta description>` controlam como a página aparece **na busca do Google**. Open
Graph controla como ela aparece em outro lugar bem diferente: quando alguém **cola o link** no
WhatsApp, LinkedIn, Slack ou Facebook. Sem essas etiquetas, cada rede tenta adivinhar sozinha —
às vezes pega um pedaço de texto aleatório da página, ou nenhuma imagem, e o link aparece feio.

As etiquetas `og:*` (Open Graph, um padrão aberto que a maioria das redes lê) e `twitter:*`
(formato próprio do X/Twitter) dizem exatamente: "aqui está o título, a descrição e o tipo de
conteúdo que você deve mostrar no cartão".

### Reaproveitar, não duplicar

O título e a descrição do cartão social são **os mesmos** que já aparecem na busca — não faz
sentido ter um texto pro Google e outro pro WhatsApp. Em vez de escrever o texto de novo, uso a
função `block()` do Twig, que lê o conteúdo já resolvido dos blocos `title`/`description`
(da etapa 7a), de dentro do próprio `base.html.twig`:

```twig
{# base.html.twig #}
<meta property="og:title" content="{{ block('title') }}">
<meta property="og:description" content="{{ block('description') }}">
...
<meta name="twitter:title" content="{{ block('title') }}">
<meta name="twitter:description" content="{{ block('description') }}">
```
`block('title')` funciona mesmo chamado do template-pai: o Twig sempre devolve o conteúdo **já
resolvido** daquele bloco — o da página filha, se ela sobrescreveu, ou o padrão, se não.

### `og:type` — "isto é uma página qualquer, ou um artigo?"

Um artigo de blog é um tipo de conteúdo diferente de uma página comum (tem autor, data de
publicação, etc.), e o Open Graph tem uma etiqueta pra marcar isso. O padrão é `"website"`
(qualquer página); o artigo sobrescreve pra `"article"` e ganha uma etiqueta extra, a data de
publicação:

```twig
{# blog/show.html.twig #}
{% block og_type %}article{% endblock %}
{% block article_published_time %}{{ post.date|date('c') }}{% endblock %}
```
`date('c')` é o formato ISO 8601 (`2026-10-02T00:00:00+00:00`) — o formato que o Open Graph (e
quase todo padrão de data na web) espera, diferente do `d/m/Y` que usamos pra mostrar a data pra
humanos lerem.

### `og:locale` e `og:locale:alternate` — reaproveitando o `altUrl` da 7b

`og:locale` diz em qual idioma está o conteúdo (`pt_BR`, `en_US` — note o underscore, diferente
do `pt-BR` do `hreflang`, por isso o `Locale::ogLocale()` é um método separado do `htmlLang()`).
`og:locale:alternate` diz "esse mesmo conteúdo também existe em outro idioma" — e só aparece
quando existe, reaproveitando **exatamente** a mesma condição do `hreflang` (`altUrl`), sem
recalcular nada:

```twig
{% if altUrl is defined and altUrl %}
    <meta property="og:locale:alternate" content="{{ i18n.locale.other.ogLocale }}">
{% endif %}
```

### O que ficou de fora, de propósito

- **`og:image`** — depende da imagem de compartilhamento, que é a etapa 7f (ainda não existe).
  Por enquanto `twitter:card` fica como `"summary"` (cartão simples, sem imagem grande); quando a
  imagem existir, troca pra `"summary_large_image"`.
- **`article:tag`** (uma etiqueta por tag do artigo) — ficou fora por ser um detalhe menor; o
  essencial (título, descrição, tipo, data) já entrega o principal benefício, que é o cartão
  aparecer bonito ao compartilhar o link.

## JSON-LD `Person`: um cartão de identidade que só as máquinas leem (etapa 7d)

Isso é parecido com o Open Graph (metadados que não aparecem pra quem lê a página), mas serve
pra outra coisa: em vez de controlar como o link aparece ao ser **compartilhado**, ajuda o
Google a entender **quem é o dono do site** como uma entidade só — "esta pessoa se chama Daniel
Carvalho, trabalha como Software Engineer, e esses são os outros perfis dela".

É um bloco `<script type="application/ld+json">`, seguindo o vocabulário público
[schema.org](https://schema.org) (Google, Bing e outros concordaram em usar esse mesmo
formato). É o que alimenta aquele quadro de informação que aparece do lado direito da busca
quando você pesquisa uma pessoa ou marca conhecida — e ajuda o Google a ligar os pontos entre o
GitHub e o LinkedIn como **a mesma pessoa**, em vez de três perfis desconectados. É a peça que
mais ajuda diretamente o objetivo "ser encontrado pelo nome" do projeto.

A chave é `sameAs`: uma lista de outros endereços que também são "essa mesma pessoa" — vem do
`site.social` (`www/content/site.md`), já existente desde a etapa 4.

### Por que montar o JSON em PHP, não em Twig

```php
// HomeController::index()
$personJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $site['name'],
    'jobTitle' => $profile['role'],
    'email' => 'mailto:' . $site['email'],
    'url' => $this->urls->absolute($locale, '/'),
    'sameAs' => array_column($site['social'], 'url'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```
`array_column($site['social'], 'url')` pega a lista `[{label: "GitHub", url: "..."}, {label:
"LinkedIn", url: "..."}]` e devolve só os `url`, numa lista simples — o mesmo truque já usado
pra `projectCategories` (etapa 5b). Montar o JSON inteiro em PHP, com `json_encode()`, evita
qualquer manipulação de lista dentro do template: o Twig só recebe a string já pronta.

```twig
{# home/index.html.twig #}
{% block json_ld %}
    <script type="application/ld+json">{{ personJsonLd|raw }}</script>
{% endblock %}
```
O `|raw` aqui é seguro pelo mesmo motivo do `post.html|raw` (etapa 6a): o conteúdo não vem de um
usuário anônimo, vem do nosso próprio `json_encode()` — sem o `raw`, o Twig escaparia as aspas
do JSON (`"` → `&quot;`), quebrando o script.

`{% block json_ld %}{% endblock %}` no `base.html.twig` fica **vazio por padrão** — só a home
sobrescreve, por isso nenhuma outra página (blog, tag, erro) ganha esse `<script>`.

## `sitemap.xml` e `robots.txt`: o mapa do site e as regras pra quem visita primeiro (etapa 7e)

Dois arquivos pra **robôs de busca** (Googlebot, Bingbot), não pra pessoas.

`sitemap.xml` é uma lista de todos os endereços que o site tem, pro Google conhecer de uma vez
— em vez de só descobrir página nova seguindo links (o que funciona, mas é mais lento e pode
deixar passar algo). Cada entrada pode ter um `<lastmod>` (quando mudou por último), o que ajuda
o Google a priorizar revisitar o que mudou.

`robots.txt` é um arquivo de texto simples, na raiz do site, que diz aos robôs "pode visitar
isso" e, o mais importante aqui, "o mapa do site está neste endereço". É um **pedido educado**,
não uma trava de segurança — não impede nada por si só.

**Decisão tomada aqui:** não bloqueei nada no `robots.txt` (`Allow: /` pra tudo). Bloquear
crawling de algo (ex.: páginas de tag, resultados de busca) é uma decisão mais forte e mais
difícil de reverter do que o `canonical` (que só diz "a relevância é de outro lugar", mas ainda
deixa o Google visitar) — fica pra você decidir depois, se quiser restringir.

### O `SitemapController`, separado dos outros

```php
// www/src/Controller/SitemapController.php
final class SitemapController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly Config $config,
        private readonly ContentRepository $content,
        private readonly UrlGenerator $urls,
    ) {}
```
Esse controller **não estende** `AbstractController` como os outros — de propósito. O
`AbstractController::render()` injeta `site`/`profile`/`canonical` e sempre devolve
`Content-Type: text/html`, coisas feitas pra uma **página HTML de um idioma só**. O sitemap não
é nenhuma das duas coisas: não tem HTML, e lista os endereços dos **dois** idiomas no mesmo
arquivo. Por isso ele monta a resposta à mão, com o `Content-Type` certo pra cada caso
(`application/xml` pro sitemap, `text/plain` pro robots.txt).

```php
public function sitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $urls = [];

    foreach (Locale::cases() as $locale) {
        $urls[] = ['loc' => $this->urls->absolute($locale, '/'), 'lastmod' => null];
        $urls[] = ['loc' => $this->urls->absolute($locale, '/blog'), 'lastmod' => null];

        foreach ($this->content->posts($locale) as $post) {
            $urls[] = [
                'loc' => $this->urls->absolute($locale, '/blog/' . $post['slug']),
                'lastmod' => date('Y-m-d', $post['date']),
            ];
        }
    }
    ...
```
Passa pelos dois idiomas (`Locale::cases()`), e em cada um lista a home, a lista do blog e cada
artigo. Só os artigos ganham `lastmod` — vem do `post['date']` (aquele timestamp que vimos em
`.claude/arch-explain/blog-content.md`). Home e lista do blog não têm uma data de "última
alteração" confiável, então ficam sem — inventar uma data seria pior que não ter nenhuma.
Páginas de tag e de busca **não entram** no sitemap, pelo mesmo motivo de não terem `canonical`
próprio: não são conteúdo original, são filtros sobre o conteúdo que já está listado.

```twig
{# www/templates/sitemap.xml.twig #}
<url>
    <loc>{{ url.loc }}</loc>
    {% if url.lastmod %}<lastmod>{{ url.lastmod }}</lastmod>{% endif %}
</url>
```
Essa template não estende o `base.html.twig` — não é uma página HTML, é um documento XML à
parte, autocontido.

```php
public function robots(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    $response->getBody()->write(
        "User-agent: *\nAllow: /\n\nSitemap: {$this->config->siteUrl}/sitemap.xml\n",
    );

    return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8');
}
```
O `robots.txt` usa `$this->config->siteUrl` direto (não precisa do `UrlGenerator`, porque esse
endereço não tem prefixo de idioma) — isso é o que faz o link do `Sitemap:` mudar sozinho entre
`http://localhost:8080` (dev), a prévia e a produção, sem precisar editar nada manualmente.
