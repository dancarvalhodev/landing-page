# IntersectionObserver — como a Home revela as seções ao rolar

Arquivo explicado: `www/public/assets/js/scroll-reveal.js`.

## O que é o `IntersectionObserver`

É uma API do navegador que **avisa automaticamente quando um elemento entra ou sai da área visível
da tela** (o "viewport"). Pensa nele como um segurança parado numa porta: você entrega uma lista de
elementos pra ele vigiar, e ele te chama de volta (callback) toda vez que um deles cruza a entrada —
sem você precisar ficar perguntando "já apareceu? já apareceu?" a cada pixel rolado.

Antes dessa API existir, o jeito comum de fazer isso era ouvir o evento `scroll` da página e, a cada
rolagem, calcular manualmente a posição de cada elemento (`getBoundingClientRect()`) pra ver se já
estava visível — caro de rodar, porque o evento `scroll` dispara dezenas de vezes por segundo. O
`IntersectionObserver` faz esse cálculo de forma otimizada, fora da thread principal quando
possível, e só avisa quando algo realmente muda.

## Linha por linha

```js
const sections = document.querySelectorAll('[data-reveal]');
```
Pega todas as seções marcadas com o atributo `data-reveal` (sobre, projetos, experiência,
depoimentos, contato — ver `www/templates/home/_about.html.twig` e as outras partials de
`www/templates/home/`). O "hero" fica de fora de propósito: já é a primeira coisa visível ao abrir
a página, não precisa de entrada.

```js
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.remove(...HIDDEN_CLASSES);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.2 });
```
Cria o "segurança". Dois pedaços:

1. **A função callback** `(entries) => {...}` — chamada pelo navegador toda vez que algo muda na
   visibilidade de qualquer elemento observado. `entries` é uma lista, porque várias mudanças podem
   acontecer de uma vez (ex: rolar rápido e três seções cruzarem a tela juntas). Para cada `entry`:
   - `entry.target` é o elemento observado (a `<section>`).
   - `entry.isIntersecting` é `true` quando aquele elemento está (pelo menos parcialmente) visível
     na tela agora.
   - Se está visível: `classList.remove(...HIDDEN_CLASSES)` tira as classes que escondiam a seção
     (`motion-safe:opacity-0`, `motion-safe:translate-y-6`), e a transição global do CSS (ver
     `www/resources/css/app.css`, regra `* { @apply motion-safe:transition-all ... }`) anima a
     mudança suavemente.
   - `observer.unobserve(entry.target)` manda o segurança parar de vigiar aquele elemento — ele já
     revelou, não precisa mais checar (evita trabalho à toa se a pessoa rolar pra cima e pra baixo
     depois).

2. **O segundo argumento**, `{ threshold: 0.2 }` — configura **quanto do elemento precisa estar
   visível** para disparar o callback com `isIntersecting: true`. `0.2` = 20%. Ou seja: a seção só é
   revelada quando pelo menos 20% dela já apareceu na tela, não no instante em que a borda encosta
   no topo da janela. É um ajuste fino de "quão cedo" a animação começa.

```js
sections.forEach((section) => {
    section.classList.add(...HIDDEN_CLASSES);
    observer.observe(section);
});
```
Para cada seção: primeiro **esconde** ela (adiciona as classes de opacidade/deslocamento), depois
manda o `observer` **vigiar** ela. A ordem importa — e é por isso que essas duas classes só são
adicionadas aqui, via JS, nunca escritas direto no Twig: se o script não rodar (bloqueado por um
navegador antigo sem suporte a `IntersectionObserver`, ou por qualquer outro motivo), elas nunca são
escondidas, e as seções aparecem normais, sem nunca ficar invisíveis para sempre. É por isso que todo
o bloco fica dentro de `if ('IntersectionObserver' in window) { ... }`.

## Por que a transição não está neste arquivo

A primeira versão deste script também adicionava as classes de transição (`motion-safe:transition-all`,
`motion-safe:duration-700`) nas seções observadas. Isso causou um efeito colateral: a troca de tema
claro/escuro só ficava suave nas seções com `data-reveal` (porque só elas tinham a classe de
transição grudada), enquanto o "hero" trocava de cor instantaneamente.

A correção foi mover a transição para uma regra única e global em `app.css`
(`* { @apply motion-safe:transition-all motion-safe:duration-400; }`), que vale para a página
inteira — tema e entrada por rolagem usam exatamente a mesma regra. Esse arquivo ficou responsável
só por esconder/revelar; a animação em si é tarefa do CSS.
