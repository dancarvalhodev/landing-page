# Arquitetura da landing page (portfólio + blog) — PHP 8.5 + Caddy em Docker

## Contexto

O repositório `/home/dan/dev/landing-page` (remoto `git@github.com:dancarvalhodev/landing-page.git`)
contém apenas `README.md` e `CLAUDE.md`. O objetivo é um site de portfólio pessoal que leve
recrutadores e clientes a entrar em contato. A arquitetura deriva da entrevista e das decisões
do usuário:

- **PHP 8.5** (linguagem de domínio do usuário) com o microframework **Slim 4** para roteamento
  e PSRs, montado como no repositório `dancarvalhodev/wecker`. Decisão tomada durante a etapa 2,
  no lugar do roteador próprio previsto inicialmente.
- **Tudo roda em Docker**, seguindo o template `dancarvalhodev/docker-nginx`, com o Nginx
  trocado por **Caddy**. A imagem PHP é **construída neste repositório** a partir do Dockerfile
  `dancarvalhodev/dockerfile-php-alpine/8.5` (não há tag 8.5 publicada no Docker Hub).
- **Tailwind CSS** no front-end, para um visual mais vibrante.
- VM **Ampere A1 (ARM64)** no Oracle Cloud Free Tier.

## Requisitos (da entrevista)

- **Objetivo:** portfólio / marca pessoal; conversão = contato por e-mail e redes (sem formulário).
- **Público e tráfego:** recrutadores e clientes; chegam por redes sociais, link direto e busca.
- **Conteúdo:** página principal de rolagem única (sobre, projetos, experiência e habilidades,
  depoimentos, contato) + blog. Textos existem; identidade visual não.
- **Idiomas:** PT e EN; no blog a tradução de cada artigo é opcional.
- **Interações:** tema claro/escuro, filtro de projetos, busca e tags no blog, comentários
  (o mais simples possível), animações sutis.
- **Performance / mídia:** equilíbrio; conteúdo leve, com prints e blocos de código bem formatados.
- **SEO:** ser encontrado pelo nome. Sem analytics.
- **Hospedagem:** instância Oracle Cloud Free Tier (ainda não criada); domínio já registrado.
- **Atualização:** Markdown no repositório, algumas vezes por mês; publicação automática com
  prévia e aprovação antes de ir ao ar.

## Abordagem recomendada

Aplicação **PHP com Slim 4**, sem banco de dados: todo o conteúdo são arquivos Markdown (etiqueta em YAML no topo, texto embaixo),
lidos direto do disco a cada requisição (MVP: sem cache até haver necessidade medida). Dois contêineres por ambiente —
`caddy` (arquivos estáticos + FastCGI) e `php` (PHP-FPM) — iguais em desenvolvimento, prévia e
produção. MySQL e Redis do template ficam de fora: não há dados nem sessões.

| Camada | Escolha | Por quê |
|---|---|---|
| Linguagem | **PHP 8.5-FPM (Alpine)**, `strict_types`, autoload PSR-4 via Composer | Dockerfile do usuário |
| Estrutura | **Slim 4** + PHP-DI (contêiner com autowiring, via `php-di/slim-bridge`) | Roteamento e requisição/resposta seguindo as PSRs (7, 11, 15), no mesmo padrão do wecker |
| Templates | **Twig 3** via `slim/twig-view` | Escape automático, herança de layout e partials prontos; biblioteca estável e padrão de mercado, já usada no wecker |
| Markdown | `league/commonmark` (+ front matter, tabelas, âncoras em títulos) | Padrão de fato em PHP |
| Realce de código | `tempest/highlight` (integra com o CommonMark) | Realce no servidor, sem JS no cliente |
| Estilo | **Tailwind CSS v4** via CLI standalone (sem Node), tokens em `@theme` | Pedido do usuário; o binário único evita uma toolchain Node |
| Busca e tags | No servidor, sobre a lista de artigos (`/blog?q=`, `/blog/tag/<tag>`) | Funciona sem JS; volume pequeno |
| Comentários | **Giscus** (GitHub Discussions) | Nada para hospedar ou moderar |
| Filtro de projetos / tema / animações | JS puro pequeno + utilitários Tailwind (`motion-safe:`) | Não justifica biblioteca |
| Servidor web | **Caddy** | Substitui o Nginx do template; HTTPS automático |
| Qualidade | PHPUnit, PHPStan, PHP-CS-Fixer (PER-CS) | Padrão do ecossistema |
| CI/CD | **GitHub Actions** → imagens no **GHCR** → `docker compose` na VM | Repositório já está no GitHub |

