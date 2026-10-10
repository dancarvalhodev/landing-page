# Imagens de produção — o que muda em relação ao desenvolvimento

Arquivos explicados: `bin/php/Dockerfile`, `bin/caddy/Dockerfile`, `config/php/php.prod.ini`,
`.dockerignore`, `docker-compose.prod.yml`, os serviços `php` e `caddy` do `docker-compose.yml` e,
na parte da versão dos arquivos estáticos, `www/src/Config.php` e
`www/src/Twig/AssetExtension.php`. Uma seção por subetapa da etapa 9.

## A ideia geral

Pense em uma **cozinha de restaurante** e em uma **marmita**.

- No desenvolvimento, o contêiner é a cozinha: os ingredientes (o código em `./www`) ficam na
  bancada e você mexe neles o tempo todo. O contêiner enxerga a pasta do seu computador, então
  salvar um arquivo já muda o site.
- Em produção, a imagem é a marmita: sai fechada, com tudo dentro, e é igual em qualquer lugar onde
  for aberta. No servidor não existe a pasta `./www`; o código precisa ter sido copiado para dentro
  da imagem na hora em que ela foi construída.

## 9a — Imagem PHP

### Três estágios no mesmo Dockerfile

Um Dockerfile pode ter vários `FROM`. Cada um começa um **estágio**, e um estágio pode partir de
outro. É como uma receita com uma massa básica e duas coberturas:

| Estágio | Parte de | O que tem |
|---|---|---|
| `base` | `php:8.5-fpm-alpine` | PHP, extensões, Composer e Xdebug (a cópia do Dockerfile do dev) |
| `dev` | `base` | Nada a mais; o código entra pela pasta compartilhada |
| `prod` | `base` | Sem Xdebug, com `php.prod.ini`, o código copiado e as dependências instaladas |

Quem escolhe o estágio é o `target`. O `docker-compose.yml` de desenvolvimento pede `target: dev`;
o de produção (subetapa 9d) pedirá `prod`.

### Como o Docker sabe o que não precisa rodar

O Docker não lê o arquivo de cima para baixo até parar em algum ponto. Ele parte do estágio pedido
e sobe só pelos estágios de que esse depende, como em uma árvore genealógica: para a ficha de
alguém, bastam os pais, não os irmãos.

```
base ──► dev     (FROM base AS dev)
   └───► prod    (FROM base AS prod)
```

- `--target dev` constrói `base` e depois `dev`. O `prod` é irmão, ninguém no caminho depende
  dele, e as linhas dele nem rodam.
- `--target prod` constrói `base` e depois `prod`, ignorando o `dev`.

Cada estágio vai de um `FROM ... AS nome` até o próximo `FROM`. O `dev` é só a linha
`FROM base AS dev`, então ele é o `base` com outro nome.

Sem `--target`, o Docker constrói o último estágio do arquivo, que aqui é o `prod`. Por isso o
`target: dev` do `docker-compose.yml` é obrigatório: sem ele, o ambiente de desenvolvimento
subiria sem Xdebug e com o código copiado na imagem. A ordem no arquivo só importa para a
definição: um estágio precisa aparecer antes de quem o usa no `FROM`.

### O que o estágio `prod` faz, na ordem

1. **Desliga o Xdebug**, apagando o arquivo que o carrega. O Xdebug deixa o PHP mais lento e não
   tem uso fora do desenvolvimento.
2. **Troca o `php.ini`** pelo `php.prod.ini` (ver abaixo).
3. **Copia `www/`** para `/app`.
4. **Roda `composer install --no-dev --classmap-authoritative`.** `--no-dev` deixa de fora
   PHPUnit, PHPStan e PHP-CS-Fixer. `--classmap-authoritative` gera uma lista fechada de onde
   está cada classe, para o PHP não procurar arquivo no disco a cada requisição.
5. **Cria `var/cache/twig` e entrega a pasta ao usuário `www-data`.** Os arquivos são copiados
   como `root`, mas o PHP-FPM atende as requisições como `www-data`, e fora de desenvolvimento o
   Twig grava ali os templates compilados.

### `php.prod.ini`

