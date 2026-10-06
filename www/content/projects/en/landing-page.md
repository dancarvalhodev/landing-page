---
title: Landing Page
summary: This website, a portfolio with a blog in Portuguese and English, built in PHP with no database.
category: Web
technologies: [PHP, Slim, Twig, PHP-DI, Tailwind CSS, Caddy, Docker]
repository: https://github.com/dancarvalhodev/landing-page
order: 4
translation_key: landing-page
---

A portfolio with a blog, in Portuguese and English, that reads all its content from Markdown
files, with no database. The blog has tags, search, code highlighting and comments through
Giscus, and SEO was planned from the start, with hreflang, Open Graph, JSON-LD and a sitemap. The
back end is PHP 8.5 with Slim 4, PHP-DI and Twig, with Tailwind CSS for styling, and the whole
environment runs on Caddy and Docker Compose.

The site is hosted on Oracle Cloud, with automatic publishing through GitHub Actions.
