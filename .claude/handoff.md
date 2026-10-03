# Passagem de sessão

Resumo para continuar o desenvolvimento em outro computador (ou voltar a este). Quem terminar uma
sessão atualiza este arquivo antes do último commit; quem começar lê este arquivo primeiro.

Última atualização: 2026-10-02.

## Onde paramos

- **Branch:** `feat/site-foundation`, já enviada ao GitHub (`origin`). Um commit por etapa.
- **Plano:** `.claude/plans/arquitetura-landing-page.md`. A seção "Progresso", no fim do arquivo,
  marca o que está confirmado.
- **Confirmadas:** etapas 1, 2, 3, 4a e 4b.
- **Etapa 4c (textos reais):** entregue e commitada, mas o dev ainda não disse "confirmada".
  Primeira coisa a fazer: perguntar se a 4c está aprovada e, se sim, marcá-la no progresso.
- **Próxima:** etapa 5 — Home (seções com o conteúdo de `www/content/`, filtro de projetos, tema
  claro/escuro, animações). A home atual é só uma amostra provisória da identidade visual.

## Como retomar em um computador novo

1. `git pull` na branch `feat/site-foundation`.
2. `cp env-example .env` e ajustar `HOST_UID` e `HOST_GID` para a saída de `id -u` e `id -g`.
3. `docker compose up -d --build`.
4. `docker compose exec php composer install` (a pasta `vendor/` não vai para o git).
5. Conferir: `docker compose exec php composer test` (38 testes passando) e
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
- **MVP sempre.** O mínimo de arquivos e classes que resolve a etapa de hoje; nada antecipado
  (cache, validadores, camadas "para o futuro"). A etapa 4b foi refeita porque a primeira versão
  tocou 57 arquivos.
- **Explicações com analogias**, curtas, começando pela analogia e depois ligando ao código. As
  explicações que ele pede para guardar ficam em `.claude/arch-explain/`.
- **Código com poucos comentários:** só o porquê que o código não mostra, em uma linha.
- **Código claro para júnior e pleno:** sem mágica desnecessária (nada de `extract()`, regex onde
  um laço basta, nomes enganosos). Ex.: `Translator::translate()` virou `InterfaceTexts::textFor()`.
- **Git é do dev.** O Claude nunca roda `git add`, `commit`, `push`, `stash` nem abre PR. Quando
  pedido, só escreve a mensagem de commit:
  - em português, título + corpo explicando o quê e o porquê;
  - **sem** `:sparkles:` no início (a ferramenta do dev acrescenta sozinha);
  - terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`;
  - antes, conferir `git status` e avisar se o staging estiver desatualizado.
- **Apagar arquivos é com o dev.** O `rm` está bloqueado para o Claude; ele lista o comando
  `! rm ...` para o dev rodar e só depois roda a verificação completa.
- **Comandos pequenos e de propósito único**, com caminhos absolutos; CodeGraph antes de `grep`.
- **Mensagens coladas:** quando a mensagem do dev chega inteira como texto colado, confirmar
  rapidamente que é o pedido dele antes de executar.
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
  segurança. Os testes leem o conteúdo real e conferem os campos obrigatórios.
- **`SITE_URL` e `APP_ENV` são obrigatórios** no `.env`; o app não sobe sem eles. Ver
  `.claude/arch-explain/site-url.md`.
- **`CanonicalPathMiddleware`** (barra final e barras duplicadas) foi mantido de propósito em vez
  do pacote `middlewares/trailing-slash`.

## Pendências conhecidas

- **Etapa 6b:** reescrever as descrições profissionais. Hoje P21 e Cubo têm o mesmo texto
  provisório.
- **Artigos do blog** (`ola-mundo.md`, `hello-world.md`) ainda são de exemplo; saem antes do
  deploy se não houver artigo real.
- **Etapa 8:** a função `asset()` do Twig não vai achar o CSS na imagem de produção (ele fica só na
  imagem do Caddy); precisará de outra fonte para o `?v=`.
- **Etapa 11:** reescrever o `CLAUDE.md` com comandos e arquitetura completos.
