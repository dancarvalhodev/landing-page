# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Explorar o código e rodar comandos

- **CodeGraph primeiro.** O repositório é indexado (`.codegraph/` na raiz). Para entender ou localizar código, use `codegraph_explore` (MCP, passando `projectPath`) ou `codegraph explore "<símbolos ou pergunta>"` antes de qualquer `grep`, `find`, `sed` ou leitura de arquivo.
- **Evite comandos compostos.** Nada de `cd ... && sed ...`, `cd ... && grep ...` ou cadeias com `&&`, `;` e `|` juntando ações diferentes. Prefira um comando por chamada, com caminhos absolutos, e as ferramentas dedicadas (Read, Edit, Write) para ler e alterar arquivos.

## Antes de começar

Leia `.claude/handoff.md`: onde o trabalho parou, como retomar em outro computador e como o dev
quer trabalhar (uma etapa por vez, MVP, analogias, git feito por ele). O plano e o progresso estão
em `.claude/plans/arquitetura-landing-page.md`.

## O que é

Site pessoal (portfólio de rolagem única + blog), em português (`/`) e inglês (`/en`). PHP 8.5 com
Slim 4, PHP-DI e Twig; Tailwind CSS 4 pela CLI standalone; Caddy; tudo em Docker. Sem banco de
dados: o conteúdo são arquivos Markdown em `www/content/`. Está no ar, publicado pelo GitHub
Actions em uma VM ARM na Oracle Cloud.

## Comandos

Tudo roda nos contêineres; PHP, Composer e Node não existem no host.

| Para | Comando |
|---|---|
| Subir o ambiente | `docker compose up -d --build` |
| Instalar dependências | `docker compose exec php composer install` |
| Testes | `docker compose exec php composer test` |
| Um teste só | `docker compose exec php vendor/bin/phpunit --filter <nome>` |
| Análise estática | `docker compose exec php composer stan` |
| Estilo (conferir / corrigir) | `docker compose exec php composer cs` / `composer cs:fix` |
| Reiniciar o Caddy (mudou o Caddyfile) | `docker compose restart caddy` |
| Validar os workflows | `docker run --rm -v .:/repo:ro --workdir /repo rhysd/actionlint:latest` |

O site de desenvolvimento fica em `http://localhost:8080`. Ao rodar comandos por ferramenta, use
`docker compose -f /caminho/absoluto/docker-compose.yml exec -T php ...`.

Antes de entregar uma alteração em PHP, rode os três: `composer test`, `composer stan` e
`composer cs`. São os mesmos que o CI roda.

## Arquitetura

### Caminho de uma requisição

Caddy (`config/caddy/Caddyfile`) entrega o que existe em `www/public/`; o resto vai por FastCGI a
`public/index.php` → `App\Bootstrap\Main` → middlewares (erros por fora, depois
`CanonicalPathMiddleware`) → rota em `routes/web.php` → controller → template Twig.

### Peças principais (`www/src/`)

- **`Config`**: `rootDir`, `env` (`APP_ENV`), `siteUrl` (`SITE_URL`) e `assetVersion`
  (`ASSET_VERSION`, opcional). `APP_ENV` e `SITE_URL` são obrigatórios e validados; o app não sobe
  sem eles.
- **`Content\ContentRepository`**: a única classe que lê `content/`. Lê do disco a cada
  requisição, sem cache, converte o Markdown e devolve arrays simples (`site()`, `profile()`,
  `posts()`, `projects()`). O `slug` é o nome do arquivo; `html` é o corpo convertido.
- **`Controller\AbstractController`**: base dos controllers de página. O `render()` injeta em todo
  template `i18n`, `site`, `profile`, `canonical` e `altUrl`. `ContentRepository` e `UrlGenerator`
  ficam disponíveis como `$this->content` e `$this->urls`.
- **`Controller\SitemapController`**: não estende `AbstractController` de propósito, porque
  `sitemap.xml` e `robots.txt` não são páginas HTML de um idioma só.
