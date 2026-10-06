# Infra — como o site fica no ar no servidor

Arquivos explicados: `infra/edge/docker-compose.yml`, `infra/edge/Caddyfile`,
`infra/edge/env-example` e a parte de rede do `docker-compose.prod.yml`. Uma seção por subetapa da
etapa 10. Para as imagens em si, ver `producao.md`; para o Caddy do app, ver `caddy.md`.

## A ideia geral

Pense em um **prédio comercial com uma loja**.

- O prédio tem **uma única entrada da rua**, com um segurança. É o **Caddy de borda**.
- A loja tem o seu **porteiro interno**, que já existia: o Caddy do app (ver `caddy.md`).
- A loja **não tem porta para a rua**. Só se chega nela passando pelo segurança.

O segurança faz duas coisas que o porteiro interno não faz:

1. **Cuida do cadeado (HTTPS).** Ele pede e renova os certificados sozinho.
2. **Olha o nome no envelope.** `meusite.com` vai para a loja; `www.meusite.com` é corrigido para
   `meusite.com`.

### Por que dois Caddy, e não um só

Daria para o Caddy do app cuidar do HTTPS e dispensar a borda. A separação foi mantida porque:

- **O certificado fica longe do deploy.** A cada publicação, os contêineres do site são trocados.
  A borda não é tocada, então um deploy com problema não derruba o HTTPS nem mexe no volume dos
  certificados.
- **A imagem do site é igual em todo lugar.** O Caddy do app só fala HTTP na porta 80, no seu
  computador e no servidor. Tudo o que é específico do servidor (domínio, certificado) mora na
  borda.
- **Sobra espaço no prédio.** Outro site na mesma VM, ou uma prévia, é um bloco a mais no
  Caddyfile de borda.

### A prévia foi retirada

O plano original tinha uma segunda loja, a prévia, em `preview.<domínio>`, com senha. Ela foi
retirada em 2026-10-06, por decisão do dev: para um site pessoal com um autor só, o
`docker compose up` no computador já mostra o resultado antes de publicar, e a prévia custaria um
terceiro projeto na VM, um workflow a mais e uma senha para gerenciar.

Para colocar de volta um dia: um bloco `preview.{$DOMAIN}` no Caddyfile de borda (com
`basic_auth`, `header X-Robots-Tag "noindex, nofollow"` e `reverse_proxy site-preview:80`), subir
o `docker-compose.prod.yml` uma segunda vez com `-p site-preview` e criar o registro DNS. O
`docker-compose.prod.yml` já está pronto para isso (ver "o apelido", abaixo).

## 10a — Caddy de borda e rede compartilhada

### O desenho

```
            internet
               │  portas 80 e 443
               ▼
       ┌───────────────┐
       │ Caddy de borda│   projeto "edge"
       └───────┬───────┘
               │  rede "edge"
               ▼
       ┌───────────────┐
       │ Caddy do app  │
       └───────┬───────┘   projeto "site-prod"
               │  rede interna
               ▼
       ┌───────────────┐
       │      PHP      │
       └───────────────┘
```

São **dois projetos do Compose** na mesma máquina:

| Projeto | Arquivo | O que sobe |
|---|---|---|
| `edge` | `infra/edge/docker-compose.yml` | O Caddy de borda |
| `site-prod` | `docker-compose.prod.yml` | Caddy do app + PHP |

### O que é um "projeto" do Compose

O `-p nome` dá um nome ao conjunto de contêineres. O Compose usa esse nome como prefixo: com
`-p site-prod`, os contêineres se chamam `site-prod-caddy-1` e `site-prod-php-1`, e a rede interna
se chama `site-prod_default`.

### As redes: quem enxerga quem

No Docker, dois contêineres só conversam se estiverem na mesma rede. Dentro de uma rede, um
contêiner chama o outro pelo nome.

Cada projeto ganha a sua rede interna (`default`), e existe uma rede a mais, a `edge`, criada à
mão uma única vez (`docker network create edge`). Ela é declarada como `external: true` nos dois
arquivos, que quer dizer "esta rede já existe, não tente criar nem apagar".

| Contêiner | Rede interna do projeto | Rede `edge` |
|---|---|---|
| Caddy de borda | (não precisa) | Sim |
| Caddy do app | Sim | Sim |
| PHP | Sim | **Não** |

O Caddy do app é o único com um pé em cada rede: recebe do segurança pela `edge` e fala com o PHP
pela rede interna.

O PHP fica de fora da `edge` de propósito: o Caddy de borda não tem por que falar direto com ele.
E se um dia outro projeto com um serviço chamado `php` entrar na `edge`, o endereço `php:9000` do
Caddyfile do app continua caindo no PHP certo, porque cada PHP só existe na rede do seu projeto.

### Como o segurança acha a loja: o apelido

O Caddy do app ganha um **apelido** (alias) na rede `edge`, no `docker-compose.prod.yml`:

```yaml
networks:
    default:
    edge:
        aliases:
            - ${COMPOSE_PROJECT_NAME}
```

`COMPOSE_PROJECT_NAME` é uma variável que o próprio Compose preenche com o nome do projeto. Com
`docker compose -p site-prod ...`, o apelido é `site-prod`, e é esse nome que o Caddyfile de borda
usa (`reverse_proxy site-prod:80`).

O apelido vem do nome do projeto, em vez de ficar escrito fixo, para o mesmo arquivo poder subir
uma segunda vez com outro `-p` sem os dois responderem pelo mesmo nome.

**O cuidado:** o nome do projeto deixou de ser livre. Se a produção subir com outro `-p`, o Caddy
de borda não a encontra e responde 502. O nome `site-prod` está escrito no Caddyfile de borda e
precisa bater com o `-p` usado no deploy (etapa 11).

