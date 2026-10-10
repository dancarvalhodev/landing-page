# Tailwind CSS — explicação por analogia

Arquivos explicados: `bin/tailwind/`, `www/resources/css/app.css` e o serviço `tailwind` do
`docker-compose.yml`.

Pense no Tailwind como uma **gráfica que imprime só as etiquetas que você usa**.

O Tailwind tem milhares de classes prontas (`flex`, `text-red-500`, `p-4`...). Mandar todas para o
navegador seria como entregar o catálogo inteiro da gráfica para cada visitante. Em vez disso, ele
lê seus arquivos, anota quais classes aparecem e gera um CSS só com elas.

## As peças

| Peça | Analogia | O que é |
|---|---|---|
| `bin/tailwind/Dockerfile` + `install.sh` | A máquina da gráfica | Um contêiner pequeno que baixa o programa do Tailwind (versão fixada no Dockerfile). Por isso não é preciso instalar Node. |
| `www/resources/css/app.css` | O pedido de impressão | O arquivo de entrada, escrito à mão. Diz "use o Tailwind" e onde procurar classes. |
| `www/public/assets/css/app.css` | As etiquetas impressas | O arquivo de saída, gerado pela máquina. É este que o navegador baixa. |
| Serviço `tailwind` no `docker-compose.yml` | O funcionário de plantão | Mantém a máquina ligada e observando seus arquivos. |

## O pedido de impressão

```css
@import "tailwindcss" source(none);

@source "../../templates";
@source "../../src";
@source "../../content";
@source "../../public/assets/js";
```

- A primeira linha liga o Tailwind e diz "não saia procurando classes por conta própria".
- As linhas `@source` são a lista de gavetas onde ele deve procurar: templates, código PHP,
  conteúdo em Markdown e JavaScript. Sem essa lista, ele vasculharia também a pasta `vendor/`,
  cheia de código de terceiros.

É também nesse arquivo que entram as cores e fontes do site (etapa 3 do plano).

## O funcionário de plantão

O serviço roda em modo `--watch`: fica olhando as pastas da lista. Você salva um template usando
uma classe nova, ele percebe e reimprime o CSS em milissegundos. Você recarrega a página e a
mudança está lá.

## Em produção

No servidor não existe funcionário de plantão: o contêiner `tailwind` é só do desenvolvimento. O
CSS é impresso uma vez, durante o build da imagem do Caddy, por um estágio chamado `assets`, com
`--minify` para sair menor, e já vai embutido na imagem (ver `producao.md`, "9b — Imagem Caddy").

Por causa disso a versão do Tailwind está escrita em dois lugares: `bin/tailwind/Dockerfile`
(desenvolvimento) e `bin/caddy/Dockerfile` (produção). Ao atualizar um, atualize o outro, senão o
CSS publicado sai diferente do que você viu no computador. O script de instalação
(`bin/tailwind/install.sh`) é um só, usado pelos dois.

## Regras práticas

- **Edite só o arquivo de `resources/`.** O de `public/assets/css/` é sobrescrito a cada
  reimpressão, e por isso está no `.gitignore`.
- **Escreva os nomes das classes por inteiro.** O Tailwind procura texto, não executa PHP.
  `text-red-500` ele encontra; `'text-' . $cor . '-500'` ele não encontra, e a classe fica sem
  estilo.

## Comandos úteis

```bash
docker compose logs tailwind      # ver se o CSS foi gerado ou se houve erro
docker compose restart tailwind   # forçar uma nova geração
```