- **`I18n\`**: `Locale` (enum; o idioma vem do começo do caminho, `Locale::fromPath`),
  `InterfaceTexts` (textos de `lang/{pt,en}.php`), `UrlGenerator` (`path()` e `absolute()`),
  `Localizer` (o objeto `i18n` dos templates: `text()`, `url()`, `switchUrl()`).
- **`Handler\HttpErrorHandler`**: páginas 404 e 500 no idioma do caminho; não registra 404 e 405
  no log.
- **`Twig\AssetExtension`**: `asset()` e `asset_absolute()`. O `?v=` é a data de modificação do
  arquivo no desenvolvimento e o `ASSET_VERSION` em produção.

### Rotas

Registradas uma vez por idioma em `routes/web.php`: `/`, `/blog`, `/blog/tag/{tag}` e
`/blog/{slug}`, com o prefixo `/en` no inglês. Sem prefixo: `/sitemap.xml` e `/robots.txt`. A
busca é o parâmetro `q` em `/blog`.

### Conteúdo (`www/content/`)

Etiqueta YAML no topo e Markdown embaixo. `site.md`; `pt.md` e `en.md` (perfil; o corpo é o
"sobre"); `projects/{pt,en}/*.md`; `blog/{pt,en}/*.md`.

- Campos obrigatórios, conferidos pelos testes contra o conteúdo real: artigo `title`,
  `description`, `date`; projeto `title`, `summary`, `order`.
- Traduções se ligam pelo `translation_key`, não pelo nome do arquivo.
- `date` chega aos templates como timestamp (inteiro), não como texto: formatar com o filtro
  `date` do Twig.
- HTML dentro do Markdown é escapado (`html_input: escape`).

### Front-end

- Templates em `www/templates/`: `layout/base.html.twig`, uma partial por seção da home
  (`home/_*.html.twig`), `blog/` e `errors/`. O único `raw` é o HTML vindo do Markdown.
- CSS: a entrada é `www/resources/css/app.css` (tokens em `@theme`, direção "Pôr do sol"). O
  `public/assets/css/app.css` é **gerado** pelo contêiner `tailwind` e não vai para o git.
- JS puro em `www/public/assets/js/`, um arquivo por função (tema, filtro de projetos, animação ao
  rolar, tema do Giscus).

### Docker

- `docker-compose.yml` (desenvolvimento): `caddy`, `php` e `tailwind`, com `./www` compartilhado.
  `php` e `caddy` constroem a partir da raiz, com `target: dev`.
- `bin/php/Dockerfile`: estágios `base`, `dev` e `prod`. O `prod` exige
  `--build-arg ASSET_VERSION`.
- `bin/caddy/Dockerfile`: estágios `dev`, `assets` (gera o CSS minificado) e `prod`.
- `docker-compose.prod.yml` (produção): só `caddy` e `php`, no estágio `prod`; exige `IMAGE_TAG`,
  que é a etiqueta das imagens e o `ASSET_VERSION`. O Caddy do app não publica porta: entra na
  rede externa `edge`, com um apelido igual ao nome do projeto.

### Servidor e publicação

- Na VM há dois projetos do Compose: `edge` (`infra/edge/`, o Caddy de borda, que cuida do HTTPS
  e publica as portas 80 e 443) e `site-prod` (`docker-compose.prod.yml`). O nome `site-prod` está
  escrito no Caddyfile de borda; mudar um exige mudar o outro.
- `.github/workflows/ci.yml`: `composer cs`, `stan` e `test` em todo Pull Request.
- `.github/workflows/deploy.yml`: no push na `master`, chama o `ci.yml`, constrói as imagens em
  runner ARM, envia ao GHCR e sobe a nova versão na VM por SSH.
- O roteiro de servidor, primeiro deploy, proteção da `master` e rollback é `infra/README.md`.

## Armadilhas

- **O CI só enxerga o que está no git.** Um teste não pode depender de `vendor/`, do `.env` ou do
  `public/assets/css/app.css`, que é gerado.
- **`infra/cloud-init.yaml` é só ASCII**, sem acentos nem em comentários: o painel da Oracle
  corrompe outros caracteres ao colar e o cloud-init descarta o arquivo inteiro. Conferir com
  `grep -nP "[^\x00-\x7F]" infra/cloud-init.yaml`.
- **A versão do Tailwind está em dois lugares**: `bin/tailwind/Dockerfile` e
  `bin/caddy/Dockerfile`. Atualizar os dois juntos.
- **`bin/php/Dockerfile` (estágio `base`) é cópia** de `dancarvalhodev/dockerfile-php-alpine`;
  mudanças lá precisam ser replicadas aqui.
- **O `name` do job no `ci.yml`** ("Estilo, análise e testes") é exigido pela proteção da
  `master`. Renomear trava todo Pull Request até a regra ser atualizada no GitHub.
- **Mudou o Caddyfile? Reinicie o Caddy.** A API de administração está desligada, então
  `caddy reload` não funciona.
- **Tema do Giscus:** os `giscus-{light,dark}.css` não declaram `color-scheme` (gera uma caixa
  opaca atrás dos comentários) e dependem do cabeçalho de CORS do Caddyfile. Só dá para conferir
  em produção. Ver `.claude/arch-explain/giscus.md`.
- **Nunca `down --volumes` na borda, no servidor:** o volume guarda os certificados.
- **A `master` é protegida e publica.** Toda alteração vai por branch nova e Pull Request; o merge
  dispara o deploy.

## Convenções

- `declare(strict_types=1)`, PSR-4 (`App\` em `src/`, `App\Bootstrap\` em `bootstrap/`), estilo
  PER-CS conferido pelo PHP-CS-Fixer.
- Poucos comentários: só o porquê que o código não mostra, em uma linha.
- Código claro para júnior e pleno: sem `extract()`, sem regex onde um laço basta, nomes que
  dizem o que a coisa faz.
- MVP: o mínimo de arquivos e classes que resolve a tarefa; nada de cache, validador ou camada
  "para o futuro".
- Comentários, documentação e mensagens de commit em português; nomes de classes, métodos e
  testes em inglês.

## Documentação

`.claude/arch-explain/` tem um documento por assunto, com o porquê das decisões: `caddy.md`,
`tailwind.md`, `i18n.md`, `blog-content.md`, `giscus.md`, `seo.md`, `site-url.md`, `scroll-reveal.md`,
`producao.md`, `infra.md` e `ci-cd.md`. Ao mudar o comportamento de uma dessas áreas, atualize o
documento correspondente na mesma alteração.