| Linha | Efeito |
|---|---|
| `display_errors = Off` | Um erro nunca aparece na página para o visitante |
| `log_errors = On` e `error_log = /proc/self/fd/2` | O erro vai para a saída do contêiner, onde o `docker logs` mostra |
| `expose_php = Off` | O servidor não anuncia a versão do PHP nos cabeçalhos |
| `opcache.enable=1` | O PHP guarda na memória o código já compilado |
| `opcache.validate_timestamps=0` | O PHP não confere se os arquivos mudaram, porque dentro da imagem eles nunca mudam |

A última linha é o motivo de ela só valer em produção: com ela, editar um arquivo não teria efeito
até reiniciar o PHP.

### Por que o log de erros não vai para um arquivo

`/proc/self/fd/2` é a saída de erro do próprio processo. Tudo o que um contêiner escreve nela o
Docker captura e guarda no servidor; lê-se com `docker logs landing_php`. É como a caixa-preta de
um avião: o PHP só "fala", e quem grava é o Docker, do lado de fora.

Um arquivo dentro do contêiner sumiria a cada deploy, porque o contêiner é recriado. Para
sobreviver, pediria um volume, permissão de escrita para o `www-data` e rotação. O limite de
tamanho do log do Docker é configurado no `docker-compose.prod.yml` (subetapa 9d).

### Por que o contexto do build passou a ser a raiz

O **contexto** é a pasta que o Docker enxerga durante o build; um `COPY` só alcança o que está
dentro dela. Antes o contexto do PHP era `bin/php`, que só tem o Dockerfile. Para copiar `www/` e
`config/php/php.prod.ini`, o contexto precisa ser a raiz do repositório. Por isso o
`docker-compose.yml` agora diz `context: .` e aponta o Dockerfile com `dockerfile:`.

### `.dockerignore`

Com a raiz como contexto, o Docker enviaria tudo para o build, inclusive `.git`, `vendor/` e o
`.env`. O `.dockerignore` é a lista do que fica de fora. Duas entradas merecem explicação:

- `www/vendor`: as dependências são instaladas dentro da imagem, sem as de desenvolvimento. Se a
  pasta do computador fosse copiada, levaria junto o PHPUnit e companhia.
- `www/public/assets/css/app.css`: é o CSS gerado pelo Tailwind em modo de desenvolvimento. O CSS
  de produção é gerado na imagem do Caddy (subetapa 9b).

### Como conferir

```
docker build --build-arg ASSET_VERSION=teste -f bin/php/Dockerfile --target prod -t landing-page-php:prod-test .
docker run --rm landing-page-php:prod-test php -m
```

A lista de módulos não pode ter `xdebug`. O `--build-arg ASSET_VERSION` é exigido desde a subetapa
9c.

## 9b — Imagem Caddy

Arquivos: `bin/caddy/Dockerfile` e o serviço `caddy` do `docker-compose.yml`.

### O problema

No desenvolvimento, o CSS é gerado por um terceiro contêiner, o `tailwind`, que fica vigiando os
templates e reescreve `public/assets/css/app.css` a cada mudança. Em produção esse contêiner não
existe, e o `app.css` nem vai para o git. O CSS precisa nascer durante o build e já sair dentro da
imagem do Caddy, que é quem entrega os arquivos estáticos.

### Um estágio que só serve de oficina

Voltando à marmita: para cozinhar você usa panela e fogão, mas só a comida vai na embalagem. O
estágio `assets` é a panela: ele instala o Tailwind, gera o CSS e é descartado. A imagem final
leva só o arquivo pronto.

| Estágio | Parte de | O que faz |
|---|---|---|
| `dev` | `caddy:2-alpine` | O Caddy puro; os arquivos entram pela pasta compartilhada |
| `assets` | `alpine:3` | Instala o Tailwind, copia `www/` e gera o `app.css` minificado |
| `prod` | `dev` | Recebe o Caddyfile, a pasta `public/` e o CSS vindo do `assets` |

A linha que faz a ponte é `COPY --from=assets ...`: ela copia um arquivo de outro estágio, em vez
de copiar do computador. É por causa dela que o Docker constrói o `assets` quando o alvo é o
`prod`, mesmo o `prod` não partindo dele no `FROM`. Na árvore da subetapa 9a, `--from` conta como
dependência, igual ao `FROM`.

