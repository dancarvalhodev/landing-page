# Infra — como o site fica no ar no servidor

Arquivos explicados: `infra/edge/docker-compose.yml`, `infra/edge/Caddyfile`,
`infra/edge/env-example`, `infra/cloud-init.yaml`, `infra/README.md` e a parte de rede do
`docker-compose.prod.yml`. Uma seção por subetapa da
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

O nome técnico do segurança é **proxy reverso**: um servidor que recebe a requisição no lugar de
outro e a repassa. "Caddy de borda" é só o apelido deste projeto para o proxy reverso que fica na
entrada da máquina e também termina o HTTPS (o cadeado é aberto nele; dali para dentro é HTTP).

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

1. O DNS diz que `meusite.com` é o IP da VM (subetapa 10b).
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

## 10b — Preparar a VM: `cloud-init.yaml` e o passo a passo

Arquivos: `infra/cloud-init.yaml` e `infra/README.md`.

Os dois andam juntos e não fazem sentido separados: o `cloud-init.yaml` é o que a máquina executa
ao nascer, e o `README.md` é o roteiro que você segue para criá-la, que inclui colar o
`cloud-init`. A primeira metade desta seção explica o arquivo; a segunda, os conceitos do roteiro.

### O que é o cloud-init

É um programa que já vem instalado nas imagens de sistema dos provedores de nuvem. Ele roda
sozinho na primeira vez que a VM liga e executa uma lista de tarefas que você entrega ao criá-la.

Pense em alugar uma casa vazia e deixar um bilhete para o caseiro: "antes de eu chegar, instale o
chuveiro, faça uma cópia da chave para o entregador e destranque o portão da frente". Você chega e
a casa está pronta. O bilhete é o `cloud-init.yaml`; o caseiro é o cloud-init.

Na prática:

1. Na tela de criação da VM na Oracle há um campo "cloud-init script". Você cola o conteúdo do
   arquivo ali (passo 3 do `infra/README.md`).
2. A VM liga pela primeira vez; o cloud-init lê o arquivo e executa tudo, como root.
3. Alguns minutos depois a máquina está pronta, sem nenhum comando digitado por SSH.

**Ele roda uma vez só.** Reiniciar a VM não o executa de novo, e mudar o arquivo depois não tem
efeito em uma máquina que já existe. O cloud-init não é instalado no seu computador: o arquivo
fica no repositório só como texto.

### Por que usar, em vez de instalar tudo à mão

- **Fica escrito.** Daqui a um ano, dá para saber exatamente o que foi feito na máquina.
- **É repetível.** Se a Oracle recolher a VM (um risco das contas gratuitas) ou você trocar de
  região, é só colar o mesmo arquivo e a máquina nova nasce igual.

### Diferença para o Ansible

São da mesma família: os dois descrevem em YAML como a máquina deve ficar.

| | cloud-init | Ansible |
|---|---|---|
| Quem executa | A própria VM, sozinha | O seu computador, entrando na VM por SSH |
| Quando | Uma vez, na primeira inicialização | Sempre que você rodar o playbook |
| Precisa de | Nada: já vem na imagem | Ansible instalado e SSH funcionando |
| Rodar de novo | Não roda | Roda, e só altera o que estiver diferente |

Aqui o cloud-init basta, porque a VM precisa de pouca coisa. Todo o resto do site vive nas
imagens, que o deploy troca.

### O arquivo, bloco a bloco

A primeira linha, `#cloud-config`, é obrigatória. Parece um comentário, mas é ela que diz ao
cloud-init como ler o resto.

**Atualizar o sistema:**

```yaml
package_update: true
package_upgrade: true
```

Equivale a `apt update` e `apt upgrade`. A imagem da Oracle pode ter semanas; isso traz as
correções de segurança antes de qualquer outra coisa.

**O grupo e o usuário de deploy:**

```yaml
groups:
  - docker

users:
  - default
  - name: deploy
    groups: [docker]
    shell: /bin/bash
    lock_passwd: true
    ssh_authorized_keys:
      - TROQUE_PELA_CHAVE_PUBLICA_DE_DEPLOY
```

