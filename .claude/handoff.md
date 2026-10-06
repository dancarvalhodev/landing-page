# Passagem de sessão

Resumo para continuar o desenvolvimento em outro computador (ou voltar a este). Quem terminar uma
sessão atualiza este arquivo antes do último commit; quem começar lê este arquivo primeiro.

Última atualização: 2026-10-06.

## Onde paramos

- **Branch:** `feat/site-foundation`, já enviada ao GitHub (`origin`). Um commit por etapa.
- **Plano:** `.claude/plans/arquitetura-landing-page.md`. A seção "Progresso", no fim do arquivo,
  marca o que está confirmado.
- **Confirmadas:** etapas 1 a 9 inteiras. A 8 (descrições profissionais) foi feita em 8a-8c e a 9
  (imagens de produção) em 9a-9d: imagem PHP, imagem Caddy, versão do CSS em produção e
  `docker-compose.prod.yml`. Conceito e código da etapa 9 estão em
  `.claude/arch-explain/producao.md`.
- **Próxima:** etapa 10 — infra (`infra/cloud-init.yaml`, `infra/edge/`, `infra/README.md`).

## Como retomar em um computador novo

1. `git pull` na branch `feat/site-foundation`.
2. `cp env-example .env` e ajustar `HOST_UID` e `HOST_GID` para a saída de `id -u` e `id -g`.
3. `docker compose up -d --build`.
4. `docker compose exec php composer install` (a pasta `vendor/` não vai para o git).
5. Conferir: `docker compose exec php composer test` (40 testes passando) e
   `http://localhost:8080`.
6. O índice do CodeGraph (`.codegraph/`) não vai inteiro para o git; se quiser usá-lo, rode
   `codegraph init` no projeto.
7. No `~/.claude/CLAUDE.md` global desse computador, acrescentar a seção "Comandos" (evitar
   comandos compostos; CodeGraph primeiro), que só existe no outro.

PHP, Composer e Node não precisam estar instalados na máquina: tudo roda nos contêineres.

## Como o dev quer trabalhar

Estas preferências estavam na memória local do Claude, que não viaja com o git. Valem para todo o
projeto.

- **Uma etapa por vez, com a skill `modo-codando`** (`.claude/skills/modo-codando/SKILL.md`):
  entregar, dizer como conferir, perguntar "está tudo correto?" e só seguir depois de um sim claro.
  Um comentário ou um "commitei" não é aprovação.
- **Ao fim de cada subetapa, um resumo explicando as alterações e a documentação** em
  `.claude/arch-explain/` (pedido do dev na etapa 9). As dúvidas que ele faz durante a conferência
  também entram no documento, quando ele concorda.
- **Etapas grandes demais para revisar de uma vez, dividir em subetapas** (a, b, c...) antes de
  começar, perguntando ao dev como prefere dividir. Foi feito com a etapa 5 (Home → 5a-5d), a 6
  (Blog → 6a-6f), a 7 (SEO → 7a-7f), a 8 (8a-8c) e a 9 (9a-9d). Cuidado ao reaproveitar uma letra já usada em outro lugar do
  plano (ex.: a 6b já existia reservada quando a 7 foi dividida).
- **MVP sempre.** O mínimo de arquivos e classes que resolve a etapa de hoje; nada antecipado
  (cache, validadores, camadas "para o futuro"). A etapa 4b foi refeita porque a primeira versão
  tocou 57 arquivos.
- **Explicações com analogias**, curtas, começando pela analogia e depois ligando ao código. As
  explicações que ele pede para guardar ficam em `.claude/arch-explain/`.
- **Em assuntos em que o dev não tem bagagem (ex.: SEO), explicar o conceito antes do código**,
  não só mostrar o que mudou — ele pediu isso explicitamente na etapa 7 e vale manter daqui pra
  frente, em qualquer assunto novo que ele sinalizar não conhecer.
- **Código com poucos comentários:** só o porquê que o código não mostra, em uma linha.
- **Código claro para júnior e pleno:** sem mágica desnecessária (nada de `extract()`, regex onde
  um laço basta, nomes enganosos). Ex.: `Translator::translate()` virou `InterfaceTexts::textFor()`.
- **Git é do dev.** O Claude nunca roda `git add`, `commit`, `push`, `stash` nem abre PR. Quando
  pedido, só escreve a mensagem de commit:
  - em português, título + corpo explicando o quê e o porquê;
  - **sem** `:sparkles:` no início (a ferramenta do dev acrescenta sozinha);
  - terminando com `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>` (conferir o modelo
    atual no início da sessão — esse nome muda conforme o modelo usado);
  - antes, conferir `git status` e avisar se o staging estiver desatualizado.
- **Apagar arquivos é com o dev.** O `rm` está bloqueado para o Claude; ele lista o comando
  `! rm ...` para o dev rodar e só depois roda a verificação completa.
- **Comandos pequenos e de propósito único**, com caminhos absolutos; CodeGraph antes de `grep`.
- **Mensagens coladas:** quando a mensagem do dev chega inteira como texto colado, confirmar
  rapidamente que é o pedido dele antes de executar.
