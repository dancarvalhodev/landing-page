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
dividi-la antes de começar, em vez de entregar um bloco que ninguém consegue revisar.

## 3. Antes da primeira etapa

Leia o plano inteiro e diga, em poucas linhas, quantas etapas há e qual será executada agora.

Para retomar um plano em andamento, veja a seção "Progresso" do arquivo do plano (abaixo) e
confira o estado real do repositório. Continue da primeira etapa não confirmada, sem refazer as
anteriores.

## 4. Executar uma etapa

- **Fique dentro da etapa.** Não adiante trabalho da próxima, mesmo que pareça óbvio ou rápido.
  Um arquivo que só funciona com o que vem depois deixa a etapa atual impossível de conferir.
- **Comente o mínimo.** Código e arquivos de configuração saem sem comentários, a não ser quando
  existe um porquê que o próprio código não mostra (uma restrição externa, uma armadilha, a
  origem de um trecho copiado) — e, nesse caso, uma linha basta. Não escreva comentários que
  narram o que a linha faz, que explicam a ferramenta ou que anunciam etapas futuras: as
  explicações vão no relatório da etapa, onde o dev as lê uma vez, e não no arquivo, onde viram
  ruído em toda leitura seguinte.
- **Verifique o que fez.** Rode o build, os testes ou o que a etapa permitir e leia o resultado.
  Uma recusa a um comando que juntava várias ações não é uma proibição de testar: rode a
  verificação em comandos separados.
- **Prefira comandos pequenos e de propósito único.** Um comando que cria branch, gera arquivo de
  configuração e faz build ao mesmo tempo é difícil de aprovar; três comandos separados não são.
- **Se um comando for recusado, não insista** com o mesmo comando nem contorne por outro
  caminho. Registre no relatório o que ficou sem rodar.
- **Desvios do plano são decisão do dev.** Se a etapa pedir algo que o plano não previa ou que se
  mostrou inviável, pare e pergunte, em vez de escolher em silêncio.
- **Não faça commit, push nem abra PR** a menos que a etapa ou o dev peçam.

## 5. Entregar a etapa

Ao terminar, encerre o turno com um relatório neste formato:

```
**Etapa N de T — <título>: concluída**

O que foi feito:
- <arquivos criados ou alterados e o que cada um faz>

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

Depois da pergunta, **pare**. Não comece a etapa seguinte no mesmo turno e não a prepare "para
adiantar".

## 6. Esperar a confirmação

Só avance com um sim claro: "está correto", "pode seguir", "ok, próxima".

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

## 8. Fim do plano

Confirmada a última etapa, faça um resumo curto: o que foi entregue, o que ficou pendente ou
sem verificação, e os próximos passos que dependem do dev (commit, PR, deploy).
