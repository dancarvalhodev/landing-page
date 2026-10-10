# CI/CD — testes e publicação automáticos

Arquivos explicados: `.github/workflows/ci.yml` e `.github/workflows/deploy.yml`. Uma seção por
subetapa da etapa 11. Os roteiros estão no `infra/README.md`: o primeiro deploy na Parte 2, a
proteção da `master` e o rollback na Parte 3. Para as
imagens, ver `producao.md`; para o servidor, ver `infra.md`.

## A ideia geral

São duas siglas que andam juntas:

- **CI** (integração contínua): a cada alteração enviada, uma máquina confere sozinha se o código
  continua bom.
- **CD** (entrega contínua): quando a alteração é aprovada, uma máquina publica sozinha.

Pense em uma **gráfica**. Antes de imprimir mil cópias, alguém confere a prova: ortografia,
margens, cores. Só com a prova aprovada a impressão roda. O CI é a conferência da prova; o CD é a
impressão. A vantagem de ser uma máquina é que ela confere toda vez, do mesmo jeito, e não esquece
nenhum item.

Quem faz esse trabalho aqui é o **GitHub Actions**, o serviço de automação do próprio GitHub.

## O vocabulário do GitHub Actions

| Termo | O que é | Na gráfica |
|---|---|---|
| Workflow | Um arquivo `.yml` em `.github/workflows/` que descreve uma automação | A ordem de serviço |
| Evento (`on`) | O que dispara o workflow: um push, um Pull Request | O cliente entregando o arquivo |
| Job | Um conjunto de passos que roda em uma máquina | Uma estação de trabalho |
| Step | Um passo do job: um comando ou uma action | Um item da lista de conferência |
| Runner | A máquina, emprestada pelo GitHub, que executa o job | O funcionário |
| Action | Um passo pronto, feito por terceiros, reaproveitável | Uma ferramenta emprestada |

O runner é uma máquina virtual nova a cada execução. Ela nasce vazia, baixa o repositório, faz o
trabalho e é destruída. Nada fica guardado de uma execução para a outra.

## 11a — `ci.yml`: estilo, análise e testes

### O que ele confere

As mesmas três coisas que você já roda à mão no contêiner:

| Passo | Comando | O que pega |
|---|---|---|
| Estilo de código | `composer cs` | Código fora do padrão de formatação (PER-CS) |
| Análise estática | `composer stan` | Erros que dá para achar sem rodar o código: tipo errado, método que não existe |
| Testes | `composer test` | Comportamento quebrado; inclui a leitura do conteúdo real em `content/` |

O terceiro tem um efeito prático: um artigo de blog sem `title`, `description` ou `date` derruba o
CI, e o erro aparece antes de publicar.

### O arquivo, bloco a bloco

**Quando roda:**

```yaml
on:
    pull_request:
    workflow_call:
```

| Evento | Quando acontece | Para quê |
|---|---|---|
| `pull_request` | Ao abrir um Pull Request e a cada novo commit enviado a ele | Conferir a alteração antes do merge |
| `workflow_call` | Quando outro workflow chama este | O `deploy.yml` roda estas mesmas conferências na `master`, antes de publicar (subetapa 11b) |

Um push em uma branch sem Pull Request aberto não dispara nada. É de propósito: se o CI rodasse em
todo push e também em todo Pull Request, cada commit de um Pull Request rodaria duas vezes.

Na subetapa 11a este arquivo também rodava a cada push na `master`. Isso saiu na 11b: quem roda
as conferências na `master` agora é o `deploy.yml`, chamando este arquivo. Se as duas coisas
ficassem, cada merge rodaria os testes duas vezes.

**Onde roda:**

```yaml
jobs:
    php:
        name: Estilo, análise e testes
        runs-on: ubuntu-24.04
        defaults:
            run:
                working-directory: www
```

- `runs-on`: o runner é um Ubuntu do GitHub.
- `working-directory: www`: os comandos rodam dentro de `www/`, onde fica o `composer.json`.
  Equivale a um `cd www` antes de cada um.

**Os passos:**

```yaml
steps:
    - uses: actions/checkout@v4

    - uses: shivammathur/setup-php@v2
      with:
          php-version: '8.5'
          coverage: none

    - name: Instalar dependências
      run: composer install --no-interaction --no-progress
```

