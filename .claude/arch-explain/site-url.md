# SITE_URL — para que serve e por que é uma variável de ambiente

Arquivos explicados: `www/src/Config.php` (propriedade `siteUrl`) e
`www/src/I18n/UrlGenerator.php` (método `absolute()`).

O `SITE_URL` é o endereço oficial do site, escrito uma vez por ambiente:

| Ambiente | Valor |
|---|---|
| Desenvolvimento | `http://localhost:8080` |
| Produção | `https://<domínio>` |

Ele é obrigatório e validado, como o `APP_ENV`: sem a variável, o app não sobe.

## Quando ele é usado

Quase todos os links do site são relativos (`/blog`, `/en`), e para esses o domínio não importa.
O `SITE_URL` só entra onde o endereço completo é exigido, o que acontece nas etiquetas de SEO da
etapa 7. Até lá, nenhum template chama `UrlGenerator::absolute()`.

## Por que o SEO precisa do endereço completo

O Google e as redes sociais não navegam no site como uma pessoa. Eles leem etiquetas escondidas no
HTML, e algumas só valem com o endereço completo.

Pense em **correspondência**. Dentro do prédio, "apartamento 42" basta. Para o carteiro que vem de
fora, só serve o endereço inteiro, com rua, número e cidade. Os links normais do site são conversa
interna; as etiquetas de SEO são cartas para quem está de fora.

### 1. "Este é o meu endereço oficial" (`canonical`)

```html
<link rel="canonical" href="https://meusite.com/blog/meu-artigo">
```

A mesma página pode ser alcançada por vários endereços: com `www`, com `?utm_source=linkedin` no
fim, por `http`. Para o Google, cada um é uma página diferente com conteúdo repetido, e ele divide
a relevância entre elas. A etiqueta diz qual é a verdadeira, e a relevância se concentra nela.

### 2. "Esta página existe em outro idioma, aqui" (`hreflang`)

```html
<link rel="alternate" hreflang="pt-BR" href="https://meusite.com/blog/meu-artigo">
<link rel="alternate" hreflang="en" href="https://meusite.com/en/blog/my-article">
```

Sem isso, o Google pode mostrar a versão em português para um recrutador de fora, ou tratar as duas
versões como concorrentes. A regra do Google exige endereço completo aqui; relativo é ignorado.

### 3. "Use esta imagem e este título ao me compartilhar" (Open Graph)

```html
<meta property="og:url" content="https://meusite.com/">
<meta property="og:image" content="https://meusite.com/assets/img/capa.png">
```

Quando alguém cola o link no LinkedIn ou no WhatsApp, é o servidor deles que busca a imagem. Ele
está fora do site, então `/assets/img/capa.png` não significa nada para ele. Sem o endereço
completo, o link aparece sem prévia.

### Também usam o endereço completo

- o `sitemap.xml`, a lista de páginas entregue ao Google;
- o JSON-LD `Person`, que descreve o dono do site para os buscadores.

Os dois objetivos de SEO do projeto dependem dessas etiquetas: ser encontrado pelo nome e chegar
por links compartilhados em redes sociais.

## Por que não descobrir o domínio pela requisição

O app poderia ler o domínio do cabeçalho `Host` de cada requisição. Há dois problemas:

- **O app não enxerga o `https`.** Ele fica atrás do Caddy de borda e recebe HTTP interno. Montaria
  `http://meusite.com/...` em todas as etiquetas.
- **O `Host` é enviado por quem faz a requisição.** Alguém pode mandar um `Host` falso e fazer a
  página se declarar oficial em outro domínio.

A variável de ambiente resolve os dois. É como o endereço no cartão de visita: você o escreve, em
vez de deixar cada visitante adivinhar.

## O cuidado que ele pede

Se o `SITE_URL` estiver errado (por exemplo `http://` em vez de `https://`, ou outro domínio), o site passa a dizer ao Google que o endereço oficial é outro, e o Google obedece.
Vale conferir o valor em cada ambiente ao configurar o deploy.
