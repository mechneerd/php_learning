# 01 — Project Overview

## Vision

A personal, AI-assisted learning platform that converts one excellent but practice-free book into a course that makes the learner **program**, not just read. The learner continuously writes, predicts, debugs, explains and recalls — while the book remains the verifiable source of truth for every concept.

## Primary user

- A **beginner backend developer** who knows a little PHP and wants to become capable of professional PHP + Laravel work.
- Must never be assumed to have strong programming fundamentals: every lesson starts from first principles.
- The Laravel application itself doubles as a **learning project**: its architecture must stay readable and conventional.

## Goals (definition of done for the whole product)

On completion the learner can:

1. Understand PHP fundamentals and write PHP without copying examples.
2. Debug PHP (parse errors, TypeErrors, undefined index, exceptions).
3. Understand and apply OOP: classes, interfaces, traits, exceptions, autoloading, reflection, attributes.
4. Reason about design: cohesion, coupling, polymorphism, encapsulation, UML.
5. Apply GoF patterns and know *why* (creational, structural, behavioral).
6. Apply enterprise/database patterns (Front Controller, Data Mapper, Unit of Work…).
7. Use Composer, PSR standards, Git, PHPUnit in real work.
8. Understand HTTP, sessions, security basics, databases.
9. Explain how Laravel maps onto every PHP concept learned.
10. Answer PHP interview questions from understanding, not memory.
11. Continue learning advanced PHP independently.

## Non-goals

- Not a PDF reader or a summarizer.
- Not a Laravel course (Core PHP is primary; Laravel mapping is an optional panel).
- Not a general LMS — one book, deeply integrated.
- Not a code-execution SaaS in v1 (sandbox arrives in a later phase, isolated).

## Constraints

| Constraint | Implication |
|---|---|
| Book is source of truth | Provenance columns on every content row; verbatim quotes kept short |
| Book has **no exercises/quizzes/flashcards** | Entire practice layer is AI-generated, badged, admin-gated |
| Book is PHP 8.0 / 2021 tooling | `BOOK/MODERN/WHY` overlay panels; `is_outdated` flag |
| Learner is a beginner | Stage 0 foundations (not in book), progressive disclosure, hint ladders |
| AI must not create dependency | Hint ladder policy; solutions only after graded hints; transfer tasks after solutions |
| Security | No in-process code execution ever; sandbox service later |
| Readable architecture | Conventional Laravel: models, form requests, policies, services, Livewire/Flux views, Pest tests |
| Ship incrementally | 11 phases, each with migrations → models → services → routes → views → tests → seeders |

## Glossary

| Term | Meaning |
|---|---|
| **Block** | Atomic unit of lesson content (`paragraph`, `code`, `diagram`, `callout`, `book_quote`, `modern_panel`…) stored as JSON with a `source` flag |
| **Provenance** | The `source/book/chapter/section/page_*` field set attached to generated content |
| **Concept** | A named, graph-linked idea (e.g. `interface`, `composite-pattern`) that appears in lessons, exercises, cards and skills |
| **Skill domain** | One of 16 tracking buckets (`oop`, `database`, `testing`, …) a concept belongs to |
| **Evidence** | Machine-checkable proof of learning (read + recall + easy + medium + debug + mixed) |
| **Mastery level** | `unseen → learning → practicing → comfortable → mastered` |
| **Hint ladder** | Graded help: conceptual → syntax → implementation → solution |
| **Stage** | One of 12 teaching stages (Stage 0 foundations … Stage 11 capstone) |
| **Mode** | Read / Teach / Practice / Quiz / Revision / Interview / Project / Debug |
| **Review item** | Anything due for spaced repetition (card, concept, weak skill) |
| **Pipeline A** | Offline AI content generation (PDF → published lessons) |
| **Pipeline B** | Runtime AI tutor conversation |

## Repository layout (planned)

```
app/
  Models/            Book, Chapter, Section, Lesson, LessonBlock, Concept, ...
  Models/Concerns/   HasProvenance, HasStatus
  Services/          Import/   (PdfTextExtractor, ChapterDetector, SectionDetector, BookImporter)
                      Content/ (LessonBuilder, BlockRenderer data layer)
                      Learning/(MasteryEvaluator, ReviewScheduler)
                      Ai/      (AiClient contract, StubAiClient, OpenAiClient, ContextAssembler)
  Livewire/          Admin/ (upload, review queue, editors), Learn/ (Lesson, Quiz, Flashcards, Tutor)
  Console/Commands/  book:import, book:detect, book:seed-toc, content:generate
database/
  migrations/        schema per 06-database-design.md
  seeders/           BookTocSeeder, StageSeeder, DemoLessonSeeder
  factories/         per model
resources/views/
  livewire/learn/    lesson, practice, quiz, flashcards, tutor
  livewire/admin/    import wizard, review queue
  lessons/           rendered lesson block partials
docs/                this folder
```

## Status

| Phase | State |
|---|---|
| Docs & architecture | ✅ this folder |
| Phase 0 — foundation & conventions | 🔄 in progress |
| Phase 1 — content schema + PDF import | ⬜ |
| Phases 2–10 | ⬜ (see `15-implementation-plan.md`) |