### Por que o Tailwind precisa de `www/` inteiro

O Tailwind só gera as classes que encontra em uso. Ele procura nas pastas listadas em
`www/resources/css/app.css` (`templates`, `src`, `content` e `public/assets/js`), então o estágio
`assets` copia `www/` inteiro. Nada disso vai para a imagem final: só o `app.css`.

O `--minify` tira espaços e quebras de linha do CSS. O arquivo de produção fica com cerca de 18 KB.

### O que a imagem `prod` carrega

- `/etc/caddy/Caddyfile`: o mesmo do desenvolvimento, agora embutido.
- `/app/public/`: fontes, imagens, JS, os CSS do Giscus, o `index.php` e o `app.css` gerado.

### Por que o `index.php` aparece nas duas imagens

`www/public/index.php` é a porta de entrada do site: um arquivo de poucas linhas que liga o app.
Toda página (`/`, `/blog`, `/en`) passa por ele. Quem o executa é sempre o PHP; o Caddy nunca roda
PHP.

Mesmo assim, o Caddy precisa ter uma cópia dele. Lembre do porteiro do `caddy.md`: antes de
interfonar para o apartamento do PHP, ele olha a lista de moradores na portaria. Se `index.php`
não estiver na lista, ele responde "não existe" (404) sem nem interfonar.

Na prática, quando chega `/blog`, o `php_fastcgi` procura, na pasta do próprio Caddy:

1. um arquivo chamado `/blog`;
2. um `/blog/index.php`;
3. o `/index.php`.

O terceiro existe, então ele repassa a requisição ao PHP dizendo "abra `/app/public/index.php`". O
PHP abre a cópia dele, que é a que roda.

A rota não se perde nessa troca. O recado do Caddy para o PHP leva duas informações separadas:

| Campo | Valor para `/blog/meu-artigo?q=php` | Quem usa |
|---|---|---|
| `SCRIPT_FILENAME` | `/app/public/index.php` | O PHP-FPM, para saber qual arquivo executar |
| `REQUEST_URI` | `/blog/meu-artigo?q=php` | O Slim, para escolher a rota em `routes/web.php` |

É o porteiro dizendo no interfone: "apartamento `index.php`, a visita quer falar sobre
`/blog/meu-artigo`". O arquivo é sempre o mesmo; o assunto muda a cada requisição. Isso vale igual
no desenvolvimento e em produção.

No desenvolvimento isso passa despercebido, porque os dois contêineres enxergam a mesma pasta
`./www`. Em produção cada imagem tem os seus arquivos, então o `index.php` precisa estar nas duas:

| Imagem | Tem o `index.php`? | Para quê |
|---|---|---|
| Caddy | Sim, dentro de `public/` | Só para constar na "lista de moradores" |
| PHP | Sim, com todo o resto de `www/` | É o que de fato executa |

O restante do código (`src/`, `templates/`, `content/`, `vendor/`) fica só na imagem do PHP.

### Um cuidado: a versão do Tailwind está em dois lugares

`bin/tailwind/Dockerfile` (desenvolvimento) e `bin/caddy/Dockerfile` (produção) declaram cada um o
seu `TAILWIND_VERSION`. Ao atualizar um, atualize o outro, senão o CSS de produção sai diferente
do que você viu no desenvolvimento. O script de instalação (`bin/tailwind/install.sh`) é um só,
usado pelos dois.

### Como conferir

```
docker build -f bin/caddy/Dockerfile --target prod -t landing-page-caddy:prod-test .
docker run --rm landing-page-caddy:prod-test ls -la /app/public/assets/css
```

A lista precisa ter o `app.css`, mesmo ele não existindo no git.

## 9c — Versão do CSS em produção

Arquivos: `www/src/Config.php`, `www/src/Twig/AssetExtension.php` e o estágio `prod` de
`bin/php/Dockerfile`.

### Para que serve o `?v=`

O Caddy manda o navegador guardar tudo de `/assets` por um ano, para o site abrir rápido. O
problema é quando o arquivo muda: o navegador continuaria usando a cópia guardada.

