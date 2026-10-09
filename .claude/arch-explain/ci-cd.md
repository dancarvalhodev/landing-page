# CI/CD — testes e publicação automáticos

Arquivos explicados: `.github/workflows/ci.yml`. Uma seção por subetapa da etapa 11. Para as
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
    push:
        branches: [master]
    pull_request:
```

| Evento | Quando acontece | Para quê |
|---|---|---|
| `pull_request` | Ao abrir um Pull Request e a cada novo commit enviado a ele | Conferir a alteração antes do merge |
| `push` na `master` | Depois do merge | Conferir o resultado final, já na `master` |

Um push em uma branch sem Pull Request aberto não dispara nada. É de propósito: se o CI rodasse em
todo push e também em todo Pull Request, cada commit de um Pull Request rodaria duas vezes.

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

O CI só roda no GitHub, e só quando há um Pull Request (ou um push na `master`).

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
