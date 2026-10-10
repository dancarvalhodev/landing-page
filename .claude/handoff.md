# Passagem de sessão

Resumo para continuar o desenvolvimento em outro computador (ou voltar a este). Quem terminar uma
sessão atualiza este arquivo antes do último commit; quem começar lê este arquivo primeiro.

Última atualização: 2026-10-10.

## Onde paramos

- **O plano está concluído e o site está no ar.** As etapas 1 a 13 de
  `.claude/plans/arquitetura-landing-page.md` estão confirmadas. A etapa 14 (spec do projeto) foi
  retirada em 2026-10-09, por decisão do dev: seria redundante com o `README.md`, o `CLAUDE.md` e
  os documentos de `.claude/arch-explain/`.
- **Não há próxima etapa planejada.** O trabalho daqui em diante é manutenção: artigos novos,
  ajustes e as pendências listadas no fim deste arquivo. Um trabalho maior pede um plano novo, em
  etapas, aprovado pelo dev antes de começar.
- **Para o estado real do projeto**, ler o `README.md` e o `CLAUDE.md`. O plano é um registro
  histórico e difere do código em alguns pontos, listados no fim dele.
- **Publicação:** cada merge na `master` roda os testes, constrói as imagens e atualiza a VM
  (`.github/workflows/deploy.yml`). O roteiro de servidor, primeiro deploy, proteção da `master` e
  rollback é o `infra/README.md`.
- **Decisões de infraestrutura:** a VM é ARM (`VM.Standard.A1.Flex`), na Oracle Cloud; o DNS fica
  no Squarespace; o repositório e as imagens no GHCR são públicos, então a VM baixa as imagens sem
  token.
- **Atenção ao git:** a `master` publica e deve estar protegida. Toda alteração nova começa de uma
  branch criada a partir da `master` atualizada e vai por Pull Request.

## Como retomar em um computador novo

1. `git switch master` e `git pull`; o trabalho novo começa de uma branch criada a partir dela.
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
  (Blog → 6a-6f), a 7 (SEO → 7a-7f), a 8 (8a-8c), a 9 (9a-9d), a 10 (10a-10b) e a 11 (11a-11c).
  **Registrar a divisão no plano e neste arquivo antes de escrever qualquer arquivo da etapa**: o
  dev interrompeu a 11a porque a divisão ainda não estava registrada. Cuidado ao reaproveitar uma
  letra já usada em outro lugar do plano (ex.: a 6b já existia reservada quando a 7 foi dividida).
- **Questionar o que o plano pede quando não vale o custo.** O dev retirou a prévia (etapa 10a) e
  a spec (etapa 14) depois de perguntar se eram necessárias. A regra a seguir é interpretação do
  Claude a partir dessas duas decisões, não um pedido literal dele: ao chegar em uma etapa cara e
  de pouco retorno para um projeto deste tamanho, dizer isso antes de começar, com a recomendação.
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
  (`www/public/assets/css/giscus-{light,dark}.css`) depende de um cabeçalho de CORS no Caddy —
  ver "Pendências".
- **`SITE_URL` e `APP_ENV` são obrigatórios** no `.env`; o app não sobe sem eles. Ver
  `.claude/arch-explain/site-url.md`.
- **`CanonicalPathMiddleware`** (barra final e barras duplicadas) foi mantido de propósito em vez
  do pacote `middlewares/trailing-slash`.

## Pendências conhecidas

O que não funciona como esperado ou é provisório:

- **Tema do Giscus (etapa 6f): duas correções em 2026-10-10, falta conferir a segunda em
  produção.** A primeira (já no ar): o Caddy entregava `giscus-{light,dark}.css` sem
  `Access-Control-Allow-Origin` (ver `.claude/arch-explain/caddy.md`). A segunda: o
  `giscus-theme.js` esperava a mensagem `discussion` para mandar o tema, mas o Giscus só a envia
  com `data-emit-metadata="1"`; agora ele reage a qualquer mensagem do Giscus. Uma terceira,
  também por conferir: uma caixa branca atrás dos comentários no tema claro com o sistema em modo
  escuro, porque o iframe (`color-scheme: light dark`) e o CSS do tema (`color-scheme: light`)
  discordavam; o `color-scheme` saiu dos dois CSS. Não sobrescrever o `color-scheme` do iframe
  no `app.css`: o documento do Giscus também usa `light dark`, e a tentativa com `normal` gerou
  uma caixa escura. Em
  `http://localhost` o Giscus ignora o CSS customizado, então o visual só dá para conferir depois
  do deploy. Os dois
  CSS definem só parte das variáveis `--color-*` do Giscus; se sobrar algum elemento sem cor,
  comparar com o `custom_example.css` oficial.
- **Os artigos do blog no ar são de exemplo** (`ola-mundo.md` e `hello-world.md`). Foram
  publicados no primeiro deploy e devem dar lugar a artigos reais.
- **O `infra/cloud-init.yaml` corrigido nunca rodou em uma VM.** Na VM atual ele não foi aplicado
  (um acento foi corrompido ao colar no painel) e os passos foram feitos à mão por SSH. Os
  comandos são os mesmos, mas o arquivo só será provado na próxima VM. Ver
  `.claude/arch-explain/infra.md`.

O que nunca foi verificado:

- **Lighthouse** (performance, acessibilidade e SEO acima de 95), previsto na verificação do plano.
- **O JSON-LD no Rich Results Test e a prévia do link no LinkedIn Post Inspector.**
- **A proteção da `master` e o rollback pelo botão Revert**, que são do painel do GitHub. O
  rollback pelo servidor foi simulado no computador.
- **Se as regras de `iptables` do cloud-init são necessárias**, já que o Docker cria as próprias.

Decisões conscientes, para revisitar se o contexto mudar:

- **A prévia (`preview.<domínio>`) foi retirada em 2026-10-06:** o projeto é simples e a
  conferência é feita no ambiente local. O valor `preview` continua aceito em `APP_ENV` (`Config`
  e testes), sem uso. Como recolocar: `.claude/arch-explain/infra.md`.
- **A spec do projeto (etapa 14) foi retirada em 2026-10-09.** Vale reconsiderar se o projeto for
  passado a outra pessoa ou reescrito em outra tecnologia.
- **`robots.txt` (etapa 7e):** libera tudo (`Allow: /`), sem bloquear páginas de tag nem de busca.
- **O deploy não tem cache de build** e deixa o site fora por um ou dois segundos a cada
  publicação.

Cuidados permanentes (também no `CLAUDE.md`, seção "Armadilhas"):

- **Versão do Tailwind em dois lugares:** `bin/tailwind/Dockerfile` e `bin/caddy/Dockerfile`.
- **O projeto do Compose no servidor precisa se chamar `site-prod`,** porque o Caddyfile de borda
  usa esse nome.
- **O texto do projeto "Landing Page"** (`content/projects/{pt,en}/landing-page.md`) afirma a
  hospedagem na Oracle Cloud e a publicação pelo GitHub Actions. Se uma das duas mudar, o texto
  muda junto.