| Passo | Para quê |
|---|---|
| `actions/checkout` | Baixa o repositório para o runner, que nasce vazio |
| `shivammathur/setup-php` | Instala o PHP 8.5 e o Composer. `coverage: none` deixa o Xdebug de fora, o que acelera |
| `composer install` | Instala as dependências, inclusive PHPUnit, PHPStan e PHP-CS-Fixer |

`uses` chama uma action pronta; `run` executa um comando. O `@v4` e o `@v2` fixam a versão
principal da action, para uma atualização dela não mudar o comportamento sem aviso.

Depois vêm os três comandos de conferência, um passo cada. Se um falha, o job para ali e fica
vermelho.

### Por que a action `shivammathur/setup-php`

O Ubuntu do runner traz um PHP pré-instalado, mas na versão que o GitHub escolheu (8.3 no
`ubuntu-24.04`). O projeto exige `^8.5`, então o `composer install` falharia. Essa action instala
a versão pedida, com as extensões comuns e o Composer, em segundos.

Ela não é do GitHub nem da equipe do PHP: é um projeto de código aberto, e é a action que o
ecossistema PHP usa no CI (Laravel, Symfony, Composer, PHPUnit). O custo é ser código de
terceiros, e o `@v2` é uma etiqueta que o autor pode mover. O risco aqui é pequeno, porque este
job não tem nenhum segredo: nem a chave de deploy, nem permissão de escrita no repositório.

As alternativas, caso um dia se queira trocar: fixar a action pelo hash de um commit, ou rodar o
job dentro da imagem oficial `php:8.5-cli`, instalando o Composer nela a cada execução. O dev
decidiu manter a `setup-php@v2` em 2026-10-09.

### Por que o CI não usa o Docker do projeto

No seu computador, os comandos rodam dentro do contêiner `php`. No CI, o PHP é instalado direto no
runner. Construir a imagem do projeto a cada execução levaria alguns minutos (ela compila
extensões); instalar o PHP pronto leva segundos. As conferências só precisam de PHP 8.5 e do
Composer, então o resultado é o mesmo.

A imagem de verdade é construída no `deploy.yml` (subetapa 11b), onde ela é o produto.

### O teste que só passava no seu computador

Ao simular o CI em uma cópia limpa do repositório, um teste falhou:
`AppTest::testPagesLoadTheStylesheetWithAVersion`. Ele conferia se o HTML trazia
`app.css?v=<número>`, e esse número vinha da data de modificação do arquivo `app.css`.

O `app.css` é gerado pelo Tailwind e não vai para o git. No seu computador ele existe, porque o
contêiner `tailwind` o cria; em uma cópia limpa, não. Sem o arquivo, o endereço saía sem `?v=` e o
teste falhava.

A correção: o teste agora cria o app com uma versão de build (`abc123`), como a produção faz desde
a subetapa 9c, e confere `app.css?v=abc123`. Ele deixou de depender de um arquivo gerado e passou
a testar o caminho que a produção usa de verdade.

A lição vale para qualquer teste novo: **o CI só enxerga o que está no git.** Um teste que depende
de `vendor/`, do `app.css`, do `.env` ou de qualquer arquivo ignorado passa no seu computador e
falha lá.

### Simular o CI no computador

Para saber se algo passa em uma cópia limpa, sem depender de arquivos ignorados:

```
git clone . /tmp/ci-clone
docker run --rm --user $(id -u):$(id -g) -e COMPOSER_HOME=/tmp/composer -v /tmp/ci-clone/www:/app landing-page-php composer install
docker run --rm --user $(id -u):$(id -g) -e COMPOSER_HOME=/tmp/composer -v /tmp/ci-clone/www:/app landing-page-php composer test
```

O `git clone .` copia só o que está commitado. Troque `composer test` por `composer cs` ou
`composer stan` para os outros dois. Ao terminar, apague `/tmp/ci-clone`.

Para conferir a sintaxe de um workflow sem enviar ao GitHub:

```
docker run --rm -v .:/repo:ro --workdir /repo rhysd/actionlint:latest
```

Sem nenhuma saída, o arquivo está correto.

### Como ver o CI funcionando

O CI só roda no GitHub, e só quando há um Pull Request (ou, pelo `deploy.yml`, no merge na
`master`).

1. Faça o commit do `ci.yml` e envie a branch: `git push`.
2. No GitHub, abra o repositório. Aparece uma faixa amarela, "feat/site-foundation had recent
   pushes"; clique em **Compare & pull request**. Se a faixa não aparecer: aba **Pull requests →
   New pull request**, com `base: master` e `compare: feat/site-foundation`.