| Linha | Para quê |
|---|---|
| `groups: docker` | Cria o grupo antes do usuário. O Docker ainda não está instalado neste ponto, então o grupo não existiria |
| `- default` | Mantém o usuário padrão da imagem (`ubuntu`), que é o seu acesso de administrador. Sem esta linha, ele não seria criado |
| `name: deploy` | O usuário que o GitHub Actions usa para publicar |
| `groups: [docker]` | Deixa o `deploy` rodar `docker` sem `sudo` |
| `lock_passwd: true` | Sem senha: só entra por chave SSH |
| `ssh_authorized_keys` | A chave **pública** que pode entrar como `deploy` |

São dois usuários de propósito. O `ubuntu` é você, com `sudo`, para administrar a máquina. O
`deploy` é o robô do GitHub Actions, com uma chave só dele. Se a chave do robô vazar, você a troca
sem perder o seu próprio acesso.

**O cuidado com o `deploy`:** quem pode rodar `docker` consegue, na prática, virar root na
máquina (basta subir um contêiner com o disco inteiro montado). O `deploy` não tem `sudo`, mas
tem esse poder. Por isso a chave privada dele fica só nos Secrets do GitHub e não é usada para
mais nada.

O texto `TROQUE_PELA_CHAVE_PUBLICA_DE_DEPLOY` é um marcador. Você o substitui pela chave pública
na hora de colar o arquivo na Oracle, sem alterar o arquivo do repositório. A chave pública não é
segredo, mas assim o repositório continua servindo para qualquer máquina.

**Os comandos (`runcmd`):**

```yaml
runcmd:
  - iptables -I INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT
  - iptables -I INPUT -p udp --dport 443 -j ACCEPT
  - netfilter-persistent save
  - curl -fsSL https://get.docker.com | sh
  - docker network create edge
  - mkdir -p /srv/landing-page
  - chown deploy:deploy /srv/landing-page
```

Rodam em ordem, como root, depois que os usuários já existem.

| Comando | Para quê |
|---|---|
| `iptables -I INPUT ... 80,443` | Abre as portas 80 e 443 (TCP) no firewall do Ubuntu |
| `iptables -I INPUT -p udp ... 443` | Abre a 443 em UDP, para o HTTP/3 |
| `netfilter-persistent save` | Grava as regras em disco, para valerem depois de um reinício |
| `curl ... get.docker.com \| sh` | O instalador oficial do Docker: instala o Docker e o Compose |
| `docker network create edge` | Cria a rede compartilhada da subetapa 10a |
| `mkdir` e `chown` | Cria `/srv/landing-page`, do usuário `deploy`, onde ficam os arquivos de compose |

### Os dois firewalls da Oracle

Uma visita passa por **duas barreiras** antes de chegar ao Caddy de borda, e as duas vêm fechadas
para as portas 80 e 443:

```
internet ──► Security List (fora da VM) ──► iptables (dentro da VM) ──► Caddy de borda
```

| Barreira | Onde fica | Quem abre |
|---|---|---|
| Security List | No painel da Oracle, na rede (VCN) | Você, à mão (passo 4 do `infra/README.md`) |
| `iptables` | Dentro do Ubuntu | Este `cloud-init` |

Pense em um condomínio: a Security List é a portaria do condomínio, o `iptables` é o portão da
casa. Abrir só um não adianta. É a causa mais comum de "subi tudo e o site não abre" na Oracle.

A imagem do Ubuntu da Oracle já vem com o `iptables` liberando só a porta 22 (SSH). Outras nuvens
costumam entregar o Ubuntu sem firewall interno, por isso este passo não aparece em muitos
tutoriais.

### Por que as regras são salvas antes de instalar o Docker

O Docker, ao ser instalado, cria as próprias regras de `iptables` e as recria toda vez que inicia.
Se o `netfilter-persistent save` rodasse depois dele, as regras do Docker seriam gravadas no
arquivo junto com as nossas, e a cada reinício da VM existiriam duas cópias: a do arquivo e a que
o Docker recria. Salvando antes, o arquivo guarda só as duas regras nossas.

