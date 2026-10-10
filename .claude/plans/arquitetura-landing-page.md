# Arquitetura da landing page (portfólio + blog) — PHP 8.5 + Caddy em Docker

## Contexto

O repositório `/home/dan/dev/landing-page` (remoto `git@github.com:dancarvalhodev/landing-page.git`)
contém apenas `README.md` e `CLAUDE.md`. O objetivo é um site de portfólio pessoal que leve
todos a entrar em contato. A arquitetura deriva da entrevista e das decisões
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
  prévia e aprovação antes de ir ao ar. **A prévia foi retirada em 2026-10-06, por decisão do
  dev** (etapa 10a): a conferência antes de publicar é feita no ambiente local. O motivo e o
  caminho para recolocá-la estão em `.claude/arch-explain/infra.md`.

## Abordagem recomendada

Aplicação **PHP com Slim 4**, sem banco de dados: todo o conteúdo são arquivos Markdown (etiqueta em YAML no topo, texto embaixo),
lidos direto do disco a cada requisição (MVP: sem cache até haver necessidade medida). Dois contêineres por ambiente —
`caddy` (arquivos estáticos + FastCGI) e `php` (PHP-FPM) — iguais em desenvolvimento e
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
docker-compose.prod.yml     # produção: imagens do GHCR, sem bind mounts
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
  edge/docker-compose.yml   # Caddy de borda: TLS e proxy para a produção
  edge/Caddyfile
  README.md                 # passo a passo OCI + DNS
.github/workflows/
  ci.yml                    # CS, PHPStan, PHPUnit (os testes também leem o conteúdo real)
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
- **Servidor**: dois projetos compose na mesma rede externa `edge`: `site-prod`
  (`docker-compose.prod.yml`) e `edge` (único a publicar 80/443). O Caddy de borda emite os
  certificados, faz proxy de `<domínio>` → produção e redireciona `www.<domínio>` para o domínio.
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
- **Publicação com revisão:** conferência no ambiente local → branch → Pull Request → `ci.yml`
  valida → merge na `master` → `deploy.yml` publica em produção. Aprovar = fazer o merge.
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
7. **SEO**, dividida em seis a pedido do dev:
   - **7a. Metadados básicos:** `<title>`/descrição por página e `canonical`.
   - **7b. hreflang:** entre PT/EN (home, lista do blog, artigos — reaproveitando a tradução por
     `translation_key` da etapa 6a).
   - **7c. Open Graph e Twitter Card:** usando `UrlGenerator::absolute()`, já existente.
   - **7d. JSON-LD `Person`:** na home, com `sameAs` apontando para as redes sociais.
   - **7e. `sitemap.xml` e `robots.txt`.**
   - **7f. Imagem de compartilhamento:** o dev gera a arte (1200×630px) a partir de um prompt que
     descreve a identidade "Pôr do sol", preparado nesta etapa.
8. **Descrições profissionais:** com a home e o blog já mostrando o conteúdo, revisar com o dev e
   reescrever todos os textos profissionais em `content/`, nos dois idiomas: a descrição de cada
   experiência (hoje P21 e Cubo têm o mesmo texto provisório), a chamada, a apresentação, o "sobre"
   e a descrição de cada projeto. Só com informações dadas pelo dev; nada inventado.
9. **Imagens de produção**, dividida em quatro a pedido do dev. Cada subetapa termina com um resumo
   das alterações e com a documentação em `.claude/arch-explain/producao.md`:
   - **9a. Imagem PHP:** estágios `base`, `dev` e `prod` em `bin/php/Dockerfile`, `php.prod.ini` e
     `.dockerignore`.
   - **9b. Imagem Caddy:** estágio que gera o CSS minificado com o Tailwind e estágio final com
     `public/` embutido.
   - **9c. Versão do CSS em produção:** outra fonte para o `?v=` da função `asset()`, já que o CSS
     só existe na imagem do Caddy.
   - **9d. `docker-compose.prod.yml` e conferência:** subir as duas imagens sem bind mounts e
     testar localmente.
