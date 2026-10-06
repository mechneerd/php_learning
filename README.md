# PHP Learning Platform

An interactive, practice-driven learning platform that turns the book **PHP 8 Objects, Patterns, and Practice (Matt Zandstra, 6th ed., Apress)** into a structured course inside a Laravel application. The PDF is the source of truth: every content row carries provenance, AI-generated material is badged and admin-approved before learners see it, and learner code is only ever executed in the isolated runner service — never in the web process.

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13, PHP ≥ 8.3 |
| UI | Livewire 4 + Flux/Blade, Tailwind CSS 4 (Vite) |
| Auth | Fortify (roles: learner / admin) |
| Database | SQLite (dev; MySQL-ready schema), database sessions & queue |
| Code execution | Isolated `runner/` service (own limits: time, memory, output) |
| QA | Pint, PHPStan (Larastan), Pest 5, runner limit tests |

## Quick start

```bash
composer setup     # composer install, .env, key, migrate, npm install, npm run build
composer dev       # artisan dev: app + queue + vite
```

Open http://127.0.0.1:8000 and register, or build the full demo course (content derives from the PDF — keep it somewhere private, it is not committed):

```bash
php artisan book:import "C:/path/to/PHP 8 Objects, Patterns, and Practice (Matt Zandstra).pdf" --detect
php artisan db:seed                       # demo user: test@example.com / password
php artisan db:seed --class=StageSeeder   # 12 stages
php artisan db:seed --class=DemoLessonSeeder   # needs the book import above (chapter 3)
php artisan db:seed --class=ExerciseSeeder
php artisan db:seed --class=QuizSeeder
php artisan db:seed --class=FlashcardSeeder
php artisan db:seed --class=ConceptGraphSeeder
php artisan db:seed --class=InterviewQuestionSeeder
php artisan db:seed --class=ErrorPatternSeeder
php artisan db:seed --class=ProjectSeeder
```

Create an admin account:

```bash
php artisan tinker --execute="App\Models\User::factory()->admin()->create(['email' => 'admin@example.com'])"
```

## Commands

| Command | Purpose |
|---|---|
| `composer test` | **Quality gate**: config:clear → Pint → PHPStan → Pest |
| `composer lint` | Auto-fix code style (Pint) |
| `composer types:check` | PHPStan only |
| `composer test:runner` | Sandbox limit tests (19 checks, runs PHP in subprocess) |
| `composer ci:check` | Full CI suite (test + runner) |
| `npm run build` | Frontend production build |
| `php artisan optimize` | Cache config/events/routes/views (deploy) |
| `php artisan optimize:clear` | Clear the above (after config changes) |

## Quality gates (non-negotiable)

1. `composer test` must be green before any phase/task is done.
2. **No AI API calls on lesson read paths** (asserted by `NoAiCallOnViewTest`).
3. **No in-process execution of learner code** — only the isolated `runner/`.
4. Every content row keeps provenance (`source = book|ai`, chapter, pages).

## Repository layout

```
app/Livewire/Learn/     learner screens (dashboard, lessons, practice, quiz, …)
app/Livewire/Admin/     admin tools (books, review queue, editor, analytics)
app/Services/           domain services (Content, Learning, Ai, Runner)
database/migrations/    schema; database/seeders content; database/factories tests
resources/views/        Blade (livewire/, lessons/blocks/, partials/)
runner/                 isolated code executor + its tests
tests/                  Pest: Feature, Unit, Support
docs/                   full spec set — start at docs/README.md
```

## Documentation

Specs live in [`docs/`](docs/README.md): requirements (03), learning architecture (04), database design (06), screens (08), AI architecture (10), MVP scope + acceptance checklist (14), implementation plan (15), operations runbook (16).

## License

Private project; the source book is copyrighted and not included — only short cited excerpts are stored with page references.