Dependências PHP de produção: `slim/slim`, `slim/psr7`, `php-di/php-di`, `php-di/slim-bridge`,
`twig/twig`, `slim/twig-view`, `league/commonmark`, `symfony/yaml` (front matter) e `tempest/highlight`.

### Estrutura do repositório

Segue a convenção do template (`bin/<serviço>/Dockerfile`, `config/<serviço>/`, `env-example`,
`logs/`, aplicação em `www/`). Diferença: `www/` passa a ser versionado, pois o app vive neste
repositório.

```
docker-compose.yml          # desenvolvimento: caddy, php, tailwind (watch)
docker-compose.prod.yml     # prévia e produção: imagens do GHCR, sem bind mounts
env-example                 # CONTAINER_NAME, PORT_CADDY, APP_ENV, SITE_URL
bin/
  php/Dockerfile            # estágios base (Dockerfile 8.5 do usuário) → dev → prod
  caddy/Dockerfile          # estágio assets (Tailwind) → caddy com public/ embutido
  tailwind/Dockerfile       # CLI standalone (variante musl) para o modo watch
config/
  caddy/Caddyfile           # equivalente ao default.conf do template
  php/php.ini               # dev: Xdebug (sem a sessão em Redis do template)
  php/php.prod.ini          # prod: OPcache sem revalidação, display_errors=Off
logs/
www/                        # a aplicação
  public/
    index.php               # front controller: lê a Config e roda o Main
    assets/                 # css/app.css (gerado), js/, fonts/
    media/blog/<slug>/      # prints dos artigos
  bootstrap/Main.php        # monta o contêiner, o app Slim, os middlewares e as rotas
  config/                   # definições do contêiner (twig.php, handler.php)
  routes/web.php            # todas as rotas do site
  src/                      # namespace App\
    Config.php              # pasta raiz e APP_ENV (obrigatório e validado)
    Controller/             # AbstractController, Home, Blog (lista, artigo, tag, busca), Sitemap
    Handler/                # HttpErrorHandler: páginas 404 e 500
    Middleware/             # CanonicalPathMiddleware: barra final e barras duplicadas
    Content/                # ContentRepository: lê os arquivos Markdown de content/
    I18n/                   # Locale, InterfaceTexts, UrlGenerator
  templates/                # Twig: layout/base.html.twig, home/ (uma partial por seção), blog/, errors/
  content/
    blog/{pt,en}/<slug>.md
    projects/{pt,en}/<slug>.md
    site.md                 # nome, e-mail, redes sociais (só etiqueta, sem corpo)
    pt.md, en.md            # perfil, experiência, habilidades e depoimentos; o corpo é o "sobre"
  lang/{pt,en}.php          # textos de interface
  resources/css/app.css     # entrada do Tailwind (@theme com os tokens)
  var/cache/                # cache do Twig, ignorado pelo git
  tests/
  composer.json
infra/
  cloud-init.yaml           # instala Docker Engine + plugin compose, usuário de deploy, firewall
  edge/docker-compose.yml   # Caddy de borda: TLS e proxy para produção e prévia
  edge/Caddyfile
  README.md                 # passo a passo OCI + DNS
.github/workflows/
  ci.yml                    # CS, PHPStan, PHPUnit (os testes também leem o conteúdo real)
  preview.yml               # PR → imagens :pr-<n> → sobe a pilha de prévia
  deploy.yml                # merge na master → imagens :<sha> → sobe a produção
```

### Docker

- **`bin/php/Dockerfile`** (contexto = raiz do repo):
  - `base`: o conteúdo do Dockerfile 8.5 do usuário (`php:8.5-fpm-alpine`, extensões, Composer),
    com `WORKDIR /app`.
  - `dev`: `base` como está (Xdebug ativo); o código entra por bind mount de `./www`.
  - `prod`: remove o ini do Xdebug, aplica `php.prod.ini`, copia `www/`, roda
    `composer install --no-dev --classmap-authoritative`.
