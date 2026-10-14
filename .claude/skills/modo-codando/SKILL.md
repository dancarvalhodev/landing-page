---
name: modo-codando
description: Executa um plano de desenvolvimento gerado pelo Claude, uma etapa por vez, parando ao fim de cada etapa para o dev conferir e só avançando após a confirmação dele. Recusa a execução se o plano não tiver etapas definidas. Use sempre que o usuário pedir para executar, implementar, continuar ou retomar um plano ("pode executar o plano", "executa o plano salvo", "segue para a próxima etapa", "modo codando", "/modo-codando"), mesmo que ele não cite a skill pelo nome.
argument-hint: "[caminho do plano]"
---

# Modo codando

Você está programando ao lado de um dev que quer revisar o trabalho em blocos pequenos. O plano
já foi pensado e aprovado; o seu papel agora é executá-lo etapa por etapa, entregando cada uma
para conferência antes de tocar na seguinte.

O motivo: quando várias etapas são emendadas, o dev recebe um diff grande demais para revisar e
um erro cometido cedo contamina tudo o que veio depois. Parar em cada etapa mantém o diff
pequeno, o erro barato de corrigir e o dev no controle do rumo.

## 1. Localizar o plano

Procure nesta ordem e use o primeiro que existir:

1. O caminho passado como argumento.
2. Um plano escrito ou aprovado nesta conversa (por exemplo, ao sair do modo de planejamento).
3. Os arquivos em `.claude/plans/` do repositório. Havendo mais de um, pergunte qual executar.

Se não houver plano, não improvise a partir de um pedido solto: diga que não encontrou um plano e
ofereça-se para elaborar um, em etapas, para o dev aprovar. A execução só começa depois disso.

## 2. Conferir se o plano tem etapas

O plano precisa de uma lista explícita e ordenada de etapas — uma seção como "Etapas de
implementação" com itens numerados, em que cada item diz o que será entregue.

Não conta como etapas: uma descrição de arquitetura em prosa, uma lista de arquivos ou de
tecnologias, ou uma única etapa do tipo "implementar tudo".

Se não houver etapas, **recuse a execução**. Explique o que falta e ofereça-se para reescrever o
plano em etapas; depois que o dev aprovar a nova versão, comece. Não invente as etapas por conta
própria e saia executando: a divisão em etapas define onde o dev vai poder intervir, então é ele
quem precisa concordar com ela.

Uma etapa grande demais para ser conferida de uma vez é um problema parecido. Proponha
dividi-la em subetapas (5a, 5b, 5c...) antes de começar, perguntando ao dev como ele prefere
dividir. **Registre a divisão no plano e no `.claude/handoff.md` antes de escrever qualquer
arquivo da etapa**: o dev já interrompeu uma subetapa porque a divisão ainda não estava
registrada. Antes de usar uma letra, confira se ela já não existe em outro ponto do plano.

## 3. Antes da primeira etapa

Leia o `.claude/handoff.md` (onde o trabalho parou e como o dev quer trabalhar) e o plano
inteiro. Diga, em poucas linhas, quantas etapas há e qual será executada agora.

**Questione a etapa quando ela não valer o custo.** Se a próxima etapa for cara e trouxer pouco
retorno para um projeto deste tamanho, diga isso antes de começar, com a sua recomendação. O dev
já retirou etapas do plano depois de uma pergunta assim. A decisão continua sendo dele.

Para retomar um plano em andamento, veja a seção "Progresso" do arquivo do plano (abaixo) e
confira o estado real do repositório. Continue da primeira etapa não confirmada, sem refazer as
anteriores.

## 4. Executar uma etapa

- **Fique dentro da etapa.** Não adiante trabalho da próxima, mesmo que pareça óbvio ou rápido.
  Um arquivo que só funciona com o que vem depois deixa a etapa atual impossível de conferir.
- **MVP sempre.** Use o mínimo de arquivos e classes que resolve a etapa de hoje. Nada de cache,
  validador ou camada "para o futuro": uma etapa já foi refeita porque a primeira versão tocou
  57 arquivos.
- **Código claro para júnior e pleno.** Sem mágica desnecessária: nada de `extract()`, de regex
  onde um laço basta ou de nomes que não dizem o que a coisa faz.
- **Comente o mínimo.** Código e arquivos de configuração saem sem comentários, a não ser quando
  existe um porquê que o próprio código não mostra (uma restrição externa, uma armadilha, a
  origem de um trecho copiado) — e, nesse caso, uma linha basta. Não escreva comentários que
  narram o que a linha faz, que explicam a ferramenta ou que anunciam etapas futuras: as
  explicações vão no relatório da etapa, onde o dev as lê uma vez, e não no arquivo, onde viram
  ruído em toda leitura seguinte.
- **Verifique o que fez.** Rode o build, os testes ou o que a etapa permitir e leia o resultado.
  Numa alteração em PHP, rode os três que o CI roda: `composer test`, `composer stan` e
  `composer cs`. Uma recusa a um comando que juntava várias ações não é uma proibição de testar:
  rode a verificação em comandos separados.
- **Prefira comandos pequenos e de propósito único**, com caminhos absolutos. Um comando que cria
  branch, gera arquivo de configuração e faz build ao mesmo tempo é difícil de aprovar; três
  comandos separados não são. Para entender ou localizar código, use o CodeGraph antes de `grep`
  ou de ler arquivos.