Pense na **data de validade de um produto**. O navegador guarda o pote na despensa e não volta ao
mercado enquanto o rótulo for o mesmo. O `?v=` é o rótulo: `app.css?v=1` e `app.css?v=2` são, para
o navegador, dois produtos diferentes, e ele busca o novo.

### O que quebrava em produção

No desenvolvimento, a função `asset()` usa como rótulo a data de modificação do arquivo
(`filemtime`). Quem monta o HTML é o PHP, então é o PHP que precisa enxergar o arquivo.

Em produção, o `app.css` só existe na imagem do Caddy (subetapa 9b). O PHP não o encontra, e a
função devolvia o endereço sem rótulo: `/assets/css/app.css`. O visitante que já tivesse o CSS
guardado ficaria com o visual antigo por até um ano depois de um deploy.

### A solução: um rótulo por build

Em vez de olhar arquivo por arquivo, a produção usa um rótulo único para o build inteiro, a
variável `ASSET_VERSION`. O caminho dela:

1. Quem constrói a imagem informa o valor: `docker build --build-arg ASSET_VERSION=<valor>`. No
   deploy (etapa 11), o valor será o identificador do commit.
2. O estágio `prod` do Dockerfile grava o valor dentro da imagem (`ENV ASSET_VERSION`).
3. `Config::fromEnv()` lê a variável e guarda em `assetVersion`.
4. `AssetExtension::assetUrl()` usa esse valor no `?v=` de todos os arquivos, sem conferir se o
   arquivo existe.

| Ambiente | `ASSET_VERSION` | Resultado |
|---|---|---|
| Desenvolvimento | Não definida | `app.css?v=<data de modificação>`, como sempre foi |
| Produção | Identificador do build | `app.css?v=<identificador>`, igual para todos os arquivos |

### Por que o valor fica dentro da imagem

O rótulo e os arquivos precisam andar juntos. Como o valor é gravado na imagem, voltar para uma
imagem antiga (rollback) traz de volta o rótulo antigo junto com o CSS antigo. Se ele fosse uma
variável do servidor, daria para trocar a imagem e esquecer o rótulo.

### Por que o build falha sem a versão

A linha `RUN test -n "$ASSET_VERSION"` interrompe o build se o valor não for informado. Sem ela,
um build feito por engano sem o `--build-arg` geraria uma imagem que funciona, mas com o CSS sem
rótulo, e o defeito só apareceria semanas depois, no navegador de quem já visitou o site. Melhor
um erro na hora.

### O custo dessa escolha

Todo deploy troca o rótulo de todos os arquivos, mesmo os que não mudaram. Quem volta ao site
depois de um deploy baixa de novo o CSS e o JS. São arquivos pequenos e os deploys são poucos por
mês, então o custo é baixo. As fontes não entram nisso: são chamadas de dentro do CSS, sem `?v=`.

A alternativa seria gerar o CSS também na imagem do PHP, para o `filemtime` continuar funcionando.
Isso duplicaria o estágio do Tailwind nos dois Dockerfiles e ainda dependeria de datas de arquivo,
que mudam a cada build de qualquer forma.

### Como conferir

Construa com um valor qualquer e veja o HTML:

```
docker build --build-arg ASSET_VERSION=teste -f bin/php/Dockerfile --target prod -t landing-page-php:prod-test .
```

Sem o `--build-arg`, o build precisa falhar na linha `RUN test -n "$ASSET_VERSION"`.

## 9d — `docker-compose.prod.yml`

Arquivo: `docker-compose.prod.yml`.

### O que ele é

O `docker-compose.yml` é a planta da cozinha; o `docker-compose.prod.yml` é o pedido de entrega
das marmitas. Ele diz quais imagens subir e com quais variáveis, e mais nada: não há pasta
compartilhada nem contêiner do Tailwind.

| | `docker-compose.yml` (desenvolvimento) | `docker-compose.prod.yml` |
|---|---|---|
| Estágio das imagens | `dev` | `prod` |
| Código | Pasta `./www` compartilhada | Dentro da imagem |
| CSS | Contêiner `tailwind` em modo watch | Gerado no build, dentro da imagem do Caddy |
| Caddyfile e `php.ini` | Montados do computador | Dentro das imagens |
| Nome das imagens | Automático | `ghcr.io/dancarvalhodev/landing-page-{php,caddy}:<IMAGE_TAG>` |
| Limite de log | Não | 3 arquivos de 10 MB por contêiner |

