---
title: Wecker
summary: Interface web inspirada no Portainer para controlar contêineres Docker de forma visual.
category: Web
technologies: [PHP, Slim, Twig, PHP-DI, PostgreSQL, Redis, Docker]
repository: https://github.com/dancarvalhodev/wecker
order: 1
translation_key: wecker
---

Painel com login que lista os contêineres da máquina e permite iniciar, parar, criar e ver os
logs de cada um, conversando com a API do Docker. O back-end é PHP com Slim 4, PHP-DI e Twig,
com PostgreSQL e Redis, e todo o ambiente sobe com Docker Compose.

O projeto segue em evolução: estão nos planos testes automatizados, status de saúde dos
contêineres, console web e logs em tempo real.