- **`bin/caddy/Dockerfile`**: estágio `assets` roda o Tailwind com `--minify` sobre `templates/`
  e `content/`; o estágio final é `caddy:alpine` com `www/public/` e o CSS gerado embutidos.
- **Caddyfile do app** (tradução do `default.conf`): `root * /app/public`, `php_fastcgi php:9000`,
  `file_server`, `encode zstd gzip`, cache longo para estáticos, redirecionamento de barra final,
  bloqueio de dotfiles. Escuta só em `:80` — o TLS fica na borda.
- **Desenvolvimento** (`docker-compose.yml`): `caddy` publica `${PORT_CADDY}`, `php` no alvo `dev`,
  `tailwind` em `--watch`; bind mounts de `./www` nos três.
- **Servidor**: três projetos compose na mesma rede externa `edge`:
  `site-prod` e `site-preview` (ambos `docker-compose.prod.yml`, com `IMAGE_TAG` diferente) e
  `edge` (único a publicar 80/443). O Caddy de borda emite os certificados e faz proxy de
  `<domínio>` → produção e `preview.<domínio>` → prévia, esta com senha e `X-Robots-Tag: noindex`.
- **Arquitetura das imagens:** `linux/arm64`, construídas no CI em runner ARM nativo
  (`ubuntu-24.04-arm`) com cache do buildx; o mesmo Dockerfile constrói em amd64 na máquina local.

### Decisões de aplicação

- **Fluxo da requisição:** Caddy serve o que existe em `public/`; o resto vai a `index.php` →
  `Main` → middlewares do Slim (erros, caminho canônico) → rota em `routes/web.php` →
  controller → template Twig → resposta PSR-7 (com `ETag` e `Cache-Control`).
- **Conteúdo (MVP):** uma única classe, `ContentRepository`, lê os arquivos de `content/` a cada
  requisição, converte o Markdown em HTML e devolve arrays simples. Os campos obrigatórios
  (artigo: `title`, `description`, `date`; projeto: `title`, `summary`, `order`) são conferidos
  pelos testes, que leem o conteúdo real: um arquivo incompleto derruba o CI antes do deploy. Sem
  cache, sem rascunhos e sem validação no código até haver necessidade.
- **i18n:** PT na raiz (`/`), EN em `/en`; as mesmas rotas são registradas uma vez por
  idioma, e o idioma é lido do começo do caminho (`Locale::fromPath`), o que também vale para as
  páginas de erro. Os templates recebem um objeto `i18n` com `text()`, `url()` e `switchUrl()`. O seletor de
  idioma usa `translationKey`: leva à tradução se existir, senão ao índice do blog do outro
  idioma. `hreflang` só para pares existentes.
- **Rotas:** `/`, `/blog`, `/blog/<slug>`, `/blog/tag/<tag>`, `/sitemap.xml` e equivalentes em
  `/en/…`. A busca é o parâmetro `q` na listagem.
- **Templates:** Twig, com `extends`/`block` para o layout e `include` para as partials. O escape
  é automático; a única exceção é o HTML do Markdown, impresso com o filtro `raw` porque vem do
  próprio repositório. Fora de `development`, os templates compilados ficam em `var/cache/twig`.
- **Tema e visual:** tokens de cor/tipografia em `@theme`; modo escuro por classe no `<html>`
  (variante `dark:`), seguindo `prefers-color-scheme` e com a escolha salva em `localStorage`;
  script inline no `<head>` evita flash. O tema do Giscus acompanha o do site.
- **Filtro de projetos:** cartões renderizados com `data-*`; o script só oculta/mostra.
- **SEO (nome):** `<title>` e `h1` com o nome, JSON-LD `Person` com `sameAs`, canonical, sitemap
  gerado do índice, `robots.txt`, Open Graph básico com imagem estática.
- **Publicação com revisão:** branch → Pull Request → `ci.yml` valida, `preview.yml` publica em
  `preview.<domínio>` → revisão → merge na `master` → `deploy.yml` publica em produção.
  Aprovar = fazer o merge. Um único slot de prévia.
- **Deploy:** o workflow envia as imagens ao GHCR e, por SSH, roda
  `IMAGE_TAG=<sha> docker compose -p site-prod -f docker-compose.prod.yml up -d --wait`.
  Rollback = subir de novo com a tag anterior.