10. **Infra**, dividida em duas a pedido do dev. Por ser um processo importante e complexo, cada
    subetapa explica e documenta tudo em `.claude/arch-explain/infra.md`. A criação da conta/VM na
    OCI, a regra na Security List e os registros DNS (`@` e `www`) são feitos pelo usuário seguindo
    o passo a passo.
    - **10a. Caddy de borda e rede compartilhada:** `infra/edge/docker-compose.yml`,
      `infra/edge/Caddyfile` e a rede `edge` no `docker-compose.prod.yml`, no lugar da porta
      publicada. A prévia foi retirada nesta subetapa.
    - **10b. Preparar a VM:** `infra/cloud-init.yaml` (Docker, usuário de deploy e firewall) e
      `infra/README.md` (passo a passo OCI + DNS). Eram 10b e 10c; foram juntadas a pedido do dev,
      por serem dependentes: o roteiro é o que executa o `cloud-init`.
11. **CI/CD**, dividida em três a pedido do dev. Cada subetapa explica e documenta tudo em
    `.claude/arch-explain/`, com o passo a passo do que o dev faz no GitHub:
    - **11a. `ci.yml`:** a cada Pull Request, roda PHP-CS-Fixer, PHPStan e PHPUnit. Na `master`,
      quem o chama é o `deploy.yml`.
    - **11b. `deploy.yml`:** no merge na `master`, roda o `ci.yml`, constrói as duas imagens para
      ARM64, envia ao GHCR e, por SSH, sobe o projeto `site-prod` na VM. Inclui os secrets
      (`SSH_KEY`, `SSH_HOST`, `SSH_KNOWN_HOSTS`), o `.env` do site na VM, o passo a passo do Pull Request da `feat/site-foundation` para a `master` e o
      primeiro deploy.
    - **11c. Proteção da `master` e rollback:** exigir Pull Request e CI verde para o merge
      (configurado pelo dev no painel do GitHub, seguindo o passo a passo) e documentar como
      voltar para uma versão anterior.

    Decisões do dev para esta etapa (2026-10-09): a VM é ARM (`VM.Standard.A1.Flex`); o
    repositório e as imagens no GHCR serão públicos, então a VM baixa as imagens sem token; a
    `feat/site-foundation` vai para a `master` por Pull Request.
12. **Documentação:** atualizar `CLAUDE.md` e `README.md` com comandos, arquitetura e como
    publicar um artigo.
13. **Revisão da documentação:** reler o que foi escrito na etapa 12 (e os demais documentos do
    projeto, como os de `.claude/arch-explain/`) e corrigir o que estiver desatualizado, incompleto
    ou confuso, com o código já pronto como referência.
14. **Spec do projeto — retirada em 2026-10-09, por decisão do dev.** A ideia era escrever a
    especificação completa a partir do que foi de fato construído, em `.claude/specs/`. Ela seria
    redundante: o `README.md`, o `CLAUDE.md`, os documentos de `.claude/arch-explain/` e o
    `infra/README.md` já cobrem requisitos, arquitetura, rotas, conteúdo, i18n, front-end, Docker,
    infraestrutura, publicação e convenções, e foram revisados contra o código na etapa 13. Uma
    spec seria um terceiro conjunto de documentos para manter em dia. Vale reconsiderar se o
    projeto for passado a outra pessoa ou reescrito em outra tecnologia.

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
- Opcional: submeter `https://<domínio>/sitemap.xml` no Google Search Console, pra acelerar a
  indexação (o Google encontra o sitemap sozinho via `robots.txt`, mas submeter manualmente não
  tem desvantagem).
