# Caddy — explicação por analogia

Arquivo explicado: `config/caddy/Caddyfile`.

Pense no Caddy como o **porteiro de um prédio**. Toda visita (requisição) passa por ele, e o
Caddyfile é a folha de instruções que ele segue.

## As instruções, em ordem

1. **"Fique na portaria da porta 80 e atenda qualquer um"** (`:80`). Ele não cuida do cadeado
   (HTTPS); isso será trabalho de outro porteiro, na entrada da rua: o Caddy de borda da etapa 10 (ver `infra.md`).

2. **"O prédio é a pasta `/app/public`"** (`root`). Só o que está lá dentro pode ser entregue a
   visitantes.

3. **"Se pedirem algo escondido, diga que não existe"** (`@hidden` + `respond 404`). Arquivos que
   começam com ponto, como `.env`, guardam segredos. O porteiro finge que não há nada ali.

4. **"Se pedirem um arquivo que existe, entregue você mesmo"** (`file_server`). CSS, imagens e
   fontes ficam na prateleira da portaria; ele pega e entrega, sem incomodar ninguém.

5. **"Para todo o resto, interfone para o PHP"** (`php_fastcgi php:9000`). Se a visita pede
   `/blog/meu-artigo`, não existe um arquivo com esse nome. O porteiro liga para o apartamento do
   PHP, que monta a página na hora e devolve para ele entregar.

## Os detalhes de acabamento

- **`encode`**: ele embala a entrega a vácuo (compacta) para chegar mais rápido.
- **`Cache-Control`**: ele avisa a visita "pode guardar este CSS por um ano, não precisa voltar
  para buscar de novo".
- **`header`**: ele cola etiquetas de segurança em cada entrega e arranca a que diz "sou o Caddy".
- **`Access-Control-Allow-Origin`** (só em `/assets/css/giscus-*.css`): um bilhete assinado
  dizendo "autorizo o `giscus.app` a usar este arquivo". Os comentários rodam em um iframe do
  Giscus, que busca o CSS do tema na nossa portaria com `crossorigin="anonymous"`; sem o bilhete,
  o navegador baixa o arquivo e joga fora, e os comentários ficam sem as cores do site. Foi o que
  aconteceu no primeiro deploy. A autorização vale só para o Giscus e só para esses dois arquivos.
  O caminho completo do tema está em `giscus.md`.

## Em uma frase

Arquivo que existe, o Caddy entrega; arquivo escondido, ele nega; qualquer outra coisa, ele
repassa ao PHP.

## Três requisições de exemplo

| Requisição | O que acontece |
|---|---|
| `/assets/css/app.css` | O arquivo existe: o porteiro entrega, com o aviso de cache de um ano. |
| `/blog/meu-artigo` | Não existe arquivo: vai para o `index.php`, que decide o que mostrar. |
| `/.env` | É escondido: recebe 404, sem chegar ao PHP. |

## Pegadinhas

- **A ordem do arquivo não é a ordem de execução.** O Caddy segue uma ordem própria e fixa
  (`root` → `header` → `encode` → `respond` → `php_fastcgi` → `file_server`). Por isso
  `file_server` aparece por último no texto e a lógica acima funciona do mesmo jeito.
- **O caminho precisa ser igual nos dois contêineres.** O Caddy só diz ao PHP qual arquivo
  abrir (`/app/public/index.php`); quem abre é o PHP. No desenvolvimento, `./www` é montado em
  `/app` tanto no `caddy` quanto no `php`. Em produção não há pasta montada: os arquivos são
  copiados para dentro das duas imagens, no mesmo caminho (ver `producao.md`, "Por que o
  `index.php` aparece nas duas imagens").
- **Mudou o Caddyfile? Reinicie.** A API de administração está desligada (`admin off`), então
  `caddy reload` não funciona: use `docker compose restart caddy`. Isso vale para o
  desenvolvimento. Em produção o Caddyfile vai embutido na imagem, e uma mudança nele só chega ao
  servidor por um deploy.
- **O bloqueio de ocultos também pega `/.well-known/`.** Hoje nada usa esse caminho.
- **Cache de um ano pede endereço com versão.** Tudo em `/assets` sai como `immutable`, então um
  arquivo só é buscado de novo se o endereço mudar. Nos templates isso é o `?v=` do `asset()`.
  Um script que precise do endereço de um arquivo deve recebê-lo pronto do template, como o
  `giscus-theme.js` (`data-light` e `data-dark`, via `asset_absolute()`), e não montá-lo sozinho.

## Comandos úteis

```bash
docker compose exec caddy caddy validate --config /etc/caddy/Caddyfile   # confere a sintaxe
docker compose restart caddy                                             # aplica mudanças
```
