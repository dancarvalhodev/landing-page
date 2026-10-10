# landing-page

Site pessoal de Daniel Carvalho: um portfólio de rolagem única com blog, em português e inglês.
Feito em PHP, sem banco de dados: todo o conteúdo são arquivos Markdown neste repositório.

## O que tem

- **Página inicial** com sobre, projetos (com filtro por categoria), experiência, habilidades e
  contato.
- **Blog** com tags, busca, realce de código feito no servidor e comentários pelo Giscus.
- **Dois idiomas:** português na raiz (`/`) e inglês em `/en`. A tradução de cada artigo é
  opcional.
- **Tema claro e escuro**, seguindo o sistema e lembrando a escolha.
- **SEO:** `canonical`, `hreflang`, Open Graph, JSON-LD `Person`, `sitemap.xml` e `robots.txt`.

## Tecnologias

| Camada | Escolha |
|---|---|
| Linguagem | PHP 8.5 |
| Aplicação | Slim 4, PHP-DI e Twig |
| Conteúdo | Markdown com `league/commonmark`; realce com `tempest/highlight` |
| Estilo | Tailwind CSS 4, pela CLI standalone (sem Node) |
| Servidor web | Caddy |
| Ambiente | Docker e Docker Compose |
| Qualidade | PHPUnit, PHPStan e PHP-CS-Fixer |
| Publicação | GitHub Actions, imagens no GHCR e uma VM ARM na Oracle Cloud |

## Rodar no computador

Só é preciso ter Docker e Docker Compose. PHP, Composer e Node não precisam estar instalados.

```
cp env-example .env
docker compose up -d --build
docker compose exec php composer install
```

No `.env`, ajuste `HOST_UID` e `HOST_GID` para a saída de `id -u` e `id -g`, se forem diferentes
de 1000. O site abre em `http://localhost:8080`.

São três contêineres: `caddy` (servidor web), `php` (PHP-FPM) e `tailwind`, que regenera o CSS a
cada mudança nos templates. O código entra por pasta compartilhada, então salvar um arquivo já
muda o site.

## Comandos do dia a dia

| Para | Comando |
|---|---|
| Rodar os testes | `docker compose exec php composer test` |
| Rodar um teste só | `docker compose exec php vendor/bin/phpunit --filter <nome>` |
| Análise estática | `docker compose exec php composer stan` |
| Conferir o estilo do código | `docker compose exec php composer cs` |
| Corrigir o estilo do código | `docker compose exec php composer cs:fix` |
| Ver os logs | `docker compose logs -f php` |
| Reiniciar o Caddy depois de mudar o Caddyfile | `docker compose restart caddy` |
| Derrubar tudo | `docker compose down` |

## Como o projeto é organizado

```
docker-compose.yml        ambiente de desenvolvimento
docker-compose.prod.yml   produção: as duas imagens, sem pasta compartilhada
bin/                      um Dockerfile por serviço (php, caddy, tailwind)
config/                   Caddyfile e arquivos .ini do PHP
infra/                    servidor: cloud-init, Caddy de borda e o roteiro de deploy
.github/workflows/        ci.yml (testes) e deploy.yml (publicação)
www/                      a aplicação
  public/                 index.php e os arquivos estáticos (JS, fontes, imagens)
  bootstrap/Main.php      monta o contêiner, o app Slim, os middlewares e as rotas
  routes/web.php          todas as rotas
  src/                    controllers, leitura de conteúdo, idiomas e extensões do Twig
  templates/              Twig: layout, home, blog e páginas de erro
  content/                o conteúdo, em Markdown
  lang/                   textos de interface, por idioma
  resources/css/app.css   entrada do Tailwind, com as cores e fontes da identidade
  tests/                  testes
```

Uma requisição passa por: Caddy → `public/index.php` → `Main` → middlewares → rota em
`routes/web.php` → controller → template Twig. Arquivos que existem em `public/` o Caddy entrega
direto, sem passar pelo PHP.

