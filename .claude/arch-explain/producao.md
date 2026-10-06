# Imagens de produção — o que muda em relação ao desenvolvimento

Arquivos explicados: `bin/php/Dockerfile`, `config/php/php.prod.ini`, `.dockerignore` e o serviço
`php` do `docker-compose.yml`. Este documento cresce a cada subetapa da etapa 9.

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
docker build -f bin/php/Dockerfile --target prod -t landing-page-php:prod-test .
docker run --rm landing-page-php:prod-test php -m
```

A lista de módulos não pode ter `xdebug`.
