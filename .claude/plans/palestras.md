# Plano: seção de palestras na home

Criado em 2026-10-14. Aguardando aprovação do dev.

## Objetivo

Uma seção "Palestras" ("Talks" no inglês) na home, com as duas palestras do dev no YouTube. Cada
palestra aparece com o player do YouTube embutido, não só com um link.

## Como vai funcionar

A seção segue o mesmo modelo dos depoimentos, que já existe: uma lista no perfil
(`pt.md`/`en.md`), uma partial da home e um `if` que só mostra a seção quando a lista tem itens.
Não precisa de controller, rota nem classe nova: o `profile` já chega em todo template pelo
`AbstractController`.

### Dados (`www/content/pt.md` e `en.md`)

```yaml
talks:
  - title: Título da palestra
    event: Nome do evento
    date: out. 2025
    language: pt
    youtube: dQw4w9WgXcQ
```

- **`youtube` guarda só o ID do vídeo** (o trecho depois de `watch?v=` ou de `youtu.be/`), não a
  URL inteira. O template monta o endereço do player, então um link copiado errado não vira um
  endereço qualquer dentro do `src` do iframe.
- **`date` é texto**, no mesmo formato do `period` dos empregos ("out. 2025" / "Oct 2025"). Não
  vira timestamp, porque ninguém ordena nem filtra palestras por data; a ordem é a da lista.
- Título e evento ficam nos dois arquivos, traduzidos. O vídeo é o mesmo nos dois idiomas.
- **`language` é o idioma em que a palestra foi dada** (`pt` ou `en`). As duas palestras de hoje
  são em português. Quando o idioma da palestra é diferente do idioma da página, o card mostra
  uma etiqueta: em `/en`, "In Portuguese". Decisão do dev: as palestras aparecem também em
  `/en`, com o aviso, para o visitante saber o idioma antes de dar play.

### Player

```twig
<iframe
    src="https://www.youtube-nocookie.com/embed/{{ talk.youtube|url_encode }}"
    title="{{ talk.title }}"
    loading="lazy"
    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
    referrerpolicy="strict-origin-when-cross-origin"
    allowfullscreen
    class="aspect-video w-full rounded-box"></iframe>
```

- **`youtube-nocookie.com`:** o domínio do YouTube que não grava cookies antes de o visitante
  dar play.
- **`loading="lazy"`:** o player (cerca de 1 MB cada) só é baixado quando a seção se aproxima da
  tela. Assim o carregamento do topo da página não fica mais lento.
- **`aspect-video`:** classe do Tailwind que mantém a proporção 16:9 em qualquer largura, do
  celular ao desktop.
- **`title`:** leitores de tela anunciam o iframe por esse nome.
- **`referrerpolicy`:** o YouTube recusa o player (erro 153) quando não recebe a origem da
  página. É a mesma política que o Caddyfile já manda para o site todo.
- O Caddyfile não tem `Content-Security-Policy`, então o iframe não precisa de liberação no
  servidor.

### Etiqueta de idioma

```twig
{% if talk.language != i18n.locale.value %}
    <span>{{ i18n.text('home.talks.language.' ~ talk.language) }}</span>
{% endif %}
```

Os textos ficam em `lang/{pt,en}.php`: `home.talks.language.pt` ("Em português" / "In
Portuguese") e `home.talks.language.en` ("Em inglês" / "In English"). A chave `.en` ainda não
será usada, mas o par já fica pronto para uma palestra futura em inglês. São duas linhas por
idioma, e assim o template não precisa de exceção.

### Posição na home

A seção entra **depois de Projetos e antes de Experiência**: palestras mostram trabalho, assim
como os projetos. Se o dev preferir outro lugar, basta mudar uma linha no `home/index.html.twig`.

## Mudança durante a etapa 2: capa própria no lugar da thumbnail (2026-10-14)

Ao conferir a etapa 2, o dev optou por usar thumbnail próprias. Decisão: não mostrar a thumbnail do YouTube. O player
descrito acima deixa de aparecer direto na página e passa a ser criado só no clique ("fachada").

- **A capa:** um link 16:9 no degradê do site (laranja → rosa → roxo, as cores do favicon), com
  um botão de play e o título da palestra. Usa as classes do Tailwind com os tokens
  `brand-orange`, `brand-rose` e `brand-purple`, sem CSS novo e sem imagens.
- **O clique:** um arquivo novo, `www/public/assets/js/talk-player.js`, troca a capa pelo
  `iframe` do player, com `autoplay=1` (o `allow` ganha `autoplay`), então o vídeo começa a
  tocar no mesmo clique.
- **Sem JS:** a capa é um link comum para `youtube.com/watch?v=<ID>`. Se o JS não carregar, o
  clique abre o vídeo no YouTube.
- **Efeito extra:** a página fica mais leve, porque o player do YouTube (cerca de 1 MB) só é
  baixado no clique.

## O que preciso do dev antes da etapa 1

Para cada uma das duas palestras:

1. O link do YouTube.
2. O título em português; eu traduzo o título para o `/en`.
3. O nome do evento.
4. O mês e o ano.

## Etapas de implementação

1. **Dados e textos.** Acrescentar `talks` em `pt.md` e `en.md` com as duas palestras
   (`language: pt` nas duas) e as chaves `home.talks.title`, `home.talks.language.pt` e
   `home.talks.language.en` em `lang/pt.php` e `lang/en.php`. O teste
   `testEveryLanguageHasTheSameKeys` já confere que as chaves existem nos dois idiomas. Em
   `ContentRepositoryTest`, conferir que toda palestra tem `title`, `event`, `language` e
   `youtube`, como já é feito com os artigos. Verificar: `composer test`, `stan` e `cs`.
2. **Seção na home.** Criar `home/_talks.html.twig` (título da seção, depois um card por
   palestra com o player, o título, o evento, a data e a etiqueta de idioma quando couber) e
   incluí-la em `home/index.html.twig` com o mesmo `if` dos depoimentos. Ajuste pedido na
   conferência: capa no degradê do site no lugar da thumbnail, com `talk-player.js` criando o
   player no clique (ver "Mudança durante a etapa 2"). Verificar no navegador:
   `/` sem etiqueta e `/en` com "In Portuguese", tema claro e escuro, largura de celular e
   desktop, e se o vídeo toca.
3. **Documentação.** Registrar o campo `talks` na seção "Conteúdo" do `CLAUDE.md` (onde fica
   cada coisa e por que o campo guarda só o ID) e atualizar o `.claude/handoff.md`.

## Progresso

- [x] 1. Dados e textos — confirmada em 2026-10-14
- [x] 2. Seção na home — confirmada em 2026-10-14
- [ ] 3. Documentação — entregue, aguardando conferência
