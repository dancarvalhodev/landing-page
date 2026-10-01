# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository state

This repository is a fresh scaffold for a landing page. As of the initial commit it contains only `README.md` (a title, no content) — there is no source code, package manifest, framework, build tooling, linter, or test setup yet.

Consequently there are no build, lint, or test commands to run, and no architecture to describe. Do not assume a stack (e.g. Next.js, Vite, Astro, plain HTML): confirm the choice with the user before scaffolding.

## Keeping this file current

Once a stack is chosen and code exists, replace the section above with:

- The actual commands for installing dependencies, running the dev server, building, linting, and running tests (including a single test).
- The high-level architecture: how pages/sections are composed, where styling and assets live, and how the site is built and deployed.
