---
title: What it was like building my first project 100% with AI - Part 2
description: "The execution phase with Claude Code: one step at a time, a check before moving on, and the rules born from the mistakes along the way."
date: 2026-11-16
tags: [AI, Claude Code, Workflow]
translation_key: primeiro-projeto-com-ia-parte-2
---

In [Part 1](/en/blog/first-project-with-ai-part-1), I talked about the architecture and how the coding plan, split into steps, was the project's starting point. With the plan written and reviewed, it was time to turn the document into code. And it was exactly in this phase that I realized that AI, however capable it is, needs a well-defined working rhythm so it doesn't get out of hand.

## Execution

The first decision was the most important one: the AI executes one step of the plan, stops and waits for my check. Only after a clear confirmation does it move on to the next one. It may seem slow, but think about the manual development flow: nobody reviews a month of work all at once. When several steps are chained together, the diff gets too big to review, and a mistake made at the beginning contaminates everything that comes after. Stopping at each step keeps the diff small, makes mistakes cheap to fix, and keeps the dev in control of where the project is going. On top of that, the AI doesn't get ahead on the next step's work, even when it seems obvious, because a file that only works with what comes next makes the current step impossible to check.

To check without having to read all the code again, I asked for every delivery to end with a short report, always in the same format:

```markdown
**Step N of T — <title>: done**

What was done:
- <files created or changed and what each one does>

How it works:
<short explanation, starting with an analogy>

What I verified:
- <commands run and the result; what could NOT be verified and why>

How to check:
<commands or steps, with the expected result>

Is everything correct? Can I move on to step N+1?
```

Note that the most valuable part of the report is "What I verified". In it, the AI reports which tests, analyses and checks it ran, and above all what was left unverified. If nothing was tested, the title changes from "done" to "files written, not tested". This avoids a common trap in co-development with AI: believing something works just because the AI said it does.

**Tip: ask the AI to be faithful to the real state of the delivery. An honest "I couldn't test it" is worth more than an optimistic "done".**

Another point that had to become a written rule was what counts as approval. A question, a comment or an "I committed it" doesn't approve the step; only a clear yes does. If I point out a mistake, the fix happens within the same step, which only closes when I confirm. Along with that, I decided that git stays mine: the AI can check `git status` and `git diff`, and even write the commit message when I ask, but it never commits, pushes or opens a Pull Request. That way, versioning becomes my moment of final review, and I also avoid spending tokens on operations I do in seconds.

Some steps, when their turn came, turned out to be too big to check all at once. In those cases, the AI proposes splitting them into substeps (5a, 5b, 5c...) and records the split in the plan before writing any file. That's why the list shown in Part 1 has so many letters: the Home became four substeps, and SEO, six. It was also during execution that the episode of the 57 files to build the blog happened, which brought the MVP mindset into the project's guidelines.

## Mistakes become rules

What surprised me most in this phase was how every stumble ended up becoming a line in the project's guidelines, and didn't happen again. A corrupted accented character in `cloud-init.yaml`, when pasting the file, made the first VM boot without its configuration; the steps were done by hand, and the file started being written in ASCII only. Long chained commands were hard to approve safely, so the rule became using small, single-purpose commands. When I reject a command, the AI doesn't insist or try to work around it another way: it records in the report what was left unrun. And deleting files stayed with me, with the AI only listing the command for me to run.

The documentation also kept pace with the execution. Each subject in the project got a short document in `.claude/arch-explain/`, such as deploy, i18n and Giscus, updated only when a change invalidates what's written. This way, the documentation doesn't bloat, and the AI reads the subject's document before touching it, instead of assuming. At first, there was also a session handoff file to continue the work on another computer, but over time it lost its purpose and the pending items moved to the README.

After repeating these rules over several sessions, I gathered everything into a Claude Code skill, `modo-codando` ("coding mode"), which finds the plan, refuses to run if it has no steps, executes one step at a time, delivers the report and waits for confirmation. Today I use this skill in all my projects, including [Wecker](https://github.com/dancarvalhodev/wecker) and [Céu de Hoje](https://ceudehoje.danielcarvalho.dev), my first Laravel project.

In the end, AI sped up development a lot, but what made the project work wasn't speed, it was rhythm: a plan before coding, one step at a time, real checks, and rules born from your own mistakes. The AI writes the code, but the one who decides the direction is still the dev.