### Riscos conhecidos

- **Firewall na Oracle:** 80/443 precisam ser liberadas na Security List da VCN; a imagem Ubuntu
  também traz regras de `iptables` restritivas, que o `cloud-init` ajusta.
- **Capacidade ARM:** pode faltar Ampere A1 em algumas regiões no momento da criação.
- **Recuperação de instâncias ociosas:** a Oracle pode reclamar VMs Always Free com uso muito
  baixo. Mitigação: converter a conta para Pay As You Go (os recursos Always Free seguem
  gratuitos). Confirmar a política vigente ao criar a conta.
- **Usuário de deploy no grupo `docker`** equivale a root na VM; a chave SSH do CI precisa ser
  exclusiva e ficar só em GitHub Secrets.
- **Dockerfile duplicado:** `bin/php/Dockerfile` copia o do repositório `dockerfile-php-alpine`;
  atualizações lá precisam ser replicadas aqui.
- **Giscus e GHCR:** Giscus exige repositório público com Discussions; se este repo for privado,
  usar um repo público só para comentários e dar à VM um token de leitura do GHCR.

## Etapas de implementação

1. **Docker de desenvolvimento:** `docker-compose.yml`, `env-example`, `bin/php`, `bin/caddy`,
   `bin/tailwind`, `config/`, `.gitignore`; `docker compose up` servindo um `index.php` mínimo.
2. **Base do app:** `composer.json` (PSR-4, scripts `test`, `stan`, `cs`), Slim 4 com PHP-DI no
   padrão do wecker (`bootstrap/Main.php`, `config/`, `routes/web.php`), Twig, `Config`,
   `HttpErrorHandler` e páginas 404 e 500.
3. **Identidade visual:** propor 2–3 direções vibrantes (paleta, tipografia, gradientes) e validar
   com o usuário antes dos templates; registrar os tokens em `www/resources/css/app.css`.
