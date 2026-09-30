# 13 — Tech Stack & Conventions

## Stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 13 (`laravel/framework ^13.17`) | already installed |
| PHP | ^8.3 (local 8.5 CLI) | modern syntax: enums, readonly, constructor promotion |
| DB | SQLite (dev, FTS5) → MySQL-ready | `.env` switch only |
| Queue/cache/session | `database` driver | jobs for import + AI pipeline |
| Frontend | **Livewire 4 + Flux UI + Blaze** + Tailwind | starter kit; no SPA, no Inertia |
| Build | Vite (`npm run build`), `@vite` in `partials.head` | |
| Editor | CodeMirror 6 (PHP mode) | added Phase 4 (npm) |
| Diagrams | Mermaid (CDN or npm, lazy-loaded) | |
| Code highlight | highlight.js or Prism (PHP grammar) | |
| Auth | Fortify | login/register/reset/2FA/profile |
| Tests | Pest 5 + `RefreshDatabase` | feature-first |
| Quality | Pint (laravel preset), Larastan level 7 | `composer test` |
| CI | GitHub Actions `tests.yml` (exists) | same gates |

## Conventions (already in use — follow them)

- Pint `laravel` preset; run `composer lint` before commit.
- Larastan level 7 on `app/`, `database/`, `routes/`, `config/`.
- Tests: Pest functions, `describe`-free flat style as in starter kit, `actingAs($user)`.
- Route views via `Route::view` for static pages; Livewire components for interactive.
- Blade components in `resources/views/components`, Flux tags `flux:*`.
- Localization via `__()` for UI strings (starter kit pattern).
- No JS framework; Alpine via Livewire only.

## Folder additions

```
app/
  Enums/                 ContentStatus, ProvenanceSource, ExerciseType, Difficulty,
                         MasteryLevel, BlockType, SkillDomain, UserRole ...
  Models/Concerns/       HasProvenance, HasStatus, HasSlug
  Services/
    Import/              PdfTextExtractor, TocParser, SectionDetector, BookImporter
    Content/             LessonBuilder, BlockPayload, SearchIndexer
    Learning/            MasteryEvaluator, ReviewScheduler, SkillAggregator
    Ai/                  (see 10-ai-architecture.md)
  Livewire/
    Admin/               BookUpload, ImportJobs, LessonEditor, ReviewQueue, ConceptGraph
    Learn/               LessonView, PracticeRunner, QuizRunner, FlashcardSession,
                         RevisionQueue, TutorChat, PathView, SearchPage
  Policies/              LessonPolicy, ExercisePolicy, BookPolicy, ...
  Http/Requests/         StoreNoteRequest, StoreBookRequest, SubmitAttemptRequest ...
  Console/Commands/      book:import, book:detect-toc, search:reindex, content:generate
  Jobs/                  ExtractPdfJob, DetectStructureJob, GenerateLessonJob, ...
database/
  seeders/               BookTocSeeder, StageSeeder, ConceptGraphSeeder, DemoContentSeeder
  factories/             one per model used in tests
resources/views/
  lessons/blocks/        paragraph, code, diagram, callout, table, tabs, modern_panel, quote
  livewire/learn/        ...
  livewire/admin/        ...
```

## Testing strategy

| Level | Scope | Tooling |
|---|---|---|
| Unit | `MasteryEvaluator`, `TocParser`, `PdfTextExtractor`, hint ladder, SM-2 scheduler | Pest unit |
| Feature | routes, policies, Livewire component flows, import command, seeds | Pest feature + `RefreshDatabase` |
| Static | Larastan level 7, Pint | `composer test` |
| Security | no-exec grep test, admin gate tests, upload validation | feature tests |

Rule: **every phase lands with its tests; a phase is not done until `composer test` is green.**

## Performance

- Lesson payload: blocks fetched with `select` on JSON keys, cached per lesson version (`Cache::remember("lesson:{id}:{updated_at}")`).
- Search: FTS5 with `search:reindex` on publish.
- Mermaid + highlight assets lazy-loaded per page that needs them.
- No N+1: eager loads specified in each Livewire component (`with([...])`).

## Environment notes (this machine)

- WAMP PHP 8.5 CLI at `C:\wamp64\bin\php\php8.5.0\php.exe`; Xdebug DLL warning is harmless but slows runs.
- PHPStan must run with `--memory-limit=1G` (128M default crashed it) — set in `composer.json` `types:check`.
- Poppler `pdftotext`/`pdfinfo` at `C:\poppler\Library\bin` — path configurable via `config/import.php::pdftotext_binary` with auto-detection.
