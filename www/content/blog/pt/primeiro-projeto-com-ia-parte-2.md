---
title: Como foi construir o meu primeiro projeto 100% com IA - Parte 2
description: "A fase de execução com o Claude Code: uma etapa por vez, conferência antes de seguir e as regras que nasceram dos erros no caminho."
date: 2026-11-16
tags: [IA, Claude Code, Fluxo de trabalho]
translation_key: primeiro-projeto-com-ia-parte-2
---

Na [Parte 1](/blog/primeiro-projeto-com-ia-parte-1), falei sobre a arquitetura e sobre como o plano de codificação, dividido em etapas, foi o ponto de partida do projeto. Com o plano escrito e revisado, chegou o momento de transformar o documento em código. E foi justamente nessa fase que percebi que a IA, por mais capaz que seja, precisa de um ritmo de trabalho bem definido para não sair do controle.

## Execução

A primeira decisão foi a mais importante: a IA executa uma etapa do plano, para e espera a minha conferência. Só depois de uma confirmação clara ela segue para a próxima. Pode parecer lento, mas pense no fluxo manual de desenvolvimento: ninguém revisa um mês de trabalho de uma vez só. Quando várias etapas são emendadas, o diff fica grande demais para revisar, e um erro cometido no começo contamina tudo o que vem depois. Parando em cada etapa, o diff fica pequeno, o erro fica barato de corrigir e o dev continua no controle do rumo do projeto. Além disso, a IA não adianta trabalho da etapa seguinte, mesmo que pareça óbvio, porque um arquivo que só funciona com o que vem depois deixa a etapa atual impossível de conferir.

Para conferir sem precisar ler todo o código novamente, pedi que cada entrega terminasse com um relatório curto, sempre no mesmo formato:

```markdown
**Etapa N de T — <título>: concluída**

O que foi feito:
- <arquivos criados ou alterados e o que cada um faz>

Como funciona:
<explicação curta, começando por uma analogia>

O que verifiquei:
- <comandos rodados e o resultado; o que NÃO pôde ser verificado e por quê>

Para conferir:
<comandos ou passos, com o resultado esperado>

Está tudo correto? Posso seguir para a etapa N+1?
```

Note que a parte mais valiosa do relatório é o "O que verifiquei". Nela, a IA informa quais testes, análises e verificações rodou, e principalmente o que ficou sem verificar. Se nada foi testado, o título muda de "concluída" para "arquivos escritos, não testados". Isso evita uma armadilha comum no codesenvolvimento com IA: acreditar que algo funciona só porque a IA disse que funciona.

**Dica: peça que a IA seja fiel ao estado real da entrega. Um "não consegui testar" honesto vale mais que um "concluído" otimista.**

Outro ponto que precisou virar regra escrita foi o que conta como aprovação. Uma pergunta, um comentário ou um "commitei" não aprovam a etapa; só um sim claro. Se aponto um erro, a correção acontece dentro da mesma etapa, que só fecha quando eu confirmo. Junto com isso, decidi que o git continua sendo meu: a IA pode consultar o `git status` e o `git diff`, e até escrever a mensagem de commit quando peço, mas nunca faz commit, push ou Pull Request. Assim, versionar vira o meu momento de revisão final, e ainda evito gastar tokens com operações que faço em segundos.

Algumas etapas, quando chegou a vez delas, se mostraram grandes demais para conferir de uma vez. Nesses casos, a IA propõe dividi-las em subetapas (5a, 5b, 5c...) e registra a divisão no plano antes de escrever qualquer arquivo. É por isso que a lista mostrada na Parte 1 tem tantas letras: a Home virou quatro subetapas, e o SEO, seis. Foi também na execução que aconteceu o episódio dos 57 arquivos para criar o blog, que levou o olhar de MVP para as diretrizes do projeto.

## Os erros viram regras

O que mais me surpreendeu nessa fase foi como cada tropeço acabou virando uma linha nas diretrizes do projeto, e não voltou a acontecer. Um acento corrompido no `cloud-init.yaml`, ao colar o arquivo, fez a primeira VM subir sem a configuração; os passos foram feitos à mão, e o arquivo passou a ser escrito só em ASCII. Comandos longos e encadeados eram difíceis de aprovar com segurança, então a regra passou a ser usar comandos pequenos e de propósito único. Quando recuso um comando, a IA não insiste nem tenta contornar por outro caminho: registra no relatório o que ficou sem rodar. E apagar arquivos ficou comigo, com a IA apenas listando o comando para eu executar.

A documentação também acompanhou a execução. Cada assunto do projeto ganhou um documento curto em `.claude/arch-explain/`, como o deploy, o i18n e o Giscus, atualizado só quando uma mudança invalida o que está escrito. Dessa forma, a documentação não incha, e a IA lê o documento do assunto antes de mexer nele, em vez de supor. No começo, havia também um arquivo de passagem de sessão para continuar o trabalho em outro computador, mas, com o tempo, ele perdeu o sentido e as pendências foram para o README.

Depois de repetir essas regras em várias sessões, reuni tudo numa skill do Claude Code, a `modo-codando`, que localiza o plano, recusa a execução se ele não tiver etapas, executa uma etapa por vez, entrega o relatório e espera a confirmação. Hoje uso essa skill em todos os meus projetos, inclusive no [Wecker](https://github.com/dancarvalhodev/wecker) e no [Céu de Hoje](https://ceudehoje.danielcarvalho.dev), meu primeiro projeto em Laravel.

No fim, a IA acelerou muito o desenvolvimento, mas o que fez o projeto dar certo não foi a velocidade, e sim o ritmo: plano antes de codar, uma etapa por vez, conferência de verdade e regras que nascem dos próprios erros. A IA escreve o código, mas quem decide o rumo continua sendo o dev.