4. **Conteúdo e i18n**, dividida em três a pedido do dev:
   - **4a. i18n:** `Locale`, `InterfaceTexts`, `UrlGenerator`, rotas em `/en`, textos de interface em
     `lang/`.
   - **4b. Leitura de conteúdo (MVP):** `ContentRepository` lendo os arquivos Markdown de `content/`,
     com conteúdo de exemplo.
   - **4c. Textos reais:** trocar o conteúdo de exemplo pelos dados do LinkedIn do dev
     (https://www.linkedin.com/in/daniel-carvalho-02578a15a/).
5. **Home**, dividida em quatro a pedido do dev:
   - **5a. Seções com conteúdo real:** sobre, projetos, experiência e habilidades, depoimentos e
     contato, no lugar da amostra de identidade visual.
   - **5b. Filtro de projetos:** JS puro sobre os cartões da 5a.
   - **5c. Tema claro/escuro:** toggle, persistência em `localStorage`, script no `<head>` para
     evitar flash.
   - **5d. Animações sutis:** `motion-safe:`, pequenas transições ao rolar.
6. **Blog**, dividida em cinco a pedido do dev:
   - **6a. Lista e artigo:** rotas, controllers e templates para `/blog` e `/blog/<slug>`, usando o
     `ContentRepository::posts()` já existente desde a etapa 4b.
   - **6c. Tags:** `/blog/tag/<tag>`, listando os artigos daquela tag.
   - **6d. Busca:** parâmetro `q` na listagem (`/blog?q=`), no servidor.
   - **6e. Realce de código:** `tempest/highlight` integrado ao CommonMark.
   - **6f. Giscus:** comentários via GitHub Discussions, tema acompanhando o claro/escuro do site.
7. **SEO:** metadados, JSON-LD, sitemap, `robots.txt`, imagem de compartilhamento.
8. **Descrições profissionais:** com a home e o blog já mostrando o conteúdo, revisar com o dev e
   reescrever todos os textos profissionais em `content/`, nos dois idiomas: a descrição de cada
   experiência (hoje P21 e Cubo têm o mesmo texto provisório), a chamada, a apresentação, o "sobre"
   e a descrição de cada projeto. Só com informações dadas pelo dev; nada inventado.
9. **Imagens de produção:** estágios `prod`, `docker-compose.prod.yml`, `php.prod.ini`.
10. **Infra:** `infra/cloud-init.yaml`, `infra/edge/`, `infra/README.md`. A criação da conta/VM na
    OCI, a regra na Security List e os registros DNS (`@`, `www`, `preview`) são feitos pelo
    usuário seguindo o passo a passo.
11. **CI/CD:** `ci.yml`, `preview.yml`, `deploy.yml`; secrets (`SSH_KEY`, `SSH_HOST`, senha da
    prévia); proteção da `master` exigindo PR e CI verde.
12. **Documentação:** atualizar `CLAUDE.md` e `README.md` com comandos, arquitetura e como
    publicar um artigo.
13. **Revisão da documentação:** reler o que foi escrito na etapa 12 (e os demais documentos do
    projeto, como os de `.claude/arch-explain/`) e corrigir o que estiver desatualizado, incompleto
    ou confuso, com o código já pronto como referência.
14. **Spec do projeto:** com o código já pronto, escrever a especificação completa a partir do que
    foi de fato construído (e não do que este plano previa): requisitos, arquitetura, rotas,
    modelo de conteúdo e front matter, i18n, front-end, ambiente Docker, infraestrutura, fluxo de
    publicação e convenções de código. Os arquivos ficam em `.claude/specs/`, em Markdown, com texto
    em português claro e completo, sem jargão desnecessário nem escrita excessivamente formal.

## Verificação

- `docker compose up -d --build` e abrir `http://localhost:${PORT_CADDY}`: navegar por `/` e
  `/en/`; alternar idioma num artigo com e sem tradução; alternar tema e recarregar (sem flash);
  filtrar projetos; buscar no blog; abrir uma tag; conferir realce de código, 404 e Giscus.
  Editar um template e confirmar que o Tailwind em watch regenera o CSS.
- `docker compose exec php composer test` (rotas e erros, idioma, leitura do conteúdo real,
  busca), `composer stan` e `composer cs` sem erros; um teste isolado com
  `docker compose exec php vendor/bin/phpunit --filter <nome>`.
- Build de produção local: `docker compose -f docker-compose.prod.yml build`; na imagem `prod`,
  `php -m` não lista Xdebug.
- Lighthouse (mobile) na home e em um artigo: performance, acessibilidade e SEO ≥ 95.
- Validar o JSON-LD `Person` no Rich Results Test e a prévia do link no LinkedIn Post Inspector.
- Abrir um PR de teste: a prévia aparece em `preview.<domínio>`, pede senha e envia `noindex`;
  após o merge, a produção atualiza e `curl -I https://<domínio>` responde 200 com HTTPS válido,
  enquanto `http://` redireciona.
- Rollback: subir a produção com a tag anterior e confirmar que o site volta.

## Progresso

Uma etapa só é marcada `[x]` depois da confirmação do dev.

- [x] 1. Docker de desenvolvimento — confirmada em 2026-10-01
- [x] 2. Base do app — confirmada em 2026-10-02
- [x] 3. Identidade visual — direção "Pôr do sol"; confirmada em 2026-10-02
- [x] 4a. i18n — confirmada em 2026-10-02
- [x] 4b. Leitura de conteúdo (MVP) — confirmada em 2026-10-02
- [x] 4c. Textos reais — confirmada em 2026-10-03
- [x] 5a. Home — seções com conteúdo real — confirmada em 2026-10-03
- [x] 5b. Home — filtro de projetos — confirmada em 2026-10-03
- [x] 5c. Home — tema claro/escuro — confirmada em 2026-10-03
- [x] 5d. Home — animações sutis — confirmada em 2026-10-03
- [x] 5. Home (todas as subetapas) — confirmada em 2026-10-03
- [x] 6a. Blog — lista e artigo — confirmada em 2026-10-03
- [ ] 6c. Blog — tags — entregue, aguardando conferência
- [ ] 6d. Blog — busca
- [ ] 6e. Blog — realce de código
- [ ] 6f. Blog — Giscus
- [ ] 7. SEO
- [ ] 8. Descrições profissionais
- [ ] 9. Imagens de produção
- [ ] 10. Infra
- [ ] 11. CI/CD
- [ ] 12. Documentação
- [ ] 13. Revisão da documentação
- [ ] 14. Spec do projeto