- Abrir um PR de teste: o CI roda;
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
- [x] 6c. Blog — tags — confirmada em 2026-10-03
- [x] 6d. Blog — busca — confirmada em 2026-10-03
- [x] 6e. Blog — realce de código — confirmada em 2026-10-03
- [x] 6f. Blog — Giscus — confirmada em 2026-10-03 (visual do tema pendente de deploy com HTTPS; corrigido e conferido em produção em 2026-10-10, ver `.claude/arch-explain/giscus.md`)
- [x] 6. Blog (todas as subetapas) — confirmada em 2026-10-03
- [x] 7a. SEO — metadados básicos — confirmada em 2026-10-04
- [x] 7b. SEO — hreflang — confirmada em 2026-10-04
- [x] 7c. SEO — Open Graph e Twitter Card — confirmada em 2026-10-04
- [x] 7d. SEO — JSON-LD Person — confirmada em 2026-10-04
- [x] 7e. SEO — sitemap.xml e robots.txt — confirmada em 2026-10-04
- [x] 7f. SEO — imagem de compartilhamento — confirmada em 2026-10-04
- [x] 7. SEO (todas as subetapas) — confirmada em 2026-10-04
- [x] 8a. Descrições profissionais — experiências — confirmada em 2026-10-05
- [x] 8b. Descrições profissionais — chamada, apresentação, "sobre" e habilidades — confirmada em 2026-10-05
- [x] 8c. Descrições profissionais — projetos — confirmada em 2026-10-05
- [x] 8. Descrições profissionais (todas as subetapas) — confirmada em 2026-10-05
- [x] 9a. Imagens de produção — imagem PHP — confirmada em 2026-10-06
- [x] 9b. Imagens de produção — imagem Caddy — confirmada em 2026-10-06
- [x] 9c. Imagens de produção — versão do CSS em produção — confirmada em 2026-10-06
- [x] 9d. Imagens de produção — docker-compose.prod.yml e conferência — confirmada em 2026-10-06
- [x] 9. Imagens de produção (todas as subetapas) — confirmada em 2026-10-06
- [x] 10a. Infra — Caddy de borda e rede compartilhada (prévia retirada) — confirmada em 2026-10-07
- [x] 10b. Infra — preparar a VM: cloud-init.yaml e README com o passo a passo OCI + DNS (antigas 10b e 10c, juntadas) — confirmada em 2026-10-09, com a VM criada e o roteiro executado pelo dev. O cloud-init não foi aplicado na primeira VM (um acento no arquivo foi corrompido ao colar); os passos foram feitos à mão e o arquivo corrigido para só ASCII
- [x] 10. Infra (todas as subetapas) — confirmada em 2026-10-09
- [x] 11a. CI/CD — ci.yml (estilo, análise e testes) — confirmada em 2026-10-09
- [x] 11b. CI/CD — deploy.yml, secrets, Pull Request para a master e primeiro deploy — confirmada em 2026-10-09, com o site no ar. O tema customizado do Giscus não funcionou em produção; o dev abriu uma issue no GitHub e corrigiu em 2026-10-10
- [x] 11c. CI/CD — proteção da master e rollback — confirmada em 2026-10-09
- [x] 11. CI/CD (todas as subetapas) — confirmada em 2026-10-09
- [x] 12. Documentação — confirmada em 2026-10-09 (feita de uma vez, sem subetapas: são dois arquivos, README.md e CLAUDE.md)
- [x] 13. Revisão da documentação — confirmada em 2026-10-09. Levantamento feito em 2026-10-09 (leitura de todos os documentos contra o código); o dev decidiu aplicar as doze correções de uma vez, sem subetapas. O plano não foi revisado
- [x] 14. Spec do projeto — retirada em 2026-10-09, por decisão do dev: seria redundante com a documentação existente

**Plano concluído em 2026-10-09.** As etapas 1 a 13 estão confirmadas e a 14 foi retirada. O site
está no ar. Este arquivo fica como registro do que foi planejado e de como o plano mudou no
caminho; ele não foi reescrito para refletir o resultado final. Para o estado real do projeto, ver
o `README.md`, o `CLAUDE.md` e `.claude/arch-explain/`. Onde o plano e o código diferem:

- a prévia (`preview.<domínio>`) foi retirada na etapa 10a;
- o deploy usa um terceiro secret, `SSH_KNOWN_HOSTS`, e o `APP_ENV` e o `SITE_URL` ficam em um
  `.env` na VM (etapa 11b);
- o `?v=` dos arquivos estáticos em produção é a versão do build, não a data do arquivo (9c);
- não há cache de build no deploy nem a pasta `public/media/blog/` prevista para imagens de
  artigos.

O que ficou pendente está em `.claude/handoff.md`, seção "Pendências conhecidas".
