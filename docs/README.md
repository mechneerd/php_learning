# PHP Learning Platform — Documentation Index

An interactive learning platform that turns the book **PHP 8 Objects, Patterns, and Practice (Matt Zandstra, 6th ed., Apress 2021)** into a structured, practice-driven course inside a Laravel application.

The PDF is the **source of truth**. AI-generated material always sits beside it, badged and editable, never silently mixed in.

## Document map

| # | File | What it answers |
|---|------|-----------------|
| 01 | [project-overview.md](01-project-overview.md) | Vision, users, goals, constraints, glossary, status |
| 02 | [pdf-analysis.md](02-pdf-analysis.md) | What the PDF contains: inventory, chapters, page mapping, findings |
| 03 | [product-requirements.md](03-product-requirements.md) | Full requirement list with IDs, priorities, acceptance criteria |
| 04 | [learning-architecture.md](04-learning-architecture.md) | Stages, lesson template, modes, mastery rules, exercise/quiz/flashcard design |
| 05 | [knowledge-map.md](05-knowledge-map.md) | Concept prerequisite graph, skill domains, PHP→Laravel bridge |
| 06 | [database-design.md](06-database-design.md) | Every table: columns, types, indexes, enums, conventions |
| 07 | [erd.md](07-erd.md) | Mermaid ER diagrams by module |
| 08 | [application-screens.md](08-application-screens.md) | Screen inventory, routes, layout, components, responsive rules |
| 09 | [flows.md](09-flows.md) | Mermaid flowcharts: import, learning, exercise, quiz, tutor, mastery, review |
| 10 | [ai-architecture.md](10-ai-architecture.md) | Content pipeline, prompt templates, tutor policy, guardrails, cost control |
| 11 | [learning-workflow.md](11-learning-workflow.md) | The 10-step learning loop and how the UI enforces it |
| 12 | [security-and-sandbox.md](12-security-and-sandbox.md) | Auth, authorization, code-execution isolation spec |
| 13 | [tech-stack.md](13-tech-stack.md) | Stack, conventions, folder layout, quality gates |
| 14 | [mvp-scope.md](14-mvp-scope.md) | What is in/out of the MVP and why |
| 15 | [implementation-plan.md](15-implementation-plan.md) | Phase-by-phase build plan: migrations, models, services, routes, views, tests, seeders |
| 16 | [16-operations-runbook.md](16-operations-runbook.md) | Backup, restore, deploy, quality gates, monitoring, rollback |

## How to read this

1. New to the project → `01` → `02` → `04` → `15`.
2. Working on the database → `06` + `07`.
3. Working on AI features → `10` (+ `09` for the flow).
4. Working on UI → `08` + `11`.

## Ground rules (non-negotiable)

1. **Book first.** Every content row carries provenance: `source = book|ai`, chapter, section, printed + PDF page numbers.
2. **No invented attribution.** AI content is badged `AI-generated` and admin-approved before publishing.
3. **Outdated content is surfaced, not hidden.** `BOOK TEACHES → MODERN PHP → WHY` panels.
4. **Practice requires writing code.** Mastery needs evidence, not page views.
5. **Never execute learner PHP in the web process.** Isolated sandbox only (see `12`).
6. **Ship in phases with tests.** `composer test` (Pint + PHPStan + Pest) must pass before a phase is done.