### O Caddyfile de borda, bloco a bloco

`{$DOMAIN}` é o Caddy lendo uma variável de ambiente. O domínio não fica escrito no repositório.

**O site:**

```
{$DOMAIN} {
	reverse_proxy site-prod:80
}
```

"Chegou visita para `meusite.com`? Entregue ao Caddy do app." `reverse_proxy` é o repasse: o
segurança recebe a visita, leva o pedido até a loja e traz a resposta de volta.

Não há nenhuma linha sobre HTTPS, e mesmo assim ele funciona. O Caddy vê um domínio de verdade no
começo do bloco e, sozinho: pede o certificado à Let's Encrypt, renova antes de vencer e
redireciona `http://` para `https://`.

**`www`:**

```
www.{$DOMAIN} {
	redir https://{$DOMAIN}{uri} permanent
}
```

Quem digita `www.meusite.com/blog` é mandado para `meusite.com/blog`. O `{uri}` preserva o caminho
e os parâmetros. Isso mantém um endereço oficial só, o que combina com a etiqueta `canonical` da
etapa 7.

### O `docker-compose.yml` de borda, linha a linha

| Trecho | Para quê |
|---|---|
| `image: caddy:2-alpine` | O Caddy oficial, sem build nosso. A borda não tem código do site |
| `ports: 80:80` | Recebe o `http://` só para redirecionar ao `https://` e para a Let's Encrypt conferir o domínio |
| `ports: 443:443` | O `https://` |
| `ports: 443:443/udp` | HTTP/3, a versão mais nova do protocolo, que usa UDP |
| `environment` | Entrega o `DOMAIN` ao Caddy. O `:?informe DOMAIN` faz o Compose recusar se ele faltar |
| `./Caddyfile:/etc/caddy/Caddyfile:ro` | O Caddyfile entra por arquivo montado, somente leitura |
| `caddy_data:/data` | Onde o Caddy guarda os certificados |
| `caddy_config:/config` | Configuração interna do Caddy |
| `networks: edge` | O pé na rede compartilhada |
| `logging` | O mesmo limite de log da pilha do site |

**O volume `caddy_data` é a peça mais sensível deste arquivo.** Ele guarda os certificados. Se for
apagado, o Caddy pede certificados novos, e a Let's Encrypt limita quantos emite por semana para o
mesmo domínio. Por isso: derrubar a borda é `docker compose down`, **nunca** `down --volumes` no
servidor.

Este é o único projeto que publica portas. O Caddy do app não tem `ports`: de fora da máquina, é
impossível chegar nele sem passar pela borda.

### O que mudou no `docker-compose.prod.yml`

- Saiu o `ports` do Caddy do app (subetapa 9d), junto com a variável `PORT_CADDY`.
- Entrou o bloco `networks` com o apelido, e a declaração da rede `edge` no fim do arquivo.

A consequência: não dá mais para abrir a pilha de produção sozinha no navegador. Para testar no
computador, é preciso subir a borda também (abaixo).

### O caminho de uma visita, do começo ao fim

Alguém abre `https://meusite.com/blog`:

1. O DNS diz que `meusite.com` é o IP da VM (subetapa 10c).
2. A visita chega na porta 443 da VM, que o Docker entrega ao Caddy de borda.
3. A borda desembrulha o HTTPS, lê o nome `meusite.com` e repassa para `site-prod:80`.
4. O Caddy do app procura um arquivo `/blog`, não acha, acha o `index.php` e interfona para o PHP
   (ver `producao.md`, "Por que o `index.php` aparece nas duas imagens").
5. O PHP monta a página e devolve; a resposta faz o caminho de volta.

O PHP recebe tudo em HTTP simples e não sabe que a visita veio por HTTPS. É por isso que o
`SITE_URL` é uma variável e não é lido da requisição (ver `site-url.md`).

### Testar tudo no computador

O domínio `localhost` é especial para o Caddy: ele gera um certificado próprio, sem falar com a
Let's Encrypt. O navegador avisa que o certificado não é confiável; para um teste, é só aceitar.

```
docker network create edge

IMAGE_TAG=teste APP_ENV=production SITE_URL=https://localhost docker compose -p site-prod -f docker-compose.prod.yml up -d --build

DOMAIN=localhost docker compose -p edge -f infra/edge/docker-compose.yml up -d
```

| Endereço | Resultado esperado |
|---|---|
| `https://localhost` | O site |
| `http://localhost/blog` | Redireciona para `https://localhost/blog` |
| `https://www.localhost/blog` | Redireciona para `https://localhost/blog` |

Para derrubar (no computador o `--volumes` pode, porque o certificado é de teste):

```
DOMAIN=localhost docker compose -p edge -f infra/edge/docker-compose.yml down --volumes
IMAGE_TAG=teste docker compose -p site-prod -f docker-compose.prod.yml down
docker network rm edge
```

A borda usa as portas 80 e 443 do computador. Se outro programa já estiver nelas, ela não sobe.

No servidor, o `DOMAIN` não vai na frente do comando: fica em `infra/edge/.env`, copiado de
`infra/edge/env-example`.

### Pegadinhas

- **502:** o projeto `site-prod` não está no ar, ou subiu com outro nome de `-p`.
- **A ordem de subida não importa**, desde que a rede `edge` exista antes. Sem ela, qualquer um
  dos dois comandos falha com "network edge not found".
- **Mudou o Caddyfile de borda?** `docker compose -p edge -f infra/edge/docker-compose.yml restart`.
- **O `.env` da borda não vai para o git.** O `.gitignore` já ignora qualquer arquivo `.env`.
