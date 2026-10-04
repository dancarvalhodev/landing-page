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