3. Dê um título e clique em **Create pull request**. **Não clique em "Merge".** Abrir o Pull
   Request não publica nada; ele só propõe a mudança.
4. Na página do Pull Request, perto do fim, aparece a seção de verificações com "CI / Estilo,
   análise e testes". Amarelo é "rodando", verde é "passou", vermelho é "falhou".
5. Clique em **Details** para ver cada passo e a saída dos comandos.

A aba **Actions** do repositório lista todas as execuções, de todos os workflows.

A partir daí, cada `git push` nessa branch roda o CI de novo, sozinho, no mesmo Pull Request.

### O que acontece quando o CI falha

Nada é publicado e nada é desfeito: o Pull Request só fica marcado em vermelho. Você corrige no
seu computador, faz o commit e o push; o CI roda de novo. A subetapa 11c configura o GitHub para
impedir o merge enquanto estiver vermelho.

### Pegadinhas

- **A primeira execução é mais lenta.** O `composer install` baixa tudo; não há cache entre
  execuções neste workflow. Para um projeto deste tamanho, são segundos.
- **O workflow precisa estar na branch.** O GitHub usa o `ci.yml` da própria branch do Pull
  Request. Um Pull Request aberto antes de o arquivo existir só passa a rodar depois do push que o
  inclui.
- **Minutos gratuitos.** Em repositórios públicos, o GitHub Actions é gratuito. Em privados, há
  uma cota mensal; este workflow consome cerca de um minuto por execução.

## 11b — `deploy.yml`: a publicação

Arquivos: `.github/workflows/deploy.yml` e a Parte 2 do `infra/README.md` (passos 9 a 15), que é o
roteiro do primeiro deploy.

### O que acontece em um merge

Voltando à gráfica: a prova foi aprovada (o merge) e agora a impressão roda sozinha.

```
merge na master
      │
      ▼
┌──────────────┐   ┌──────────────────────┐   ┌────────────────┐
│ 1. testes    │──►│ 2. imagens           │──►│ 3. publicar    │
│ (o ci.yml)   │   │ constrói e envia     │   │ manda a VM     │
│              │   │ ao GHCR              │   │ atualizar      │
└──────────────┘   └──────────────────────┘   └────────────────┘
   runner x86          runner ARM                 runner x86
```

São três jobs em fila. Cada um só começa se o anterior passou (`needs`). Se os testes falham,
nenhuma imagem é construída; se a construção falha, a VM nem é tocada. O site que está no ar
continua no ar.

### O cabeçalho

```yaml
on:
    push:
        branches: [master]

concurrency:
    group: deploy
    cancel-in-progress: false

env:
    IMAGE_TAG: ${{ github.sha }}
```

| Trecho | Para quê |
|---|---|
| `on: push: branches: [master]` | Um merge na `master` é, para o GitHub, um push nela. É o gatilho |
| `concurrency` | Dois deploys nunca rodam ao mesmo tempo. Se houver dois merges seguidos, o segundo espera o primeiro acabar |
| `IMAGE_TAG: ${{ github.sha }}` | O identificador do commit vira a etiqueta das imagens e, pelo compose, o `?v=` dos arquivos estáticos (ver `producao.md`, 9c e 9d) |

`github.sha` é uma sequência de 40 letras e números que identifica o commit. Usá-la como etiqueta
faz cada versão publicada ter um nome único e rastreável: dá para olhar o `?v=` no código-fonte
do site e saber exatamente qual commit está no ar.

### Job 1 — testes

```yaml
testes:
    uses: ./.github/workflows/ci.yml
```

Chama o `ci.yml` inteiro, como se fosse uma função. É por isso que o `ci.yml` tem o
`workflow_call`. Assim as conferências ficam escritas em um lugar só e valem para os dois
momentos: no Pull Request e antes de publicar.

O CI já passou no Pull Request, então por que rodar de novo? Porque o que é publicado é a
`master` depois do merge, não a branch. Na maioria das vezes dá no mesmo, mas esta é a última
porta antes de o site mudar, e a conferência custa um minuto.

### Job 2 — imagens

```yaml
imagens:
    needs: testes
    runs-on: ubuntu-24.04-arm
    permissions:
        contents: read
        packages: write
```