### O que não está no arquivo, de propósito

- **O domínio e os arquivos de compose.** São copiados para a VM depois (passo 7 do `infra/README.md`).
- **Subir o site.** A primeira subida é feita pelo deploy (etapa 11).
- **Endurecimento do SSH.** A imagem da Oracle já vem sem login por senha.

### Como saber se deu certo

Depois de criar a VM, entre por SSH como `ubuntu` e rode:

```
cloud-init status --long
```

`status: done` quer dizer que terminou sem erro. Se aparecer `error`, o registro completo está em
`/var/log/cloud-init-output.log`. As conferências seguintes:

```
docker --version
docker network ls
sudo iptables -L INPUT -n --line-numbers
id deploy
```

O esperado: o Docker instalado, a rede `edge` na lista, as regras das portas 80 e 443 no topo do
`INPUT` e o `deploy` no grupo `docker`.

### Validar o arquivo sem criar uma VM

Confere só o formato (nomes de campos e indentação), sem executar nada. Roda em um contêiner
Ubuntu temporário, porque o cloud-init não existe no seu computador:

```
docker run --rm -v ./infra/cloud-init.yaml:/cloud-init.yaml:ro ubuntu:24.04 sh -c "apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq cloud-init >/dev/null 2>&1 && cloud-init schema --config-file /cloud-init.yaml"
```

A resposta esperada é `Valid schema /cloud-init.yaml`. Vale rodar de novo sempre que o arquivo
mudar.

### O arquivo só pode ter ASCII (sem acentos)

Na primeira VM criada, em 2026-10-09, o cloud-init respondeu `degraded done` com o aviso
*"Failed loading yaml blob. unacceptable character ... position 337"*, e nada do arquivo foi
executado. A posição 337 era o "ã" de um comentário em português dentro do `runcmd`.

O que aconteceu: o painel da Oracle não preservou o caractere acentuado ao receber o texto colado.
O cloud-init recebeu um byte inválido, não conseguiu ler o YAML e descartou o arquivo **inteiro**,
não só a linha. Um comentário derrubou a configuração toda.

Duas lições ficaram no projeto:

- O arquivo agora é só ASCII, e a segunda linha dele avisa disso. Para conferir depois de editar:
  `grep -nP "[^\x00-\x7F]" infra/cloud-init.yaml` não pode mostrar nada.
- `status: done` não basta. O que diz se o arquivo foi aplicado é não haver avisos e o
  `extended_status` não ser `degraded done`. O roteiro passou a dizer isso.

A validação com `cloud-init schema` não pegou o problema, porque o arquivo no disco é UTF-8
válido; o estrago acontece no caminho, ao colar. Para quem cair nisso, o roteiro tem a seção "Se o
cloud-init não foi aplicado", com os mesmos passos feitos à mão por SSH, sem recriar a máquina.

### O que só dá para confirmar na VM de verdade

A validação acima não executa os comandos. Ficam por confirmar, na criação da VM:

- se as regras de `iptables` têm o efeito esperado na imagem atual da Oracle;
- se o `netfilter-persistent` existe nessa imagem (existia nas versões anteriores do Ubuntu da
  Oracle);
- se o instalador do Docker roda sem erro em ARM64.

Se a imagem escolhida não for Ubuntu, este arquivo não serve como está: os comandos de firewall e
o usuário padrão mudam.

### O passo a passo (`infra/README.md`)

O roteiro em si está em `infra/README.md`: oito passos, da criação da conta à conferência final.
Daqui em diante, esta seção explica os conceitos que aparecem nele.

### O vocabulário da Oracle

