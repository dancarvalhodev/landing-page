---
title: Landing Page
summary: Este site, um portfólio com blog em português e inglês, feito em PHP e sem banco de dados.
category: Web
technologies: [PHP, Slim, Twig, PHP-DI, Tailwind CSS, Caddy, Docker]
repository: https://github.com/dancarvalhodev/landing-page
order: 4
translation_key: landing-page
---

Portfólio com blog, em português e inglês, que lê todo o conteúdo de arquivos Markdown, sem banco
de dados. O blog tem tags, busca, realce de código e comentários pelo Giscus, e o SEO foi
planejado desde o início, com hreflang, Open Graph, JSON-LD e sitemap. O back-end é PHP 8.5 com
Slim 4, PHP-DI e Twig, com Tailwind CSS no estilo, e todo o ambiente sobe com Caddy e Docker
Compose.

O site está hospedado na Oracle Cloud, com publicação automática pelo GitHub Actions e uma prévia
de cada alteração antes de ir ao ar.