- `runs-on: ubuntu-24.04-arm`: um runner com processador ARM, igual ao da VM. Uma imagem Docker
  é feita para uma arquitetura. Construir a imagem ARM em um runner x86 funcionaria por emulação,
  só que várias vezes mais devagar, porque a imagem do PHP compila extensões.
- `permissions`: o que o job pode fazer no GitHub. `contents: read` deixa baixar o código;
  `packages: write` deixa enviar imagens ao GHCR. Nada além disso.

Os passos:

| Passo | Comando | Para quê |
|---|---|---|
| Checkout | `actions/checkout` | Baixa o repositório |
| Entrar no GHCR | `docker login ghcr.io` | Identifica-se no registro de imagens |
| Construir | `docker compose -f docker-compose.prod.yml build` | Constrói as duas imagens, no estágio `prod` |
| Enviar | `docker compose -f docker-compose.prod.yml push` | Envia as duas ao GHCR |

O `build` e o `push` usam o mesmo `docker-compose.prod.yml` do servidor. O arquivo já diz como
construir cada imagem, que nome dar e que o `IMAGE_TAG` vira o `ASSET_VERSION`. O workflow não
repete nada disso.

**O `GITHUB_TOKEN`.** A senha usada no `docker login` não foi cadastrada por ninguém. O GitHub
cria um token novo a cada execução, válido só enquanto ela dura e só para este repositório, com
as permissões listadas em `permissions`. É por isso que enviar ao GHCR não exige nenhum secret.

### Job 3 — publicar

```yaml
publicar:
    needs: imagens
    runs-on: ubuntu-24.04
```

Este job não constrói nada, só dá ordens à VM por SSH. Por isso roda em um runner comum.

**Preparar o SSH:**

```yaml
run: |
    mkdir -p ~/.ssh
    echo "$SSH_KEY" > ~/.ssh/id_ed25519
    chmod 600 ~/.ssh/id_ed25519
    echo "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts
```

O runner nasce vazio, então a chave precisa ser colocada nele a cada execução.

| Linha | Para quê |
|---|---|
| `echo "$SSH_KEY" > ~/.ssh/id_ed25519` | Grava a chave privada de deploy com o nome padrão, que o `ssh` usa sem precisar de `-i` |
| `chmod 600` | Só o dono pode ler. O `ssh` se recusa a usar uma chave com permissão mais aberta |
| `echo "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts` | Grava a identidade da VM, para o `ssh` conferir com quem está falando |

**Enviar o compose e subir:**

```
scp docker-compose.prod.yml deploy@<IP>:/srv/landing-page/docker-compose.prod.yml
ssh deploy@<IP> "IMAGE_TAG=<sha> docker compose -p site-prod -f /srv/landing-page/docker-compose.prod.yml up -d --no-build --wait"
```

1. O `scp` copia o arquivo de compose para a VM. Ele vai a cada deploy, para a VM usar sempre a
   versão do commit publicado.
2. O `ssh` roda um único comando na VM:

| Parte | Para quê |
|---|---|
| `IMAGE_TAG=<sha>` | Diz qual versão das imagens subir |
| `-p site-prod` | O nome do projeto, que é também o apelido que a borda procura (ver `infra.md`) |
| `up -d` | Sobe os contêineres. Como a etiqueta é nova, o Docker baixa as imagens do GHCR antes |
| `--no-build` | Nunca construir na VM: lá só existe o arquivo de compose, sem o código |
| `--wait` | Só termina quando os contêineres estão rodando; se não subirem, o job fica vermelho |

O `APP_ENV` e o `SITE_URL` não aparecem no comando. O Compose lê o arquivo `.env` que está na
mesma pasta do arquivo de compose, `/srv/landing-page/.env`, criado à mão uma vez (passo 9 do
roteiro).

**Apagar imagens antigas:**

```
docker image prune --all --force --filter until=168h
```

Cada deploy deixa na VM as imagens da versão anterior, e o disco encheria com o tempo. Este
comando apaga as que não estão em uso e têm mais de 168 horas (uma semana). As versões recentes
ficam, para um rollback rápido; as antigas continuam guardadas no GHCR.

### Os três secrets

Secrets são valores guardados cifrados no GitHub. O workflow consegue usá-los, mas ninguém
consegue lê-los de volta, nem você; nos logs eles aparecem como `***`.