### As variáveis

| Variável | Para quê | Exemplo |
|---|---|---|
| `IMAGE_TAG` | A etiqueta das duas imagens e também o `ASSET_VERSION` do build | o identificador do commit |
| `APP_ENV` | O ambiente; no servidor, sempre `production` | `production` |
| `SITE_URL` | O endereço oficial daquele ambiente | `https://meusite.com` |

O `IMAGE_TAG` é obrigatório: sem ele, o Compose se recusa a rodar e mostra "informe IMAGE_TAG".
Isso vale para qualquer comando com este arquivo, inclusive `down` e `logs`.

Uma variável só faz dois papéis de propósito. O `IMAGE_TAG` vira a etiqueta da imagem e é
repassado ao build como `ASSET_VERSION` (subetapa 9c). Assim é impossível ter uma imagem com
etiqueta de um commit e `?v=` de outro.

### `image` e `build` juntos

Cada serviço tem as duas chaves, e cada uma serve a um momento:

- `build`: como construir a imagem. É usado no seu computador e, na etapa 11, pelo GitHub Actions.
- `image`: o nome que a imagem recebe ao ser construída e de onde ela é baixada. No servidor só
  esta chave importa: a imagem já vem pronta do GHCR e nada é construído lá.

### Por que `ghcr.io` se o site roda na Oracle

São duas coisas diferentes: o GHCR (GitHub Container Registry) é onde as imagens ficam guardadas,
e a VM da Oracle é onde elas rodam.

Pense em uma transportadora. A fábrica (GitHub Actions) monta as marmitas, mas a cozinha do
restaurante (a VM) fica em outro lugar. No meio precisa existir um depósito onde a fábrica deixa a
carga e o restaurante retira. Esse depósito é o registro de imagens. O nome
`ghcr.io/dancarvalhodev/...` é só o endereço do depósito; não diz onde a imagem vai rodar.

O caminho previsto para a etapa 11:

1. Merge na `master`.
2. O GitHub Actions constrói as duas imagens e envia ao GHCR, com o identificador do commit como
   etiqueta.
3. O workflow entra na VM por SSH e roda `docker compose up`.
4. A VM lê o `image:` deste arquivo, baixa as imagens do GHCR e sobe os contêineres.

Daria para clonar o repositório na VM e construir lá, sem registro. O plano evitou isso porque:

- a VM gratuita é pequena, e compilar extensões do PHP nela é lento e disputa memória com o site
  no ar;
- com as imagens antigas guardadas, o rollback é subir a etiqueta anterior, em segundos.

O GHCR foi escolhido, e não o registro da Oracle ou o Docker Hub, porque o repositório já está no
GitHub: o Actions envia para lá sem cadastrar credencial, e ele é gratuito para imagens públicas.
Se as imagens forem privadas, a VM precisa de um token de leitura do GHCR.

### O limite de log

O Docker guarda tudo o que os contêineres escrevem na saída (inclusive os erros do PHP, ver "Por
que o log de erros não vai para um arquivo"). Sem limite, esse arquivo cresce até encher o disco.
Com `max-size: 10m` e `max-file: "3"`, cada contêiner guarda no máximo 30 MB e descarta o mais
antigo.

### Rodar a produção no seu computador

Desde a subetapa 10a, o Caddy do app não publica porta: ele só é alcançado pelo Caddy de borda.
O passo a passo para subir a produção e a borda no computador está em `infra.md`, na seção
"Testar tudo no computador".

Dois detalhes que valem para qualquer comando com este arquivo:

- As variáveis vão na frente do comando (`IMAGE_TAG=teste APP_ENV=production ... docker compose`)
  porque o `.env` do projeto tem os valores de desenvolvimento; o que é passado na linha de
  comando tem prioridade sobre o `.env`.
- Os erros do PHP se leem com `docker logs site-prod-php-1`.
