---
name: novo-post
description: Cria um artigo novo no blog do site (www/content/blog/{pt,en}/), perguntando ao dev um campo por vez (idioma, título, texto, descrição, tags, data e endereço) e escrevendo a tradução quando ele pedir. Use sempre que o usuário quiser escrever, criar, publicar ou adicionar um post, artigo ou texto no blog ("novo post", "quero escrever um artigo", "adiciona um post no blog", "/novo-post"), mesmo que ele não cite a skill pelo nome.
argument-hint: "[assunto do artigo]"
---

# Novo post no blog

Você vai montar um artigo do blog junto com o dev, perguntando **um campo por vez**. Ele escreve
ou aprova cada texto; você cuida do formato do arquivo, das regras que o site exige e da
tradução.

Antes de começar, leia `.claude/arch-explain/blog-content.md`: ele explica como o arquivo vira
página, por que o endereço é o nome do arquivo e como as traduções se ligam.

## Como é um artigo

Cada artigo é um arquivo Markdown com uma etiqueta YAML no topo:

```markdown
---
title: Olá, mundo (exemplo)
description: Artigo de exemplo para testar o motor de conteúdo.
date: 2026-10-02
tags: [PHP, Exemplo]
translation_key: ola-mundo
---

Texto do artigo em Markdown.
```

| Componente | Onde aparece | Regra |
|---|---|---|
| Nome do arquivo | o endereço: `/blog/<nome>` ou `/en/blog/<nome>` | minúsculas, sem acento, palavras separadas por hífen; não mudar depois de publicado (quebra links) |
| `title` | `<h1>`, aba do navegador, lista do blog, busca | obrigatório |
| `description` | lista do blog, `<meta name="description">`, Open Graph, busca | obrigatório; uma ou duas frases, até ~160 caracteres |
| `date` | data do artigo e ordem da lista (mais novo primeiro) | obrigatório; formato `AAAA-MM-DD`, sem aspas |
| `tags` | etiquetas clicáveis (`/blog/tag/<tag>`) e busca | opcional; uma ou duas palavras cada, sem barra |
| `translation_key` | liga o artigo à tradução no outro idioma | igual nos dois arquivos; por convenção, o nome do arquivo em português |
| Corpo | o artigo em si | Markdown |

Os três obrigatórios são conferidos por `ContentRepositoryTest`: um artigo sem eles derruba o CI.

## 1. Preparar

1. Veja os artigos que já existem em `www/content/blog/pt/` e `www/content/blog/en/`.
2. Anote as tags já usadas, para o dev reaproveitar. "PHP" e "php" viram duas tags diferentes.
3. Se ainda existirem os artigos de exemplo (`ola-mundo.md` e `hello-world.md`), avise que eles
   continuam no ar. Pergunte se o dev quer removê-los quando o artigo novo estiver pronto.
   Apagar é com ele: passe o comando `! rm ...` para ele rodar.

## 2. Perguntar, um campo por vez

Faça uma pergunta por mensagem, nesta ordem, e espere a resposta antes da próxima. Quando
fizer sentido, ofereça uma sugestão; o dev pode aceitar, ajustar ou mandar a dele. Se ele já
tiver passado um campo (no argumento ou em uma resposta anterior), não pergunte de novo.

1. **Idioma.** Só português, só inglês, ou os dois? Nos dois, ele escreve em um idioma e você
   traduz para o outro.
2. **Título.**
3. **Texto do artigo.** Ele pode colar o texto pronto ou pedir ajuda para escrever a partir de
   tópicos. Ao receber, ajuste só o formato (veja "Regras do Markdown" abaixo) e mostre o que
   mudou. Não reescreva o conteúdo sem pedir.
4. **Descrição.** Sugira uma a partir do texto, com até ~160 caracteres.
5. **Tags.** Mostre as tags que já existem e sugira de duas a quatro.
6. **Data.** Sugira a data de hoje.
7. **Endereço.** Sugira um nome de arquivo a partir do título, sem acentos (ex.: "Cache com
   Redis no PHP" vira `cache-com-redis-no-php`). Nos dois idiomas, sugira também o nome em
   inglês (`redis-caching-in-php`) e mostre os dois endereços finais. Confira se o nome já
   existe na pasta.

## 3. Regras do Markdown

- **Não use `#` no texto.** O título já é o `<h1>` da página; as seções começam em `##`.
- **Blocos de código com a linguagem** (` ```php `, ` ```bash `, ` ```yaml `...): é o que liga o
  realce de cores.
- **HTML não funciona.** O site escapa qualquer HTML escrito no Markdown, que aparece como texto.
  Use só Markdown.
- **Tabelas, listas, links, negrito e código em linha** funcionam (Markdown do GitHub).
- **Imagens:** o site ainda não tem uma pasta para imagens de artigos. Se o dev quiser uma,
  pare e combine com ele antes; não crie pasta nem estrutura por conta própria.

## 4. Traduzir

Quando o artigo for nos dois idiomas, traduza título, descrição, tags e corpo. **Não traduza o
código** dentro dos blocos; os comentários dentro do código podem ser traduzidos. Use o mesmo
`translation_key` e a mesma data nos dois arquivos. Mostre a tradução ao dev antes de gravar.

## 5. Gravar e conferir

1. Mostre o arquivo completo (ou os dois) e peça a aprovação antes de gravar.
2. Grave em `www/content/blog/pt/<nome>.md` e/ou `www/content/blog/en/<nome>.md`.
3. Rode os testes:
   `docker compose -f <raiz>/docker-compose.yml exec -T php composer test`.
4. Abra o artigo no ambiente local (`http://localhost:8080/blog/<nome>` e
   `http://localhost:8080/en/blog/<nome>`). Confira que responde 200, que o título e a data
   aparecem e, nos dois idiomas, que o link de troca de idioma leva à tradução. Se o ambiente
   não estiver no ar, suba com `docker compose up -d`.
5. Relate o que foi criado, o que foi verificado e o endereço para o dev ver no navegador.

## 6. O git é do dev

Esta skill não faz `git add`, `commit`, `push` nem abre PR. Lembre que a `master` publica: o
artigo vai por uma branch nova e um Pull Request, e o merge coloca o artigo no ar. Se o dev
pedir, escreva a mensagem de commit em português, com título e corpo.