| Secret | O que é | Por que é segredo |
|---|---|---|
| `SSH_HOST` | O IP da VM | Não é sensível como uma senha, mas não há motivo para publicá-lo |
| `SSH_KEY` | A chave privada de deploy | Quem a tem entra na VM e controla o Docker |
| `SSH_KNOWN_HOSTS` | A identidade (chave pública) da VM | Não é segredo de verdade; fica ali para não ficar escrito no repositório |

No arquivo, os secrets entram por `env:` e são usados como `$SSH_HOST`, em vez de escritos direto
no comando. É o jeito recomendado: o valor chega ao comando como uma variável, e não como um
pedaço do texto do script.

### Por que conferir a identidade da VM (`known_hosts`)

Na primeira vez que você entrou na VM, o `ssh` perguntou algo como *"The authenticity of host ...
can't be established. Are you sure you want to continue?"*. Ao responder "yes", o seu computador
guardou a identidade da máquina. Nas vezes seguintes ele confere: se a identidade mudar, o `ssh`
recusa a conexão.

O runner nasce vazio e não tem ninguém para responder "yes". Há dois caminhos:

- **Aceitar qualquer identidade**, sempre. Funciona, mas se alguém conseguisse se colocar no meio
  do caminho, o runner entregaria os comandos de deploy a uma máquina falsa.
- **Entregar a identidade certa ao runner**, que é o que o `SSH_KNOWN_HOSTS` faz.

É como combinar uma senha com o porteiro antes de mandar um entregador: ele só entrega se ouvir a
senha certa. O custo é um comando a mais na configuração (`ssh-keyscan`), e ter de refazê-lo se a
VM for recriada.

### Por que o primeiro deploy pode falhar no job 3

No GHCR, toda imagem nasce **privada**, mesmo em repositório público. A VM não tem senha para
baixar imagem privada, e o plano é ela não precisar de nenhuma. Então o primeiro `up` na VM pode
ser recusado com `denied` ou `unauthorized`.

A correção é manual e única: tornar as duas imagens públicas no painel do GitHub e rodar de novo
só o job que falhou (**Re-run failed jobs**). As imagens já estão no GHCR; nada é reconstruído. O
passo 14 do roteiro tem os cliques.

Ter três jobs separados é o que permite isso: o GitHub repete só o que falhou.

### O que não está no workflow, de propósito

- **Cache de build.** Cada deploy constrói as imagens do zero, o que leva alguns minutos por causa
  das extensões do PHP. Para um site que publica algumas vezes por mês, a simplicidade vale mais.
  Se incomodar, o caminho é o cache do `docker buildx`.
- **Login da VM no GHCR.** As imagens são públicas.
- **Rollback automático.** Se o site subir com defeito, voltar atrás é manual (subetapa 11c).
- **O Caddy de borda.** Ele não é tocado pelo deploy; é o que mantém o HTTPS de pé enquanto os
  contêineres do site são trocados.

### O que acontece com o site durante o deploy

O `up -d` troca os contêineres antigos pelos novos. Por um ou dois segundos não há site atrás da
borda, e quem acessar nesse instante vê um 502. Para um site pessoal é aceitável; evitar isso
exigiria subir a versão nova ao lado da antiga, o que não cabe neste projeto.

### O que foi testado e o que não foi

No computador:

- a sintaxe dos dois workflows, com o `actionlint`;
- o comando de build do job 2, com uma etiqueta de teste;
- o comando do job 3, em uma pasta que simula `/srv/landing-page`, só com o arquivo de compose e
  o `.env`: os contêineres subiram sem build, o `SITE_URL` veio do `.env` e o `?v=` saiu com a
  etiqueta. Isso confirma que a VM não precisa do código.

Só dava para confirmar no GitHub, no primeiro merge: o runner ARM, o envio das imagens ao GHCR
com o `GITHUB_TOKEN` e a conexão SSH com os três secrets. O primeiro deploy foi feito pelo dev em
2026-10-09 e o site está no ar.

## 11c — Proteção da `master` e rollback

Não há arquivo novo nesta subetapa. A proteção é uma configuração feita no painel do GitHub, e o
rollback é um procedimento. Os dois estão na Parte 3 do `infra/README.md` (passos 16 e 17); aqui
fica o porquê.

### Por que proteger a `master`

Desde a subetapa 11b, a `master` é o botão de publicar: tudo o que chega nela vai para o ar em
minutos. Sem proteção, um `git push` na branch errada publica código que nenhum teste viu.

Pense na **porta de uma sala limpa**: ninguém entra direto do corredor; todo mundo passa pela
antecâmara. A proteção obriga toda alteração a passar pela antecâmara, que é o Pull Request com o
CI verde.

