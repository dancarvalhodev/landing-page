# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Explorar o código e rodar comandos

- **CodeGraph primeiro.** O repositório é indexado (`.codegraph/` na raiz). Para entender ou localizar código, use `codegraph_explore` (MCP, passando `projectPath`) ou `codegraph explore "<símbolos ou pergunta>"` antes de qualquer `grep`, `find`, `sed` ou leitura de arquivo.
- **Evite comandos compostos.** Nada de `cd ... && sed ...`, `cd ... && grep ...` ou cadeias com `&&`, `;` e `|` juntando ações diferentes. Prefira um comando por chamada, com caminhos absolutos, e as ferramentas dedicadas (Read, Edit, Write) para ler e alterar arquivos.

## Antes de começar

Leia `.claude/handoff.md`: onde o trabalho parou, como retomar em outro computador e como o dev
quer trabalhar (uma etapa por vez, MVP, analogias, git feito por ele). O plano e o progresso estão
em `.claude/plans/arquitetura-landing-page.md`. Este arquivo ganha os comandos e a arquitetura
completos na etapa 11.