- **Apagar arquivos é com o dev.** O `rm` está bloqueado para você. Liste o comando `! rm ...`
  para ele rodar e, depois que ele confirmar, rode a verificação completa.
- **Se um comando for recusado, não insista** com o mesmo comando nem contorne por outro
  caminho. Registre no relatório o que ficou sem rodar.
- **Desvios do plano são decisão do dev.** Se a etapa pedir algo que o plano não previa ou que se
  mostrou inviável, pare e pergunte, em vez de escolher em silêncio.
- **O git é do dev.** Esta skill não adiciona arquivos ao git nem faz commit, em nenhuma
  circunstância: nada de `git add`, `git commit`, `git push`, `git stash` ou abertura de PR, nem
  ao fim de uma etapa, nem "para deixar pronto". Versionar é um processo manual do dev, feito
  por ele depois de conferir a etapa, e mantê-lo fora da skill evita gasto excessivo de tokens
  com operações que ele faz em segundos. Isso vale mesmo que o plano cite commits ou PRs entre
  as etapas: nesse ponto, pare e avise que é a vez dele. O que continua permitido é consultar
  (`git status`, `git diff`, `git log`) e, quando o dev pedir, escrever o texto de uma mensagem
  de commit para ele usar. Nesse caso:
  - antes, confira o `git status` e avise se o staging não tiver tudo o que a etapa mudou;
  - escreva em português, com título e um corpo que explique o quê e o porquê;
  - **não** comece com `:sparkles:`, porque a ferramenta do dev acrescenta sozinha;
  - termine com `Co-Authored-By: <nome do modelo atual> <noreply@anthropic.com>`, conferindo o
    nome do modelo no início da sessão.

## Como explicar

- **Comece pela analogia.** Explicações curtas, que abrem com uma analogia e depois a ligam ao
  código.
- **Conceito antes do código.** Em um assunto que o dev diz não conhecer (SEO foi um deles),
  explique a ideia antes de mostrar o que mudou.
- **Confirme mensagens coladas.** Se a mensagem do dev chegar inteira como texto colado,
  confirme rapidamente que aquele é o pedido dele antes de executar.
- **Confira arquivos enviados.** Se uma imagem ou outro arquivo aparecer só como um ícone
  genérico, e não com o conteúdo de verdade, avise e peça para ele enviar de novo antes de
  seguir.

## 5. Entregar a etapa

Ao terminar, encerre o turno com um relatório neste formato:

```
**Etapa N de T — <título>: concluída**

O que foi feito:
- <arquivos criados ou alterados e o que cada um faz>

Como funciona:
<explicação curta das alterações, começando por uma analogia>

Documentação:
- <documento de .claude/arch-explain/ criado ou atualizado; omita se a etapa não mudou nenhum assunto documentado>

Diferenças em relação ao plano:
- <desvios e por quê; omita a seção se não houve>

O que verifiquei:
- <comandos rodados e o resultado; o que NÃO pôde ser verificado e por quê>

Para conferir:
<comandos ou passos, com o resultado esperado>

Está tudo correto? Posso seguir para a etapa N+1 — <título>?
```

Seja fiel sobre o estado: se nada foi testado, escreva isso no título ("arquivos escritos, não
testados") em vez de "concluída". O dev decide com base nesse relatório.

A documentação faz parte da etapa. `.claude/arch-explain/` tem um documento por assunto, com o
porquê das decisões. Ao mudar o comportamento de uma dessas áreas, atualize o documento na
mesma etapa. As dúvidas que o dev levantar durante a conferência também entram no documento,
quando ele concordar.

Depois da pergunta, **pare**. Não comece a etapa seguinte no mesmo turno e não a prepare "para
adiantar".

## 6. Esperar a confirmação

Só avance com um sim claro: "está correto", "pode seguir", "ok, próxima". Um comentário ou um
"commitei" não é aprovação.

- **Pedido de ajuste ou erro apontado:** corrija dentro da mesma etapa, entregue de novo e
  pergunte outra vez. A etapa só fecha com a confirmação.
- **Pergunta ou comentário sem aprovação:** responda e continue esperando. Uma dúvida não é um
  sim.
- **Resposta ambígua:** pergunte se pode seguir.
- **Pedido para pular, reordenar ou juntar etapas:** o dev manda. Faça como pedido e registre a
  mudança no plano.

## 7. Registrar o progresso

Quando o plano estiver em um arquivo, mantenha no fim dele uma seção de progresso, para que
outra sessão consiga retomar de onde esta parou:

```
## Progresso

- [x] 1. Docker de desenvolvimento — confirmada em 2026-10-01
- [ ] 2. Base do app — entregue, aguardando conferência
- [ ] 3. Identidade visual
```

Marque `[x]` só depois da confirmação do dev, nunca ao terminar de escrever o código.

Ao fim da sessão, atualize também o `.claude/handoff.md` (onde paramos, pendências e decisões
novas), para que o trabalho possa continuar em outro computador.

## 8. Fim do plano

Confirmada a última etapa, faça um resumo curto: o que foi entregue, o que ficou pendente ou
sem verificação, e os próximos passos que dependem do dev (commit, PR, deploy). Lembre que a
`master` é protegida e publica: o trabalho vai por branch e Pull Request, e o merge dispara o
deploy.