| Termo no painel | O que é | Analogia |
|---|---|---|
| Instance | A máquina virtual | A casa |
| Image | O sistema operacional que já vem instalado | A planta da casa |
| Shape | O tamanho da máquina: tipo de processador, núcleos e memória | Quantos cômodos |
| Ampere A1 | O processador ARM incluído no plano gratuito | — |
| OCPU | Um núcleo de processador | — |
| VCN | A rede privada onde a máquina fica | O condomínio |
| Subnet | Uma divisão da VCN; a nossa é pública | A rua dentro do condomínio |
| Security List | O firewall da rede, fora da máquina | A portaria do condomínio |
| Ingress Rule | Uma regra de entrada na Security List | "Deixe entrar quem vem para a porta 443" |
| Public IP | O endereço da máquina na internet | O endereço da casa |
| Home Region | A região onde a conta e os recursos gratuitos vivem; não muda depois | A cidade |

### Por que ARM importa

A máquina gratuita usa processador ARM (`aarch64`), e o seu computador provavelmente usa x86
(`amd64`). Uma imagem Docker construída para um não roda no outro. Por isso o plano prevê
construir as imagens do site em um runner ARM do GitHub Actions (etapa 11). A imagem do Caddy de
borda (`caddy:2-alpine`) é publicada para as duas arquiteturas, e o Docker baixa a certa sozinho.

### DNS em uma frase

O DNS é a lista telefônica da internet: traduz o nome `meusite.com` para o número (IP) da máquina.
Um **registro A** é uma linha dessa lista, "este nome → este IP". O `@` significa o próprio
domínio, sem nada na frente.

São dois registros, `@` e `www`, porque `meusite.com` e `www.meusite.com` são nomes diferentes
para o DNS. Os dois apontam para a mesma VM, e a borda redireciona o `www`.

"Propagação" é o tempo que os servidores de DNS pelo mundo levam para esquecer a resposta antiga
e buscar a nova. O `dig +short` mostra o que o seu computador está enxergando naquele momento.

### Por que a ordem dos passos importa

- **O cloud-init entra na criação da VM** (passo 3), porque só roda na primeira inicialização.
- **O DNS vem antes da borda** (passo 6 antes do 7). Para emitir o certificado, a Let's Encrypt
  acessa `http://meusite.com` e confere se quem responde é mesmo o seu Caddy. Se o nome ainda não
  aponta para a VM, a conferência falha.
- **As portas vêm antes da borda** (passo 4 antes do 7), pelo mesmo motivo: a conferência chega
  pela porta 80.

### Por que o resultado final é um 502

Ao fim do roteiro, a borda está no ar, mas não há site atrás dela: `reverse_proxy site-prod:80`
não encontra ninguém e responde 502. É o resultado certo. Ele prova que o caminho inteiro
funciona (DNS, os dois firewalls, o Docker e o certificado) e que só falta a última peça, que o
primeiro deploy coloca.

### Onde ficam os arquivos no servidor

```
/srv/landing-page/
└── edge/
    ├── docker-compose.yml
    ├── Caddyfile
    ├── env-example
    └── .env            # só existe no servidor, com o DOMAIN
```

A pasta é do usuário `deploy`. O `docker-compose.prod.yml` do site chega pelo deploy, na etapa 11.

### As duas chaves SSH

Uma chave SSH é um par: a **privada** fica com quem entra, a **pública** fica na máquina onde se
entra. É como cadeado e chave: você pode distribuir cadeados (a pública) à vontade; só quem tem a
chave (a privada) abre.

| Chave | Pública vai para | Privada fica em |
|---|---|---|
| A sua | O campo "SSH keys" da criação da VM (usuário `ubuntu`) | Só no seu computador |
| A de deploy | O `cloud-init` colado na Oracle (usuário `deploy`) | No seu computador e nos Secrets do GitHub |

### O que foi testado e o que não foi

No computador, simulei os passos 7 e 8: copiei `infra/edge` para outra pasta, criei o `.env` com
`DOMAIN=localhost` e subi a borda sem site atrás. As três respostas da conferência final bateram
(502, 308 e 301).

Os passos 1 a 6 não puderam ser testados: dependem da conta, do painel e do domínio. O roteiro foi
escrito a partir do funcionamento conhecido do painel da Oracle, e os nomes de menus podem ter
mudado. Cada passo termina com uma conferência justamente para o erro aparecer cedo.
