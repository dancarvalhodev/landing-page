---
title: What it was like building my first project 100% with AI - Part 1
description: "How I used Claude Code to plan this site's architecture: a step-by-step plan, a review before coding, and guidelines that became project rules."
date: 2026-10-15
tags: [AI, Claude Code, Architecture]
translation_key: primeiro-projeto-com-ia-parte-1
---

Using AI in the development workflow is inevitable, whether for planning, reviewing, testing, or even as support for deployment. Building a project from scratch, like this landing page with a blog, while following a few specific criteria (such as keeping the blog itself in an easy-to-maintain PHP structure), could be an architecture challenge. And that was the first step.

## Architecture

This is the most complex part, because it holds up the whole project and also sets the development time. After more than 5 years using PHP, I ended up adopting my own pattern for organizing folders and content, which was my starting point. However, instead of describing and explaining the whole pattern, I took a different approach: I asked the AI (in this case, Claude Code) to analyze the repository of [Wecker](https://github.com/dancarvalhodev/wecker), a project of mine that is a Docker wrapper similar to Portainer, and suggest a coding plan.

Note that the coding plan is very important before actually starting to code. Think about the manual development flow, without AI: you need to define the steps to code (first the database, then the backend, then the frontend). With AI it's no different. On top of that, the plan works as a checkpoint for how much of the project is already done. This way, both the AI and the dev can track progress, correct course, make changes and, above all, document the choices, freeing the mind to focus on other details.

**Tip: for the plan to be coherent, always ask the AI to dig through the code or the references before writing it. That way it doesn't assume: it relies on facts.**

With the plan written, it was time for the review. This step matters because, even today, AI tends to overcomplicate some implementations, creating very complex solutions for very simple situations. During this process, which means actually reading and understanding the generated document, I noticed that some points could be trimmed or simplified. This is the moment when you can adjust course at no cost.

The generated plan was split into steps, each with a checkpoint (when it was executed, whether it is waiting for review). These steps, in turn, were grouped into large implementation blocks, at the AI's own suggestion, and that became a rule in the project's CLAUDE.md.

```markdown
A step is only marked `[x]` after the dev's confirmation.

- [x] 1. Development Docker — confirmed on 2026-10-01
- [x] 2. App foundation — confirmed on 2026-10-02
- [x] 3. Visual identity — "Sunset" direction; confirmed on 2026-10-02
- [x] 4a. i18n — confirmed on 2026-10-02
- [x] 4b. Content loading (MVP) — confirmed on 2026-10-02
- [x] 4c. Real copy — confirmed on 2026-10-03
- [x] 5a. Home — sections with real content — confirmed on 2026-10-03
- [x] 5b. Home — project filter — confirmed on 2026-10-03
- [x] 5c. Home — light/dark theme — confirmed on 2026-10-03
- [x] 5d. Home — subtle animations — confirmed on 2026-10-03
- [x] 5. Home (all substeps) — confirmed on 2026-10-03
- [x] 6a. Blog — list and post — confirmed on 2026-10-03
- [x] 6c. Blog — tags — confirmed on 2026-10-03
- [x] 6d. Blog — search — confirmed on 2026-10-03
- [x] 6e. Blog — code highlighting — confirmed on 2026-10-03
- [x] 6f. Blog — Giscus — confirmed on 2026-10-03 (theme look pending a deploy with HTTPS; fixed and checked in production on 2026-10-10, see `.claude/arch-explain/giscus.md`)
- [x] 6. Blog (all substeps) — confirmed on 2026-10-03
- [x] 7a. SEO — basic metadata — confirmed on 2026-10-04
- [x] 7b. SEO — hreflang — confirmed on 2026-10-04
- [x] 7c. SEO — Open Graph and Twitter Card — confirmed on 2026-10-04
- [x] 7d. SEO — JSON-LD Person — confirmed on 2026-10-04
- [x] 7e. SEO — sitemap.xml and robots.txt — confirmed on 2026-10-04
- [x] 7f. SEO — share image — confirmed on 2026-10-04
- [x] 7. SEO (all substeps) — confirmed on 2026-10-04
- [x] 8a. Professional descriptions — experience — confirmed on 2026-10-05
- [x] 8b. Professional descriptions — headline, introduction, "about" and skills — confirmed on 2026-10-05
- [x] 8c. Professional descriptions — projects — confirmed on 2026-10-05
- [x] 8. Professional descriptions (all substeps) — confirmed on 2026-10-05
- [x] 9a. Production images — PHP image — confirmed on 2026-10-06
- [x] 9b. Production images — Caddy image — confirmed on 2026-10-06
- [x] 9c. Production images — CSS version in production — confirmed on 2026-10-06
- [x] 9d. Production images — docker-compose.prod.yml and check — confirmed on 2026-10-06
- [x] 9. Production images (all substeps) — confirmed on 2026-10-06
- [x] 10a. Infra — edge Caddy and shared network (preview dropped) — confirmed on 2026-10-07
- [x] 10b. Infra — prepare the VM: cloud-init.yaml and README with the OCI + DNS walkthrough (former 10b and 10c, merged) — confirmed on 2026-10-09, with the VM created and the walkthrough run by the dev. The cloud-init was not applied on the first VM (an accented character in the file got corrupted when pasting); the steps were done by hand and the file was fixed to ASCII only
- [x] 10. Infra (all substeps) — confirmed on 2026-10-09
- [x] 11a. CI/CD — ci.yml (style, analysis and tests) — confirmed on 2026-10-09
- [x] 11b. CI/CD — deploy.yml, secrets, Pull Request to master and first deploy — confirmed on 2026-10-09, with the site live. The custom Giscus theme did not work in production; the dev opened an issue on GitHub and fixed it on 2026-10-10
- [x] 11c. CI/CD — master protection and rollback — confirmed on 2026-10-09
- [x] 11. CI/CD (all substeps) — confirmed on 2026-10-09
- [x] 12. Documentation — confirmed on 2026-10-09 (done in one go, without substeps: it is two files, README.md and CLAUDE.md)
- [x] 13. Documentation review — confirmed on 2026-10-09. Survey done on 2026-10-09 (reading every document against the code); the dev decided to apply the twelve fixes at once, without substeps. The plan was not reviewed
- [x] 14. Project spec — dropped on 2026-10-09, by the dev's decision: it would be redundant with the existing documentation
```

While executing the steps, it's normal for new ideas to come up to improve the process and the plan itself. One of the most relevant came after a step in which Claude generated an incredible 57 files to build the blog, when something simple would have done the job. To correct course, I asked it to always take an MVP view of the implementation, especially given the project's scope. It added this to the plan and to CLAUDE.md, and it became a project guideline. More details on the execution flow are coming in Part 2.

Another very important detail in creating the plan, and any documentation, was defining writing guidelines. They set the tone, the level of formality, the amount of jargon and the target audience of the texts. My personal touch is always to put readability and clarity ahead of grammatical rigor, so I included that criterion too. The result was clear, objective texts of the right length, which cuts the time spent reading and reviewing the documentation, one of the main causes of delay in co-development with AI.

In Part 2, which is yet to be published, I'll cover the execution phase and how it connects directly with the architecture and planning phase.
