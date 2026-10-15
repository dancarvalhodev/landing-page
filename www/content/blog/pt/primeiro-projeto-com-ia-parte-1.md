---
title: Como foi construir o meu primeiro projeto 100% com IA - Parte 1
description: "Como usei o Claude Code para planejar a arquitetura deste site: um plano em etapas, revisão antes de codar e diretrizes que viraram regras do projeto."
date: 2026-10-15
tags: [IA, Claude Code, Arquitetura]
translation_key: primeiro-projeto-com-ia-parte-1
---

É inevitável usar IA no fluxo de desenvolvimento, seja no planejamento, na revisão, nos testes ou até como apoio no deploy. Desenvolver um projeto do zero, como esta landing page com blog, seguindo alguns critérios específicos (como manter o próprio blog numa estrutura de fácil manutenção em PHP), poderia ser um desafio de arquitetura. E esse foi o primeiro passo.

## Arquitetura

É a parte mais complexa, pois é ela que sustenta todo o projeto e também define o tempo de desenvolvimento. Depois de mais de 5 anos usando PHP, acabei adotando um padrão próprio de organização de pastas e conteúdo, que foi o meu ponto de partida. Porém, em vez de descrever e explicar todo o padrão, pensei diferente: pedi para a IA (no caso, o Claude Code) analisar o repositório do [Wecker](https://github.com/dancarvalhodev/wecker), um projeto meu que é um wrapper de Docker parecido com o Portainer, e sugerir um plano de codificação.

Note que o plano de codificação é muito importante antes de sair programando de fato. Pense no fluxo manual de desenvolvimento, sem IA: é preciso definir os passos para codificar (primeiro o banco de dados, depois o backend, depois o frontend). Com a IA não é diferente. Além disso, o plano serve como ponto de controle do quanto o projeto já está concluído. Assim, a IA e o próprio dev conseguem acompanhar o progresso, corrigir o curso, fazer mudanças e, principalmente, documentar as escolhas, liberando a mente para focar em outros detalhes.

**Dica: para que o plano seja coeso, sempre peça para a IA vasculhar o código ou as referências antes de escrevê-lo. Assim, ela não supõe: se baseia em fatos.**

Com o plano escrito, chegou o momento da revisão. Esse passo é importante porque, ainda hoje, a IA tende a complicar demais algumas implementações, criando soluções muito complexas para situações muito simples. Durante esse processo, que é realmente ler e entender o documento gerado, percebi que alguns pontos poderiam ser enxugados ou simplificados. É nesse momento que dá para ajustar o curso sem prejuízo.

O plano gerado foi dividido em etapas, cada uma com um ponto de controle (quando foi executada, se está aguardando conferência). Essas etapas, por sua vez, foram organizadas em blocos grandes de implementação, por sugestão da própria IA, e isso virou um critério no CLAUDE.md do projeto.

```markdown
Uma etapa só é marcada `[x]` depois da confirmação do dev.

- [x] 1. Docker de desenvolvimento — confirmada em 2026-10-01
- [x] 2. Base do app — confirmada em 2026-10-02
- [x] 3. Identidade visual — direção "Pôr do sol"; confirmada em 2026-10-02
- [x] 4a. i18n — confirmada em 2026-10-02
- [x] 4b. Leitura de conteúdo (MVP) — confirmada em 2026-10-02
- [x] 4c. Textos reais — confirmada em 2026-10-03
- [x] 5a. Home — seções com conteúdo real — confirmada em 2026-10-03
- [x] 5b. Home — filtro de projetos — confirmada em 2026-10-03
- [x] 5c. Home — tema claro/escuro — confirmada em 2026-10-03
- [x] 5d. Home — animações sutis — confirmada em 2026-10-03
- [x] 5. Home (todas as subetapas) — confirmada em 2026-10-03
- [x] 6a. Blog — lista e artigo — confirmada em 2026-10-03
- [x] 6c. Blog — tags — confirmada em 2026-10-03
- [x] 6d. Blog — busca — confirmada em 2026-10-03
- [x] 6e. Blog — realce de código — confirmada em 2026-10-03
- [x] 6f. Blog — Giscus — confirmada em 2026-10-03 (visual do tema pendente de deploy com HTTPS; corrigido e conferido em produção em 2026-10-10, ver `.claude/arch-explain/giscus.md`)
- [x] 6. Blog (todas as subetapas) — confirmada em 2026-10-03
- [x] 7a. SEO — metadados básicos — confirmada em 2026-10-04
- [x] 7b. SEO — hreflang — confirmada em 2026-10-04
- [x] 7c. SEO — Open Graph e Twitter Card — confirmada em 2026-10-04
- [x] 7d. SEO — JSON-LD Person — confirmada em 2026-10-04
- [x] 7e. SEO — sitemap.xml e robots.txt — confirmada em 2026-10-04
- [x] 7f. SEO — imagem de compartilhamento — confirmada em 2026-10-04
- [x] 7. SEO (todas as subetapas) — confirmada em 2026-10-04
- [x] 8a. Descrições profissionais — experiências — confirmada em 2026-10-05
- [x] 8b. Descrições profissionais — chamada, apresentação, "sobre" e habilidades — confirmada em 2026-10-05
- [x] 8c. Descrições profissionais — projetos — confirmada em 2026-10-05
- [x] 8. Descrições profissionais (todas as subetapas) — confirmada em 2026-10-05
- [x] 9a. Imagens de produção — imagem PHP — confirmada em 2026-10-06
- [x] 9b. Imagens de produção — imagem Caddy — confirmada em 2026-10-06
- [x] 9c. Imagens de produção — versão do CSS em produção — confirmada em 2026-10-06
- [x] 9d. Imagens de produção — docker-compose.prod.yml e conferência — confirmada em 2026-10-06
- [x] 9. Imagens de produção (todas as subetapas) — confirmada em 2026-10-06
- [x] 10a. Infra — Caddy de borda e rede compartilhada (prévia retirada) — confirmada em 2026-10-07
- [x] 10b. Infra — preparar a VM: cloud-init.yaml e README com o passo a passo OCI + DNS (antigas 10b e 10c, juntadas) — confirmada em 2026-10-09, com a VM criada e o roteiro executado pelo dev. O cloud-init não foi aplicado na primeira VM (um acento no arquivo foi corrompido ao colar); os passos foram feitos à mão e o arquivo corrigido para só ASCII
- [x] 10. Infra (todas as subetapas) — confirmada em 2026-10-09
- [x] 11a. CI/CD — ci.yml (estilo, análise e testes) — confirmada em 2026-10-09
- [x] 11b. CI/CD — deploy.yml, secrets, Pull Request para a master e primeiro deploy — confirmada em 2026-10-09, com o site no ar. O tema customizado do Giscus não funcionou em produção; o dev abriu uma issue no GitHub e corrigiu em 2026-10-10
- [x] 11c. CI/CD — proteção da master e rollback — confirmada em 2026-10-09
- [x] 11. CI/CD (todas as subetapas) — confirmada em 2026-10-09
- [x] 12. Documentação — confirmada em 2026-10-09 (feita de uma vez, sem subetapas: são dois arquivos, README.md e CLAUDE.md)
- [x] 13. Revisão da documentação — confirmada em 2026-10-09. Levantamento feito em 2026-10-09 (leitura de todos os documentos contra o código); o dev decidiu aplicar as doze correções de uma vez, sem subetapas. O plano não foi revisado
- [x] 14. Spec do projeto — retirada em 2026-10-09, por decisão do dev: seria redundante com a documentação existente
```

Durante a execução das etapas, é normal surgirem novas ideias para melhorar o processo e o próprio plano. Uma das mais relevantes veio depois de uma etapa em que o Claude gerou incríveis 57 arquivos para criar o blog, sendo que algo simples já resolveria. Para corrigir o curso, pedi que ele sempre tivesse um olhar de MVP na implementação, até pelo escopo do projeto. Ele adicionou isso ao plano e ao CLAUDE.md, e isso virou uma diretriz do projeto. Mais detalhes do fluxo de execução ficam para a Parte 2.

Outro detalhe importantíssimo na criação do plano, e de qualquer documentação, foi definir diretrizes de texto. Elas orientaram o tom, o nível de formalidade, a quantidade de jargões e o público-alvo dos textos. Meu toque pessoal é sempre priorizar a legibilidade e a clareza em vez do rigor gramatical, então incluí esse critério também. O resultado foram textos claros, objetivos e do tamanho certo, o que reduz o tempo de leitura e revisão da documentação, um dos principais motivos de demora no codesenvolvimento com IA.

Na Parte 2, que ainda será publicada, vou abordar a fase de execução e como ela se conecta diretamente com a fase de arquitetura e plano.
