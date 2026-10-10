# Infra — passo a passo para colocar o servidor no ar

Este roteiro tem três partes. A primeira (passos 1 a 8) cria a máquina na Oracle Cloud, aponta o
domínio para ela e sobe o Caddy de borda; ao final, `https://<domínio>` responde com um
certificado válido. A segunda (passos 9 a 15) prepara o GitHub e faz o primeiro deploy, que
coloca o site no ar. A terceira (passos 16 e 17) protege a `master` e ensina a voltar para uma
versão anterior.

O porquê de cada peça está em `.claude/arch-explain/infra.md` e `.claude/arch-explain/ci-cd.md`.
Aqui fica só o que fazer, na ordem.

> Os nomes de menus e botões são os do painel da Oracle em inglês e podem mudar com o tempo. Se
> algum não bater, procure pelo conceito (por exemplo, "regra de entrada" em vez de "Ingress
> Rule").

## Parte 1 — O servidor

| # | Passo | Onde | Tempo |
|---|---|---|---|
| 1 | Criar a conta na Oracle Cloud | Navegador | 15 min, mais a aprovação |
| 2 | Gerar as duas chaves SSH | Seu computador | 2 min |
| 3 | Criar a VM, com o cloud-init | Painel da Oracle | 10 min |
| 4 | Abrir as portas 80 e 443 | Painel da Oracle | 5 min |
| 5 | Conferir a VM | SSH | 5 min |
| 6 | Apontar o domínio | Painel do registrador do domínio | 5 min, mais a propagação |
| 7 | Subir o Caddy de borda | SSH | 5 min |
| 8 | Conferência final | Seu computador | 2 min |

Você vai precisar de: um cartão de crédito (a Oracle exige para validar a conta, mesmo no plano
gratuito), o acesso ao painel onde o domínio foi registrado e este repositório no computador.

## 1. Criar a conta na Oracle Cloud

1. Acesse `https://www.oracle.com/cloud/free/` e crie a conta.
2. Na escolha da **Home Region**, pare e pense: **ela não pode ser trocada depois**, e os recursos
   gratuitos só existem nela. Prefira uma região perto dos seus visitantes (para o Brasil,
   `Brazil East (Sao Paulo)` ou `Brazil Southeast (Vinhedo)`).
3. Conclua a validação do cartão e espere o e-mail de conta ativa.

**Sobre o plano gratuito:**

- A máquina usada aqui é do tipo **Ampere A1 (ARM)**, incluída no "Always Free".
- Pode faltar máquina ARM disponível na região no momento da criação (passo 3). É um problema
  conhecido; ver "Se aparecer Out of capacity", no passo 3.
- A Oracle pode recolher máquinas gratuitas que ficam ociosas por muito tempo.

### Converter para Pay As You Go (opcional, mas recomendado)

Converter a conta resolve os dois últimos problemas acima: contas pagas têm prioridade na
capacidade de máquinas ARM e não têm a VM recolhida por ociosidade. O caminho é **Billing & Cost
Management → Upgrade and Manage Payment**; a conversão pode levar algumas horas.

**Você continua sem pagar se o uso ficar dentro do "Always Free".** No Pay As You Go só é cobrado
o que passar dos limites gratuitos. A diferença para a conta gratuita é que deixa de existir uma
trava: quem passa do limite é cobrado, em vez de ter o recurso bloqueado.

**A pré-autorização no cartão.** Ao converter, a Oracle reserva um valor no cartão para validá-lo
(historicamente em torno de 100 dólares). Não é uma compra nem gera reembolso: é um bloqueio
temporário no limite, que a Oracle libera sozinha. O valor volta a aparecer como disponível em
poucos dias a cerca de uma semana, conforme o banco. A tela de conversão mostra o valor antes de
você confirmar; se não liberar em duas semanas, fale primeiro com o banco.

**Os limites gratuitos que importam aqui:**

| Recurso | Limite gratuito por mês | O que este site usa |
|---|---|---|
| Máquina ARM (Ampere A1) | 4 OCPUs e 24 GB de memória, somando todas as instâncias | 1 OCPU e 6 GB |
| Disco (boot volume e block volume) | 200 GB no total | cerca de 47 GB, o padrão |
| Tráfego de saída | 10 TB | uma fração mínima |
| IP público | o da instância não é cobrado | 1 |

**Onde dá para ser cobrado sem perceber:**

- criar uma segunda instância e a soma passar de 4 OCPUs ou 24 GB;
- aumentar o disco, ou acumular discos de instâncias apagadas (ao apagar uma instância, marque a
  opção de apagar o boot volume junto);
- escolher um shape sem a etiqueta **Always Free-eligible**;
- criar outros serviços além das cotas gratuitas.

**Duas proteções, logo depois de converter:**

1. Um orçamento com alerta, em **Billing & Cost Management → Budgets**: valor de 1 dólar, com
   aviso por e-mail. Ele não impede a cobrança, mas avisa no primeiro centavo.
2. Olhar o **Cost Analysis** na primeira semana, para confirmar que tudo aparece como zero.

> Os valores e limites acima podem mudar. Antes de converter, confira `oracle.com/cloud/free` e a
> lista de recursos "Always Free" no painel; o que vale é o que estiver lá.

## 2. Gerar as duas chaves SSH

São duas chaves, uma para cada usuário da VM:

| Chave | Usuário na VM | Quem usa |
|---|---|---|
| A sua | `ubuntu` | Você, para administrar a máquina |
| A de deploy | `deploy` | O GitHub Actions, para publicar o site |

Se você já tem uma chave pessoal em `~/.ssh/id_ed25519.pub`, use essa como "a sua". Para criar a
de deploy, no seu computador:

```
ssh-keygen -t ed25519 -f ~/.ssh/landing-deploy -C "deploy landing-page" -N ""
```

Isso gera dois arquivos:

- `~/.ssh/landing-deploy`: a chave **privada**. Não sai do seu computador, a não ser para os
  Secrets do GitHub, na etapa de CI/CD.
- `~/.ssh/landing-deploy.pub`: a chave **pública**. É esta que vai para a VM.

Deixe as duas chaves públicas à mão para o próximo passo:

```
cat ~/.ssh/id_ed25519.pub
cat ~/.ssh/landing-deploy.pub
```

## 3. Criar a VM

No painel da Oracle: menu **Compute → Instances → Create instance**.

**Nome:** qualquer um, por exemplo `landing-page`.

**Image and shape** (botão **Edit**):

- **Image:** `Canonical Ubuntu`, a versão LTS mais recente (24.04). O `cloud-init.yaml` deste
  repositório foi escrito para Ubuntu; com outra imagem ele não serve como está.
- **Shape:** em **Ampere**, escolha `VM.Standard.A1.Flex`.
- **OCPUs e memória:** 1 OCPU e 6 GB bastam com folga para este site. O limite gratuito é maior;
  deixar sobra permite criar outra máquina depois.

**Networking:**

A máquina precisa estar em uma **sub-rede pública** e receber um IP público. Crie a rede antes,
pelo assistente, que monta tudo o que uma sub-rede pública precisa (a sub-rede, a saída para a
internet e a rota):

1. Em outra aba: **Networking → Virtual cloud networks → Start VCN Wizard**.
2. Escolha **Create VCN with Internet Connectivity**, dê um nome (por exemplo `landing-page`),
   mantenha os valores padrão e conclua.
3. De volta à criação da instância, escolha **Select existing virtual cloud network**, a VCN
   criada, e **Select existing subnet**, a que tem "public" no nome.
4. Marque **Assign a public IPv4 address**. Sem isso a máquina não é alcançada de fora.

Se a opção do IP público estiver bloqueada, com o aviso *"You must select a public subnet to
assign a public IPv4 address"*, a sub-rede escolhida (ou a que o formulário está criando) é
privada. Troque pela pública antes de continuar.

**Não crie a instância para "arrumar a rede depois".** A sub-rede de uma instância não pode ser
trocada, e sem internet o cloud-init falha na única vez em que roda: a máquina nasceria sem
Docker e sem acesso por SSH.

**Add SSH keys:**

- Escolha **Paste public keys** e cole **a sua** chave pública (a do usuário `ubuntu`).

**Boot volume:** deixe o padrão.

**O cloud-init** (o campo fica escondido):

1. Abra **Show advanced options**, aba **Management**.
2. Em **Initialization script**, escolha **Paste cloud-init script**.
3. Abra `infra/cloud-init.yaml` no seu computador, copie o conteúdo inteiro e cole no campo.
4. **No campo colado**, troque a linha `TROQUE_PELA_CHAVE_PUBLICA_DE_DEPLOY` pelo conteúdo de
   `~/.ssh/landing-deploy.pub`. A linha fica assim (mantendo os espaços e o traço do começo):

   ```yaml
         - ssh-ed25519 AAAA...resto-da-chave deploy landing-page
   ```

   Não altere o arquivo do repositório; a troca é só no que você colou.

Antes de clicar em **Create**, salve o texto do cloud-init já com a chave trocada em um arquivo
fora do repositório. Se a criação falhar, é só colar de novo.

Clique em **Create**. Quando o estado virar **Running**, anote o **Public IP address** da
instância. Ele é usado em todos os passos seguintes; abaixo ele aparece como `<IP>`.

### Se aparecer "Out of capacity"

A mensagem *"Out of capacity for shape VM.Standard.A1.Flex"* não é erro de configuração: a Oracle
está sem máquinas ARM gratuitas livres naquela região naquele momento. O que tentar, em ordem:

1. **Tirar o fault domain**, se você escolheu um; deixe a Oracle escolher.
2. **Outro availability domain.** Só adianta em regiões com mais de um; as brasileiras têm um só.
3. **Tentar de novo em outros horários.** A capacidade aparece quando outras pessoas apagam
   máquinas. Pode levar de minutos a dias. A VCN já criada continua lá; é só selecioná-la.
4. **Converter a conta para Pay As You Go** (passo 1). É o que mais resolve na prática.
5. **Usar a máquina AMD gratuita**, `VM.Standard.E2.1.Micro`, como último recurso. Ela quase
   sempre está disponível, mas tem 1 GB de memória. Atenção: ela é x86, não ARM, e as imagens do
   site precisam ser construídas para a mesma arquitetura da VM. Se for por aqui, o workflow de
   deploy precisa ser ajustado para `amd64`.

**Atenção:** o cloud-init só roda nesta primeira inicialização. Se você esquecer de colar o
arquivo ou de trocar a chave, o caminho mais simples é apagar a instância (**Terminate**) e criar
de novo.

## 4. Abrir as portas 80 e 443 na Security List

A Oracle tem dois firewalls. O de dentro da VM foi aberto pelo cloud-init; este é o de fora.

1. Na página da instância, clique no nome da **Subnet** (em **Primary VNIC**).
2. Em **Security Lists**, abra a **Default Security List**.
3. Clique em **Add Ingress Rules** e crie três regras. Em todas: **Source Type** `CIDR` e
   **Source CIDR** `0.0.0.0/0` (qualquer origem).

| IP Protocol | Destination Port Range | Para quê |
|---|---|---|
| TCP | `80` | HTTP: redirecionamento para HTTPS e emissão do certificado |
| TCP | `443` | HTTPS |
| UDP | `443` | HTTP/3 |

Deixe **Source Port Range** em branco. A regra da porta 22 (SSH) já existe; não a apague.

## 5. Conferir a VM

O cloud-init leva alguns minutos depois de a instância ficar **Running**. Entre como `ubuntu`:

```
ssh ubuntu@<IP>
```

Espere o cloud-init terminar e veja o resultado:

```
cloud-init status --wait --long
```

O esperado é `status: done`, sem avisos. Dois resultados ruins:

- `status: error`: um comando falhou; o registro completo está em
  `/var/log/cloud-init-output.log`.
- `status: done` com `extended_status: degraded done`: o arquivo não foi lido. Ver "Se o
  cloud-init não foi aplicado", logo abaixo. **Não confie só no `status: done`.**

Confira as quatro coisas que ele deveria ter feito:

| Comando | Resultado esperado |
|---|---|
| `docker --version` | Mostra a versão do Docker |
| `docker compose version` | Mostra a versão do Compose |
| `docker network ls` | A lista tem a rede `edge` |
| `id deploy` | Os grupos incluem `docker` |
| `sudo iptables -L INPUT -n --line-numbers` | As primeiras linhas liberam as portas 80 e 443 |
| `ls -ld /srv/landing-page` | A pasta existe e é do usuário `deploy` |

### Se o cloud-init não foi aplicado

Se o `cloud-init status` mostrar `degraded done` com avisos como *"Failed loading yaml blob"* ou
*"empty cloud config"*, o arquivo colado não pôde ser lido e **nada dele foi executado**: não há
Docker, nem usuário `deploy`, nem portas abertas. A causa conhecida é um caractere fora do ASCII
(um acento, por exemplo) no texto colado, que o painel da Oracle corrompe.

Não é preciso apagar a instância. Faça à mão, como `ubuntu`, o que o cloud-init faria. Um comando
por vez, na ordem:

```
sudo apt-get update
sudo apt-get -y upgrade
sudo groupadd docker
sudo useradd --create-home --shell /bin/bash --groups docker deploy
sudo passwd --lock deploy
sudo mkdir /home/deploy/.ssh
sudo nano /home/deploy/.ssh/authorized_keys
```

No `nano`, cole a chave pública de deploy (o conteúdo de `~/.ssh/landing-deploy.pub`, do seu
computador) em uma linha só, salve e saia. Depois:

```
sudo chmod 700 /home/deploy/.ssh
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo iptables -I INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT
sudo iptables -I INPUT -p udp --dport 443 -j ACCEPT
sudo netfilter-persistent save
curl -fsSL https://get.docker.com | sudo sh
sudo docker network create edge
sudo mkdir -p /srv/landing-page
sudo chown deploy:deploy /srv/landing-page
```

Se o `groupadd` disser que o grupo já existe, siga em frente. Ao terminar, refaça a tabela de
conferências acima.

Saia (`exit`) e teste a entrada do usuário de deploy, com a chave dele:

```
ssh -i ~/.ssh/landing-deploy deploy@<IP> docker ps
```

Deve mostrar uma lista vazia de contêineres, sem pedir senha e sem erro de permissão.

## 6. Apontar o domínio para a VM

No painel do registrador do domínio (onde ele foi comprado), na área de DNS, crie dois registros:

| Tipo | Nome | Valor | Para quê |
|---|---|---|---|
| A | `@` | `<IP>` | `meusite.com` |
| A | `www` | `<IP>` | `www.meusite.com`, que a borda redireciona para o domínio sem `www` |

Alguns painéis usam o próprio domínio no lugar de `@`. O TTL pode ficar no padrão.

A mudança leva de alguns minutos a algumas horas para se espalhar. Confira do seu computador:

```
dig +short meusite.com
dig +short www.meusite.com
```

Os dois precisam responder `<IP>`. **Só siga para o passo 7 quando responderem.** O Caddy pede o
certificado assim que sobe; se o domínio ainda não aponta para a VM, o pedido falha, e muitas
falhas seguidas fazem a Let's Encrypt bloquear novas tentativas por um tempo.

**Sobre o IP:** o endereço público padrão é "efêmero": continua o mesmo enquanto a instância
existir, mas muda se ela for apagada e recriada. Se isso acontecer, é só atualizar os dois
registros. Quem quiser um IP que sobreviva à recriação pode reservar um em **Networking → IP
Management → Reserved Public IPs**.

## 7. Subir o Caddy de borda

Do seu computador, na raiz do repositório, copie a pasta da borda para a VM:

```
scp -i ~/.ssh/landing-deploy -r infra/edge deploy@<IP>:/srv/landing-page/
```

Entre na VM como `deploy`:

```
ssh -i ~/.ssh/landing-deploy deploy@<IP>
```

Crie o arquivo com o domínio (troque pelo seu, sem `https://` e sem `www`):

```
cp /srv/landing-page/edge/env-example /srv/landing-page/edge/.env
nano /srv/landing-page/edge/.env
```

Suba a borda:

```
docker compose -p edge -f /srv/landing-page/edge/docker-compose.yml up -d
```

Acompanhe a emissão do certificado:

```
docker logs -f edge-caddy-1
```

Procure por linhas com `certificate obtained successfully`, uma para o domínio e outra para o
`www`. `Ctrl+C` sai do acompanhamento sem derrubar o contêiner.

## 8. Conferência final

Do seu computador:

| Comando | Resultado esperado |
|---|---|
| `curl -I https://meusite.com` | `HTTP/2 502` |
| `curl -I http://meusite.com` | `308`, com `location: https://meusite.com/` |
| `curl -I https://www.meusite.com` | `301`, com `location: https://meusite.com/` |

**O 502 é o resultado certo neste ponto.** Ele quer dizer que a borda está no ar, com certificado
válido, e que ainda não existe um site atrás dela. O site sobe com o primeiro deploy. Se o `curl`
reclamar do certificado, ou não conectar, algo nos passos anteriores falhou.

## Parte 2 — O primeiro deploy

Com a borda respondendo 502, falta colocar o site atrás dela. Quem faz isso é o GitHub Actions,
no merge na `master`. Esta parte prepara o que ele precisa e faz o primeiro merge. O porquê de
cada peça está em `.claude/arch-explain/ci-cd.md`.

| # | Passo | Onde |
|---|---|---|
| 9 | Criar o `.env` do site | VM, por SSH |
| 10 | Copiar a identidade da VM | Seu computador |
| 11 | Cadastrar os três secrets | GitHub |
| 12 | Tornar o repositório público | GitHub |
| 13 | Abrir o Pull Request e fazer o merge | GitHub |
| 14 | Acompanhar o deploy e tornar as imagens públicas | GitHub |
| 15 | Conferir o site no ar | Seu computador |

### 9. Criar o `.env` do site na VM

O domínio e o ambiente do site ficam em um arquivo na VM, fora do repositório. Entre como
`deploy`:

```
ssh -i ~/.ssh/landing-deploy deploy@<IP>
nano /srv/landing-page/.env
```

Conteúdo, com o seu domínio (com `https://`, sem `www` e sem barra no fim):

```
APP_ENV=production
SITE_URL=https://meusite.com
```

Confira o `SITE_URL` com cuidado: é ele que o site usa para dizer ao Google qual é o endereço
oficial.

### 10. Copiar a identidade da VM

O GitHub precisa ter certeza de que está falando com a sua VM, e não com alguém se passando por
ela. Para isso ele guarda a "impressão digital" da máquina. No seu computador:

```
ssh-keyscan -t ed25519 <IP>
```

A saída tem uma linha que começa com o IP, seguida de `ssh-ed25519 AAAA...`. Copie essa linha
inteira (a que começa com `#` é só um comentário e não entra).

### 11. Cadastrar os três secrets no GitHub

No repositório: **Settings → Secrets and variables → Actions → New repository secret**. Crie
três, com estes nomes exatos:

| Nome | Valor | De onde vem |
|---|---|---|
| `SSH_HOST` | O IP público da VM | Página da instância na Oracle |
| `SSH_KEY` | O conteúdo inteiro da chave **privada** de deploy | `cat ~/.ssh/landing-deploy` |
| `SSH_KNOWN_HOSTS` | A linha copiada no passo 10 | `ssh-keyscan` |

Cuidados com o `SSH_KEY`:

- É o arquivo **sem** `.pub`.
- Copie tudo, da linha `-----BEGIN OPENSSH PRIVATE KEY-----` até
  `-----END OPENSSH PRIVATE KEY-----`, inclusive.
- Depois de salvo, o GitHub não mostra mais o valor. Para trocar, é preciso cadastrar de novo.

### 12. Tornar o repositório público

**Settings → General**, no fim da página, em **Danger Zone → Change repository visibility →
Change to public**.

Faça isso antes do merge, por dois motivos: o runner ARM usado para construir as imagens é
gratuito em repositórios públicos, e o cartão do projeto "Landing Page" no site aponta para o
repositório.

### 13. Abrir o Pull Request e fazer o merge

1. No seu computador, faça o commit e o `git push` da branch de trabalho.
2. No GitHub, abra o Pull Request para a `master`, se ainda não estiver aberto: **Pull requests →
   New pull request**, com `base: master` e `compare:` a sua branch, e **Create pull
   request**.
3. Espere a verificação "CI / Estilo, análise e testes" ficar verde.
4. Clique em **Merge pull request** e depois em **Confirm merge**.

O merge é o gatilho: a partir dele, o GitHub publica sozinho.

### 14. Acompanhar o deploy

Na aba **Actions**, abra a execução do workflow **Deploy**. Ela tem três partes, em sequência:

| Job | O que faz | Tempo aproximado |
|---|---|---|
| Estilo, análise e testes | As mesmas conferências do CI, agora na `master` | 1 min |
| Construir e enviar as imagens | Constrói as duas imagens e envia ao GHCR | 5 a 10 min |
| Publicar na VM | Manda a VM baixar as imagens e subir o site | 1 min |

**No primeiro deploy, o terceiro job pode falhar**, com uma mensagem como `denied`,
`unauthorized` ou `pull access denied`. Não é um erro seu: no GHCR, toda imagem nasce privada,
mesmo em repositório público, e a VM não tem senha para baixar imagem privada. As imagens já
foram enviadas; falta só abri-las:

1. No GitHub, clique na sua foto → **Your profile** → aba **Packages**.
2. Abra `landing-page-php` → **Package settings** → no fim da página, **Change visibility →
   Public**.
3. Repita para `landing-page-caddy`.
4. Volte à execução que falhou, em **Actions**, e clique em **Re-run failed jobs**. Só o terceiro
   job roda de novo.

Isso é feito uma vez só. As próximas versões das mesmas imagens já nascem públicas.

### 15. Conferir o site no ar

Do seu computador:

| Comando | Resultado esperado |
|---|---|
| `curl -I https://meusite.com` | `HTTP/2 200` |
| `curl -I https://meusite.com/en` | `HTTP/2 200` |
| `curl -I https://meusite.com/sitemap.xml` | `HTTP/2 200` |

Abra o site no navegador e veja o código-fonte da página: o `app.css` deve aparecer com `?v=`
seguido de uma sequência longa de letras e números. É o identificador do commit publicado, o
mesmo que aparece na execução do Deploy.

Vale conferir também o que só dá para ver com o site no ar:

- o tema dos comentários (Giscus) em um artigo do blog, no claro e no escuro;
- a prévia do link no LinkedIn Post Inspector;
- o JSON-LD `Person` no Rich Results Test, do Google.

### Daqui em diante

Publicar passa a ser:

```
git switch master
git pull
git switch -c <nome-da-branch>
```

Depois: alterar, conferir em `localhost:8080`, fazer o commit e o `git push`, abrir o Pull
Request, esperar o CI ficar verde e fazer o merge. O deploy acontece sozinho, alguns minutos
depois.

Cada alteração começa de uma branch nova, criada a partir da `master` atualizada. Uma branch já
mesclada não deve ser reaproveitada.

## Parte 3 — Proteger a `master` e voltar atrás

### 16. Proteger a `master`

Desde o primeiro deploy, tudo o que chega à `master` vai para o ar. A proteção faz o GitHub
recusar qualquer alteração na `master` que não venha por um Pull Request com o CI verde, mesmo
que seja você, por engano, com um `git push` direto.

No repositório: **Settings → Branches → Add classic branch protection rule** (em algumas contas o
botão é **Add branch ruleset**; as opções têm os mesmos nomes).

| Opção | Valor | Para quê |
|---|---|---|
| **Branch name pattern** | `master` | A branch protegida |
| **Require a pull request before merging** | Marcada | Ninguém envia direto para a `master` |
| ↳ **Required approvals** | `0` | Você trabalha sozinho, e o GitHub não deixa aprovar o próprio Pull Request. Com 1, nenhum merge seria possível |
| **Require status checks to pass before merging** | Marcada | O merge só libera com o CI verde |
| ↳ caixa de busca de status checks | Procure por `Estilo` e selecione **Estilo, análise e testes** | Diz qual verificação é obrigatória |
| **Do not allow bypassing the above settings** | Marcada | As regras valem também para você, que é administrador |

Clique em **Create** (ou **Save changes**).

Se a busca por `Estilo` não encontrar nada, é porque o CI ainda não rodou nenhuma vez em um Pull
Request deste repositório. Abra um Pull Request qualquer, espere o CI rodar e volte.

**Para conferir que a proteção vale**, no seu computador, na `master` atualizada:

```
git commit --allow-empty -m "teste de protecao"
git push
```

O push deve ser recusado, com uma mensagem como `protected branch hook declined`. Desfaça o
commit de teste com `git reset HEAD~1`, que remove só ele e não mexe nos seus arquivos.

### 17. Voltar para uma versão anterior (rollback)

Use quando um deploy colocou no ar algo quebrado. Há dois caminhos, e eles se completam: o
primeiro apaga o incêndio em segundos, o segundo conserta de verdade.

**A. Pelo servidor, em segundos**

1. Descubra o identificador da versão boa. Na aba **Actions** do GitHub, abra a execução do
   **Deploy** anterior à que quebrou; o identificador do commit aparece no topo. Copie os 40
   caracteres (o botão de copiar ao lado dele faz isso).
2. Entre na VM e suba essa versão:

   ```
   ssh -i ~/.ssh/landing-deploy deploy@<IP>
   IMAGE_TAG=<identificador> docker compose -p site-prod -f /srv/landing-page/docker-compose.prod.yml up -d --no-build --wait
   ```

O site volta em alguns segundos. Se a versão tiver sido publicada há menos de uma semana, a
imagem ainda está na VM; se não, o Docker a baixa de novo do GHCR, o que leva um pouco mais.

**Atenção:** este caminho não muda a `master`. O código quebrado continua lá, e o próximo merge
publicaria tudo de novo, com o defeito. Por isso o caminho B vem em seguida.

**B. Pelo GitHub, desfazendo o merge**

1. No GitHub, abra o Pull Request que causou o problema (aba **Pull requests → Closed**).
2. Perto do fim da página, clique em **Revert**. O GitHub cria um Pull Request novo que desfaz
   aquelas alterações.
3. Espere o CI ficar verde e faça o merge. O deploy roda sozinho e publica a versão sem o defeito.

Este caminho leva o tempo de um deploy normal (cerca de dez minutos), mas deixa a `master` igual
ao que está no ar. Depois, com calma, você corrige o problema em uma branch nova.

| | A. Pelo servidor | B. Revert no GitHub |
|---|---|---|
| Tempo | Segundos | Cerca de dez minutos |
| Muda a `master`? | Não | Sim |
| Precisa de SSH? | Sim | Não |
| Quando usar | O site está fora do ar ou muito quebrado | Sempre, sozinho ou depois do A |

Se o problema for pequeno (um erro de digitação, um link errado), nem precisa de rollback:
corrija em uma branch nova e publique pelo caminho normal.

## Se algo não funcionar

| Sintoma | Causa provável | Onde olhar |
|---|---|---|
| A opção do IP público está bloqueada ao criar a VM | A sub-rede escolhida é privada | Passo 3, "Networking" |
| "Out of capacity" ao criar a VM | Falta de máquinas ARM gratuitas na região | Passo 3, "Se aparecer Out of capacity" |
| `ssh` não conecta | IP errado, a chave colada no passo 3 não é a sua, ou a máquina está sem IP público | Página da instância; refazer o passo 3 |
| `ssh deploy@...` pede senha ou recusa | A chave de deploy não foi trocada no cloud-init | `sudo cat /home/deploy/.ssh/authorized_keys`, como `ubuntu` |
| `cloud-init status` mostra `error` | Um comando do arquivo falhou | `/var/log/cloud-init-output.log` |
| `cloud-init status` mostra `degraded done` e "Failed loading yaml blob" | O texto colado tinha um caractere fora do ASCII e foi ignorado inteiro | Passo 5, "Se o cloud-init não foi aplicado" |
| O `curl` fica parado e dá timeout | Um dos dois firewalls está fechado | Passo 4 (Security List) e `sudo iptables -L INPUT -n` |
| `network edge not found` ao subir a borda | O cloud-init não chegou ao fim | `docker network create edge` e rever o log do cloud-init |
| O log do Caddy mostra falha ao obter o certificado | O domínio ainda não aponta para a VM, ou a porta 80 está fechada | `dig +short meusite.com` e o passo 4 |
| `curl` reclama de certificado inválido | O certificado ainda não foi emitido | `docker logs edge-caddy-1` |
| O job "Construir e enviar as imagens" fica parado em "Queued" | O repositório ainda é privado e o runner ARM não está disponível para ele | Passo 12 |
| O job "Publicar na VM" falha com `denied` ou `unauthorized` | As imagens no GHCR ainda são privadas | Passo 14 |
| O job "Publicar na VM" falha com `Permission denied (publickey)` | O secret `SSH_KEY` está incompleto, ou é a chave pública | Passo 11 |
| O job "Publicar na VM" falha com `Host key verification failed` | O secret `SSH_KNOWN_HOSTS` não bate com a VM (IP trocado ou VM recriada) | Refazer os passos 10 e 11 |
| O deploy ficou verde, mas o site responde 502 | O projeto subiu com outro nome, ou os contêineres caíram | `docker ps` e `docker logs site-prod-php-1`, na VM |
| O site abre, mas os links canônicos apontam para o endereço errado | `SITE_URL` errado no `.env` da VM | Passo 9, e publicar de novo |

## Cuidados no dia a dia

- **Nunca rode `down --volumes` na borda.** O volume guarda os certificados, e a Let's Encrypt
  limita quantos emite por semana para o mesmo domínio. Para derrubar, use só `down`.
- **Mudou o `Caddyfile` da borda?** Copie de novo com o `scp` do passo 7 e rode
  `docker compose -p edge -f /srv/landing-page/edge/docker-compose.yml restart`.
- **O cloud-init não roda de novo.** Mudanças em uma VM que já existe são feitas à mão, por SSH.
  Atualize o `cloud-init.yaml` do repositório também, para a próxima máquina nascer igual.
- **A chave privada de deploy** (`~/.ssh/landing-deploy`) dá controle total da VM. Fora do seu
  computador, ela só deve existir nos Secrets do GitHub.