- **Imagens/arquivos binários que o dev envia:** se a ferramenta mostrar só um ícone genérico de
  arquivo em vez do conteúdo de verdade, avisar e pedir reenvio antes de seguir (aconteceu na
  etapa 7f — o primeiro envio veio como ícone de PNG quebrado, não a arte).
- O dev escreve em português; respostas em português.

## Decisões técnicas importantes

O plano tem o detalhe; aqui só o que mais pesa ao continuar.

- **Pilha:** PHP 8.5 + Slim 4 + PHP-DI (no padrão do repositório `dancarvalhodev/wecker`) + Twig +
  Tailwind CSS 4.3.3 (CLI standalone, sem Node) + Caddy, tudo em Docker.
- **Idiomas:** português na raiz, inglês em `/en`. O idioma vem do endereço (`Locale::fromPath`),
  e os templates recebem o objeto `i18n` (`text()`, `url()`, `switchUrl()`). Ver
  `.claude/arch-explain/i18n.md`.
- **Conteúdo:** tudo em Markdown em `www/content/`, com etiqueta YAML no topo. Uma classe só lê
  tudo (`ContentRepository`), sem cache. HTML escrito à mão no Markdown sai como texto, por
  segurança. Os testes leem o conteúdo real e conferem os campos obrigatórios. Como o slug é o
  nome do arquivo e a tradução casa pelo `translation_key` (não pelo nome), ver
  `.claude/arch-explain/blog-content.md`.
- **`AbstractController` é o ponto central dos controllers de página:** injeta automaticamente
  `i18n`, `site`, `profile`, `canonical` e `altUrl` (hreflang) em todo `render()`; `ContentRepository`
  e `UrlGenerator` ficam protegidos ali, então `HomeController`/`BlogController` não precisam dos
  seus próprios. `SitemapController` é a exceção de propósito: não estende `AbstractController`
  porque não é uma página HTML de um idioma só. Ver `.claude/arch-explain/seo.md`.
- **Realce de código** (etapa 6e) usa `tempest/highlight`, com um tema CSS próprio (classes
  `hl-*` em `app.css`) nas cores da identidade, não um dos temas prontos do pacote.
- **Giscus** (etapa 6f) está configurado com o repositório real
  `dancarvalhodev/landing-page-comments`. O tema customizado
  (`www/public/assets/css/giscus-{light,dark}.css`) só funciona com HTTPS — ver "Pendências".
- **`SITE_URL` e `APP_ENV` são obrigatórios** no `.env`; o app não sobe sem eles. Ver
  `.claude/arch-explain/site-url.md`.
- **`CanonicalPathMiddleware`** (barra final e barras duplicadas) foi mantido de propósito em vez
  do pacote `middlewares/trailing-slash`.

## Pendências conhecidas

- **Texto do projeto "Landing Page"** (`content/projects/{pt,en}/landing-page.md`): já afirma a
  hospedagem na Oracle Cloud e a publicação pelo GitHub Actions, que dependem das etapas 10 e 11. Rever antes do deploy se algo mudar. O repositório também precisa
  estar público quando o site for ao ar, senão o link do cartão dá 404.
- **Etapa 10 em andamento:** dividida em 10a (Caddy de borda e rede `edge`), 10b (`cloud-init`) e
  10c (README com o passo a passo). Ver o "Progresso" do plano e `.claude/arch-explain/infra.md`.
- **Etapa 11:** o build de produção exige `IMAGE_TAG` (que também vira o `ASSET_VERSION`); o
  workflow deve passar o identificador do commit. O projeto do Compose precisa se chamar
  `site-prod` (`-p`), porque o Caddyfile de borda usa esse nome.
- **A prévia (`preview.<domínio>`) foi retirada em 2026-10-06, por decisão do dev:** o projeto é
  simples e a conferência é feita no ambiente local. Não existe `preview.yml` na etapa 11. O
  valor `preview` continua aceito em `APP_ENV` (`Config` e testes), sem uso. Como recolocar:
  `.claude/arch-explain/infra.md`.
- **Versão do Tailwind em dois lugares:** `bin/tailwind/Dockerfile` e `bin/caddy/Dockerfile`.
  Atualizar os dois juntos.
- **Artigos do blog** (`ola-mundo.md`, `hello-world.md`) ainda são de exemplo; saem antes do
  deploy se não houver artigo real.
- **Tema do Giscus (etapa 6f):** só confirmável visualmente depois do deploy com HTTPS (etapas
  9+) — em `http://localhost`, o Giscus ignora o CSS customizado e usa o visual padrão do GitHub.
- **`robots.txt` (etapa 7e):** libera tudo (`Allow: /`), sem bloquear páginas de tag nem de busca.
  Foi uma decisão consciente, não uma pendência técnica — fica pra o dev revisitar se quiser
  restringir depois.
- **Etapa 12:** reescrever o `CLAUDE.md` com comandos e arquitetura completos.
- **Etapa 13:** depois da 12, revisar a documentação escrita (e os `.claude/arch-explain/`) antes
  da etapa de spec, a pedido do dev.