## Onde fica o conteúdo

Tudo em `www/content/`. Cada arquivo tem uma etiqueta em YAML no topo, entre linhas `---`, e o
texto em Markdown embaixo.

| Arquivo | O que guarda |
|---|---|
| `site.md` | Nome, e-mail e redes sociais |
| `pt.md` e `en.md` | Chamada, apresentação, experiência, habilidades e formação; o texto do arquivo é o "sobre" |
| `projects/{pt,en}/<slug>.md` | Um projeto por arquivo |
| `blog/{pt,en}/<slug>.md` | Um artigo por arquivo |

HTML escrito à mão dentro do Markdown aparece como texto, por segurança.

## Publicar um artigo

1. Crie uma branch a partir da `master` atualizada:

   ```
   git switch master
   git pull
   git switch -c artigo/<nome>
   ```

2. Crie o arquivo em `www/content/blog/pt/<slug>.md`. O nome do arquivo vira o endereço:
   `meu-artigo.md` é publicado em `/blog/meu-artigo`.

   ```markdown
   ---
   title: Título do artigo
   description: Uma frase que resume o artigo. Aparece na lista do blog e no Google.
   date: 2026-10-09
   tags: [PHP, Docker]
   translation_key: meu-artigo
   ---

   O texto do artigo, em Markdown.
   ```

   | Campo | Obrigatório | Para quê |
   |---|---|---|
   | `title` | Sim | Título da página e da lista |
   | `description` | Sim | Resumo na lista, no Google e ao compartilhar o link |
   | `date` | Sim | Ordena a lista, do mais novo para o mais antigo. Formato `AAAA-MM-DD` |
   | `tags` | Não | Cada tag ganha uma página em `/blog/tag/<tag>` |
   | `translation_key` | Só se houver tradução | Liga o artigo à versão no outro idioma |

3. **Tradução (opcional):** crie `www/content/blog/en/<slug-em-ingles>.md` com o mesmo
   `translation_key`. Os nomes dos dois arquivos podem ser diferentes; o que liga um ao outro é
   esse campo. Sem tradução, o link de idioma leva para a lista do blog no outro idioma.

4. Confira em `http://localhost:8080/blog` e rode os testes: `docker compose exec php composer
   test`. Um artigo sem `title`, `description` ou `date` faz os testes falharem.

5. Faça o commit e o `git push`, abra um Pull Request para a `master` e espere a verificação
   ficar verde.

6. Faça o merge. O site é publicado sozinho, alguns minutos depois.

Cuidado ao renomear o arquivo de um artigo já publicado: o endereço muda e os links antigos
quebram.

## Publicação

Cada merge na `master` dispara o `deploy.yml`, que roda os testes, constrói as duas imagens,
envia ao GHCR e manda a VM atualizar. O `ci.yml` roda as mesmas conferências em todo Pull Request.

- **Montar o servidor do zero, fazer o primeiro deploy, proteger a `master` e voltar para uma
  versão anterior:** [`infra/README.md`](infra/README.md).

## Documentação

Em `.claude/arch-explain/` há um documento por assunto, com o porquê de cada decisão, explicado
por analogias:

| Documento | Assunto |
|---|---|
| `caddy.md` | O Caddyfile do app |
| `tailwind.md` | O Tailwind sem Node e as cores da identidade |
| `i18n.md` | Os dois idiomas e as rotas |
| `blog-content.md` | Do arquivo Markdown ao artigo na tela |
| `seo.md` | Metadados, `hreflang`, Open Graph, JSON-LD e sitemap |
| `site-url.md` | Para que serve o `SITE_URL` |
| `scroll-reveal.md` | As animações ao rolar a página |
| `producao.md` | As imagens de produção |
| `infra.md` | O servidor, o Caddy de borda e o cloud-init |
| `ci-cd.md` | Os testes e a publicação automáticos |

O plano do projeto e o andamento estão em `.claude/plans/arquitetura-landing-page.md`.
