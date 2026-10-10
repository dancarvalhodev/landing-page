# Giscus — comentários com as cores do site

Arquivos explicados: `www/templates/blog/_giscus.html.twig`,
`www/public/assets/js/giscus-theme.js`, `www/public/assets/css/giscus-{light,dark}.css` e o
trecho do `config/caddy/Caddyfile` que cuida desses dois CSS.

Pense no Giscus como uma **loja alugada dentro do seu prédio**. A loja é de outra empresa
(`giscus.app`), tem a própria porta e as próprias regras: é um `iframe`, um documento separado
dentro da página do artigo. Você não entra lá para pintar as paredes. O que dá para fazer é
entregar um **catálogo de cores** (um arquivo CSS) e pedir que a loja o use.

Os comentários em si ficam no GitHub, nas Discussions do repositório
`dancarvalhodev/landing-page-comments`. Cada artigo vira uma discussão, achada pelo caminho da
página (`data-mapping="pathname"`).

## O caminho, em ordem

1. **O template deixa o pedido escrito.** O `_giscus.html.twig` tem só uma
   `<div class="giscus">` com as configurações em atributos `data-*` (repositório, categoria,
   idioma). Ele não carrega nada do Giscus.

2. **O `show.html.twig` entrega os endereços dos catálogos.** A tag do `giscus-theme.js` leva
   `data-light` e `data-dark`, montados pelo `asset_absolute()`: endereço completo, com `https://`
   e com o `?v=` da versão.

3. **O `giscus-theme.js` chama a loja já com o catálogo certo.** Ele copia as configurações da
   `<div>` para uma tag `<script>` nova, acrescenta `data-theme` (o CSS claro ou o escuro, conforme
   o tema do site naquele momento) e só então carrega o `client.js` do Giscus. O `client.js` lê
   esses atributos, monta o `iframe` dentro da `<div class="giscus">` e o iframe já nasce com as
   cores do site.

4. **O iframe busca o catálogo na nossa portaria.** Ele pede `giscus-light.css` ou
   `giscus-dark.css` ao Caddy, que entrega com o cabeçalho
   `Access-Control-Allow-Origin: https://giscus.app`.

5. **Trocou o tema do site? O script avisa a loja.** Quando a classe do `<html>` muda (botão de
   tema) ou o sistema troca entre claro e escuro, o script manda uma mensagem ao iframe
   (`postMessage` com `setConfig.theme`) com o endereço do outro CSS.

## Por que cada peça é assim

Cada item abaixo foi um defeito real, visto em produção em 2026-10-10.

- **O cabeçalho de CORS no Caddy.** O Giscus carrega o tema com
  `<link crossorigin="anonymous">`. Nesse modo o navegador exige um "bilhete assinado" na
  resposta dizendo que `giscus.app` pode usar o arquivo. Sem o bilhete, ele baixa o CSS e joga
  fora, sem aviso na tela. Ver `caddy.md`.

- **Os endereços vêm do template, com `?v=`.** Tudo em `/assets` sai com cache de um ano. Se o
  script montasse o endereço sozinho, sem versão, uma mudança de cor nunca chegaria a quem já
  visitou o site.

- **O tema vai junto no carregamento, não depois.** A primeira versão carregava o Giscus com o
  tema padrão e corrigia em seguida, por mensagem. Isso falhou de dois jeitos:
  - o script esperava a mensagem `discussion` para saber que o iframe estava pronto, mas o Giscus
    só a envia com `data-emit-metadata="1"`, e aqui é `0`;
  - mesmo reagindo a qualquer mensagem, ao trocar de idioma os comentários às vezes ficavam com
    as cores padrão até recarregar a página. A causa exata não foi isolada; dependia do tempo de
    chegada das mensagens.

  Com o `data-theme` certo desde o início, o resultado não depende mais desse tempo, e some o
  instante com as cores padrão a cada carregamento.

- **O script ainda reenvia o tema a cada mensagem do Giscus.** O iframe manda mensagens sozinho
  (a altura dele, por exemplo). Reenviar o tema nessas horas cobre dois casos: o visitante trocou
  o tema antes de o iframe estar pronto, e o iframe foi recarregado pelo próprio Giscus (ao sair
  da conta). Mandar o mesmo tema de novo não tem efeito.

- **Os CSS do tema não declaram `color-scheme`.** O Giscus usa `color-scheme: light dark` nos
  dois lados: no `iframe` e no documento de dentro. Enquanto os dois concordam, o fundo do iframe
  é transparente e aparece o fundo da página. Se um lado divergir, o navegador troca o "vidro"
  por uma "placa opaca":
  - com `color-scheme: light` no nosso CSS, apareceu uma caixa branca (sistema em modo escuro,
    site no tema claro);
  - com `.giscus-frame { color-scheme: normal }` no `app.css`, apareceu uma caixa escura.

  A regra é não mexer em `color-scheme` em nenhum dos dois lados.

- **Os CSS repetem as cores do `app.css`.** O iframe é outro documento: as variáveis do site
  (`--page`, `--ink`...) não existem lá dentro. Por isso os valores estão copiados, com os nomes
  de variável que o Giscus espera (`--color-canvas-default`, `--color-fg-default`...).

## Pegadinhas

- **Em `http://localhost` o tema da marca não aparece.** O iframe é `https` e o navegador não o
  deixa baixar um CSS de `http`. Para os comentários não ficarem sem estilo, o script usa ali os
  temas prontos `light` e `dark` do Giscus. O visual da marca só dá para conferir em produção.
- **Mudou a paleta? São três lugares:** `www/resources/css/app.css` e os dois
  `giscus-{light,dark}.css`.
- **Os dois CSS definem só parte das variáveis do Giscus.** Se algum elemento aparecer sem cor
  (realce de código dentro de um comentário, por exemplo), compare com o
  `https://giscus.app/themes/custom_example.css`.
- **`data-lang` é `pt` ou `en`, nunca `pt-BR`.** O Giscus não reconhece `pt-BR` e o iframe
  responde 404.
- **Sem JavaScript não há comentários.** Já era assim: o Giscus inteiro depende de JavaScript.

## Como conferir em produção

```bash
curl -sI https://danielcarvalho.dev/assets/css/giscus-dark.css
```

A resposta deve trazer `access-control-allow-origin: https://giscus.app`. No navegador, abrir um
artigo e conferir:

- os comentários já aparecem com as cores do site, nos temas claro e escuro;
- o botão de tema troca as cores dos comentários junto;
- trocar de idioma mantém as cores, sem recarregar;
- a área de reações mostra o fundo da página, sem caixa branca nem escura.