| Regra | O que impede |
|---|---|
| Exigir Pull Request | Um `git push` direto na `master`, inclusive por engano |
| Exigir o CI verde | O merge de um Pull Request com teste falhando, código fora do padrão ou conteúdo incompleto |
| Valer também para administradores | Você mesmo pulando as duas regras acima |

### Por que zero aprovações

A regra "exigir Pull Request" tem um número de aprovações necessárias. O GitHub não deixa uma
pessoa aprovar o próprio Pull Request, então, trabalhando sozinho, qualquer valor acima de zero
trancaria a `master` para sempre. Com zero, o Pull Request continua obrigatório, e quem "aprova"
é o CI.

### O nome da verificação obrigatória

A regra do CI pede o nome da verificação. É o `name` do job no `ci.yml`: **Estilo, análise e
testes**. Se esse nome mudar no arquivo, a regra passa a esperar uma verificação que não existe
mais, e todo Pull Request fica travado em "aguardando". Ao renomear o job, atualize a regra junto.

O GitHub só oferece na busca as verificações que já rodaram pelo menos uma vez no repositório.

### Rollback: dois caminhos que se completam

**A. Pelo servidor.** O mesmo comando do deploy, com a etiqueta de uma versão anterior:

```
IMAGE_TAG=<identificador> docker compose -p site-prod -f /srv/landing-page/docker-compose.prod.yml up -d --no-build --wait
```

Funciona porque cada versão publicada é uma imagem com nome próprio, guardada no GHCR (ver
`producao.md`, "Por que `ghcr.io` se o site roda na Oracle"). Voltar atrás é só apontar para
outra imagem; nada é reconstruído. E como o `?v=` dos arquivos estáticos está gravado dentro da
imagem (subetapa 9c), o CSS antigo volta junto com o rótulo antigo.

**B. Pelo GitHub.** O botão **Revert** de um Pull Request já mesclado cria um Pull Request novo
que desfaz aquelas alterações. O merge dele dispara um deploy normal.

Por que não basta o A: ele muda o que está no ar, mas não a `master`. O código com defeito
continua lá, e o próximo merge, mesmo de algo sem relação, publicaria tudo de novo. O B é o que
deixa a `master` igual ao que está no ar. O A existe para os minutos em que o site está quebrado
e dez minutos de espera são demais.

| | A. Pelo servidor | B. Revert no GitHub |
|---|---|---|
| Tempo | Segundos | Cerca de dez minutos |
| Muda a `master`? | Não | Sim |
| Quando usar | Site fora do ar ou muito quebrado | Sempre, sozinho ou depois do A |

### De onde vem o identificador da versão

É o `github.sha` do commit que foi publicado, o mesmo valor em quatro lugares: no topo da
execução do Deploy na aba Actions, na etiqueta das duas imagens no GHCR, no `?v=` do código-fonte
do site e no histórico de commits da `master`.

Na VM, `docker images ghcr.io/dancarvalhodev/landing-page-php` lista as versões que ainda estão
no disco (as da última semana); as mais antigas são baixadas do GHCR quando pedidas.

### O que o rollback não desfaz

- **O arquivo de compose na VM** continua sendo o do último deploy. Só importa se a versão com
  defeito mudou o próprio `docker-compose.prod.yml`; nesse caso, use o caminho B.
- **O `.env` da VM e o Caddy de borda** não fazem parte do deploy, então também não voltam.

### O fluxo de trabalho depois da proteção

Com a `master` protegida, toda alteração segue o mesmo caminho, inclusive documentação:

1. `git switch master` e `git pull`.
2. `git switch -c <nome-da-branch>`.
3. Alterar, conferir em `localhost:8080`, commit e `git push`.
4. Abrir o Pull Request, esperar o CI ficar verde e fazer o merge.

Uma branch já mesclada não é reaproveitada: a próxima alteração começa de uma branch nova, criada
a partir da `master` atualizada.

### O que foi testado e o que não foi

No computador, simulei o caminho A: subi uma etiqueta, depois subi a anterior com o mesmo
comando. Os dois contêineres foram recriados com a imagem antiga e o `ASSET_VERSION` voltou junto.

A proteção da `master` e o botão Revert são do painel do GitHub e não puderam ser testados daqui.
O roteiro traz uma conferência para a proteção: um push direto na `master` precisa ser recusado.
