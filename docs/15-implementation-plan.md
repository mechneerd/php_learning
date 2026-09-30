# 15 — Implementation Plan

Phased delivery. **Every phase ends with:** migrations → models → services → policies/requests → routes → views → seeders → tests → `composer test` green → short status report.

Status legend: ⬜ not started · 🔄 in progress · ✅ done

---

## Phase 0 — Foundation & conventions ✅

**Why:** stable base, role model, quality gates working on this machine.

| Item | Detail |
|---|---|
| Migrations | `add_role_and_last_seen_to_users_table` (`role` string default `learner`, `last_seen_at`) |
| Models | `User` + `UserRole` enum (`learner`, `admin`); helper `User::isAdmin()` |
| Config | `config/import.php` (pdftotext/pdfinfo binary paths + auto-detect), `config/ai.php` (provider=key=stub) |
| Middleware | `EnsureUserIsAdmin` |
| Routes | admin group skeleton `/admin` (view stub) behind role middleware |
| Views | extend `layouts.app.sidebar` nav groups (Learn / Practice / Reinforce / Build) |
| Tooling | `composer.json` `types:check` → `phpstan analyse --memory-limit=1G` |
| Tests | `UserRoleTest` (role default, admin gate), baseline suite green |

**DoD:** `composer test` green; admin gate returns 403 for learners.

---

## Phase 1 — Content schema + PDF import 🔄

**Why:** the book must exist in the DB with faithful provenance before anything renders.

### Migrations
1. `create_books_table` — title, subtitle, author, edition, isbn, publisher, published_year, source_pdf_document_id (nullable FK), status, timestamps
2. `create_pdf_documents_table` — book_id FK, original_name, path, sha256 unique, page_count, status, error, timestamps
3. `create_pdf_pages_table` — pdf_document_id FK, page_pdf, page_printed, text, word_count, extraction_quality, needs_review, timestamps; unique(pdf_document_id, page_pdf); index(page_printed)
4. `create_import_jobs_table` — pdf_document_id FK, type, status, payload/result json, attempts, error, started_at, finished_at
5. `create_page_review_flags_table` — pdf_page_id FK, reason, note, resolved_at, resolved_by
6. `create_stages_table` — number unique, slug, name, subtitle, source, description, gate_rules json, ord
7. `create_chapters_table` — book_id FK, number, title, slug, page ranges, ord; unique(book_id, number)
8. `create_sections_table` — chapter_id FK, parent_id self-FK, number, title, slug, level, page ranges, ord; unique(chapter_id, slug)
9. `create_lessons_table` — stage_id FK, chapter_id FK nullable, section_id FK nullable, title, slug unique, summary, status, est_minutes, ord, provenance block, soft deletes
10. `create_lesson_blocks_table` — lesson_id FK cascade, ord, type, payload json, source, page ranges; unique(lesson_id, ord)

### Enums (`app/Enums`)
`UserRole`, `ContentStatus`, `ProvenanceSource`, `StageSource`, `BlockType`, `ImportJobType`, `ImportJobStatus`, `PdfDocumentStatus`, `PageFlagReason`, `BookStatus`

### Models + relationships
`Book` (hasMany chapters, hasOne pdfDocument), `PdfDocument`, `PdfPage`, `ImportJob`, `PageReviewFlag`, `Stage` (hasMany lessons), `Chapter` (belongsTo book, hasMany sections/lessons, children sections), `Section` (belongsTo chapter, parent/children, hasMany lessons), `Lesson` (belongsTo stage/chapter/section, hasMany blocks ordered, `HasProvenance`), `LessonBlock`
`Models/Concerns/HasProvenance` — casts + `scopePublished()` + `citation()` accessor ("Ch. 3 · pp. 29–33 · PDF 49–53").

### Services (`app/Services/Import`)
| Service | Responsibility |
|---|---|
| `PdfTextExtractor` | shells `pdfinfo` + `pdftotext -layout -enc UTF-8` (Symfony Process), splits on `\f`, returns pages[] |
| `TextSanitizer` | strip `\x07`, `\x08`, U+FFFD runs, normalize NBSP, collapse 3+ blank lines |
| `PrintedPageDetector` | last standalone number on a page → `page_printed` |
| `TocParser` | parse TOC region (315 entries; indent → level; dot-leader page ref) → `TocEntry[]` |
| `ChapterSectionCreator` | TOC entries → chapters/sections rows, resolve printed→PDF pages by scanning `pdf_pages` |
| `BookImporter` | orchestrates: extract → store → parse → create → report; returns `ImportResult` |
| `ImportReport` | DTO: pages stored, chapters, sections, flags, warnings |

### Jobs
`ExtractPdfJob` (queue), `DetectStructureJob` — with `ImportJob` bookkeeping (attempts/status).

### Commands
- `book:import {pdf} {--book=}` — extract + store pages (sync or queued with `--queue`)
- `book:detect {book}` — TOC parse → chapters/sections
- `book:report {book}` — print tree + flag counts

### Policies / Requests
`BookPolicy` (admin only for store/update), `StoreBookRequest` (mimes:pdf, max 514400 KB→50 MB, unique sha check)

### Routes
```
POST /admin/books                 (admin) store upload
GET  /admin/books                  index + status
GET  /admin/books/{book}/inspect   tree + flags
POST /admin/books/{book}/detect    run structure detection
```

### Views / Livewire
`livewire/admin/book-upload.blade.php` (dropzone + job progress), `livewire/admin/book-index.blade.php`

### Seeders
- `StageSeeder` — 12 stages with `gate_rules`
- `BookTocSeeder` — the book metadata + 22 chapters + full TOC sections (315 entries with printed/PDF pages, from analysis) so structure exists without re-parsing

### Tests
| File | Covers |
|---|---|
| `Feature/Admin/BookUploadTest` | non-admin 403, invalid mime rejected, valid pdf creates document + job |
| `Feature/Import/BookImportCommandTest` | fixture PDF (tiny generated) → pdf_pages rows, printed page detection, sanitizer |
| `Unit/Import/TocParserTest` | sample TOC text → entries, levels, page refs |
| `Unit/Import/PrintedPageDetectorTest` | trailing number, missing number, number inside code |
| `Unit/Import/TextSanitizerTest` | control chars, FFFD, nbsp |
| `Feature/Import/ChapterSectionCreatorTest` | TOC fixture → chapters/sections + page mapping |
| `Feature/Content/LessonProvenanceTest` | `citation()` output, scopes |

**DoD:** uploading the real book produces 22 chapters + ~98 sections with correct page refs; flags any pages failing quality checks.

---

## Phase 2 — Lesson rendering & reading

**Why:** the actual reading experience; validates the block model.

### Migrations
`create_code_examples_table` (lesson_id, concept_id, listing_ref, tier, title, code, expected_output, explanation, syntax_notes, common_mistake, external_ref, ord, provenance), `create_diagrams_table` (lesson_id, kind, title, mermaid_source, source, figure_ref, page_pdf, status)

### Enums
`BlockType` finalized (19 types), `CodeTier`, `DiagramKind`

### Services
`Content/LessonBuilder` (assembles lesson payload + blocks in one query, cached), `Content/BlockValidator` (payload schema per block type), `MermaidSanitizer`

### Models
`CodeExample`, `Diagram` + casts

### Routes / Livewire
```
GET /lessons/{lesson:slug}          Learn\LessonView (3-pane)
```
`LessonView` properties: `$lesson`, `$blocks`, `$mode`, `$railTab`; listeners for `focus` time tracking → `lesson_progress`.

### Views
- `layouts/learn.blade.php` (3-pane wrapper inside app sidebar layout)
- `lessons/blocks/{type}.blade.php` × 19
- components: `source-badge`, `citation-line`, `status-badge`, `mastery-meter`
- Mermaid + highlight assets (`@push('scripts')`)

### Seeders
`DemoLessonSeeder` — Ch 3 "Object Basics" restructured into ~20 lessons with hand-verified blocks (mix of book quotes + AI scaffolding), code examples from listings 03.01–03.76 subset, 6 diagrams.

### Tests
`Feature/Lessons/RenderLessonTest` (all block types render, citation shown, unpublished 404), `Unit/Content/BlockValidatorTest`, `Feature/Lessons/NoAiCallOnViewTest` (fake AI client asserts 0 calls), `Feature/Lessons/TimeTrackingTest`.

**DoD:** Ch 3 readable end-to-end on desktop + mobile widths.

---

## Phase 3 — Concepts, prerequisites, learning path

**Why:** the knowledge graph powers gating, skills and the tutor's context.

### Migrations
`create_concepts_table`, `create_lesson_concepts_table`, `create_concept_prerequisites_table`

### Services
`Learning/ConceptGraph` (descendants, ancestors, cycle detection), `Learning/GateEvaluator` (stage gates from `stages.gate_rules`)

### Routes / Livewire
`GET /path` → `Learn\PathView`; `GET /concepts/{concept:slug}` → concept page (can be part of PathView in MVP).

### Views
`livewire/learn/path.blade.php`: stage rail + Mermaid graph (nodes colored by `MasteryLevel`), gate panel with pass/fail chips.

### Seeders
`ConceptGraphSeeder` — ~120 concepts, lesson links for Stage 0 + Ch 3, prerequisite edges from `05-knowledge-map.md`.

### Tests
`Unit/Learning/ConceptGraphTest` (cycle detection rejects, ancestors correct), `Unit/Learning/GateEvaluatorTest`, `Feature/Path/PathViewTest` (renders, shows next recommendation).

---

## Phase 4 — Practice, quiz, flashcards

**Why:** the interactive core; writing and testing knowledge.

### Migrations
`exercises`, `exercise_hints`, `exercise_tests`, `exercise_attempts`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_answers`, `flashcards`, `flashcard_reviews`, `recall_attempts` (recall UI can land in Phase 6, table now)

### Enums
`ExerciseType`, `ExerciseDifficulty`, `AttemptResult`, `QuizQuestionType`, `CardType`, `HintGrade`

### Services
`Learning/HintLadder` (unlock rules), `Learning/ExerciseGrader` (test types: `assert_output`, `assert_contains`, `assert_regex`, `static_check`), `Learning/QuizScorer`, `Learning/CardScheduler` (SM-2 lite), `Learning/MasteryEvaluator` (evidence keys — introduced here, finalized Phase 5)

### Livewire
`Learn\PracticeRunner` (prompt, editor, hints, submit, results, history), `Learn\QuizRunner` (one-at-a-time), `Learn\FlashcardSession`

### Requests
`SubmitExerciseAttemptRequest`, `SubmitQuizAnswerRequest`

### Views
`livewire/learn/practice.blade.php`, `quiz.blade.php`, `flashcards.blade.php`, components `hint-ladder`, `test-result-row`, `quiz-progress`, `flashcard` + CodeMirror mount.

### Seeders
`ExerciseSeeder`, `QuizSeeder`, `FlashcardSeeder` for Stage 0 + Ch 3 (~40 / ~60 / ~80 rows, all `source = ai`).

### Tests
`Unit/Learning/HintLadderTest` (solution blocked before level 3 + failures), `Unit/Learning/ExerciseGraderTest`, `Unit/Learning/CardSchedulerTest`, `Feature/Practice/AttemptFlowTest`, `Feature/Quiz/QuizFlowTest` (order, no back, scoring, weak concepts), `Feature/Flashcards/ReviewScheduleTest`.

**DoD:** learner can complete the full loop on any Ch 3 lesson without AI.

---

## Phase 5 — Progress, mastery, skills, search

**Why:** measurement and orientation; the dashboard that keeps the learner honest.

### Migrations
`lesson_progress`, `concept_mastery`, `skill_progress`, `review_items`, `notes`, `tags`, `taggables`

### Services
`Learning/MasteryEvaluator` (promote/demote + evidence validation), `Learning/ReviewScheduler` (due items builder), `Learning/SkillAggregator` (16 domains), `Content/SearchIndexer` (FTS5 rebuild + incremental)

### Commands
`search:reindex {--entity=} {--all}`

### Livewire
`Learn\Dashboard`, `Learn\RevisionQueue`, `Learn\SkillsView`, `Learn\SearchPage`, `Learn\NotesList`

### Views
dashboard cards (per `08-application-screens.md`), `revision`, `skills`, `search`, `notes`; components `progress-ring`, `stage-chip`, `concept-chip`, `empty-state`.

### Tests
`Unit/Learning/MasteryEvaluatorTest` (evidence matrix, demotion, 30-day rule), `Unit/Learning/SkillAggregatorTest`, `Feature/Dashboard/DashboardTest`, `Feature/Revision/ReviewQueueTest`, `Feature/Search/SearchTest` (grouped results, reindex), `Feature/Notes/NotesTest`.

---

## Phase 6 — AI tutor + active recall

**Why:** teach-on-demand without creating dependency.

### Migrations
`ai_conversations`, `ai_messages`, `ai_generations`, `content_versions`

### Services (see `10-ai-architecture.md`)
`Ai\Contracts\AiClient`, `StubAiClient`, `OpenAiClient`, `ContextAssembler`, `PromptRunner`, `Prompts\TutorPrompt`, `Prompts\RecallScorer`, `Learning\RecallEvaluator`

### Livewire
`Learn\TutorChat` (rail widget + `/tutor` page), `Learn\RecallPrompt` (explain-it input + rubric feedback)

### Config
`config/ai.php` (provider/model/budgets), rate limiter definitions in `AppServiceProvider`

### Views
chat bubbles with source badges, quick-action chips, usage footer; recall card component.

### Tests
`Unit/Ai/ContextAssemblerTest`, `Unit/Ai/HintPolicyTest` (solution refused below ladder position), `Feature/Tutor/ChatFlowTest` (rate limit, persistence, budget), `Feature/Tutor/NoDependencyPromptsTest` (transfer task emitted after solution), `Feature/Recall/RecallScoringTest` (rubric + missing points).

---

## Phase 7 — Content pipeline at scale

**Why:** fill all 12 stages without hand-writing; keep the human in the gate.

### Jobs
`ExtractConceptsJob`, `DerivePrerequisitesJob`, `GenerateLessonJob`, `GenerateCodeExamplesJob`, `GenerateDiagramJob`, `GenerateExercisesJob`, `GenerateQuizJob`, `GenerateCardsJob`, `DetectOutdatedJob`, `IndexContentJob`

### Services
`Ai\Generators\*` (one per job, DTO-validated), `Content\LessonBuilder` reuse, `Content\ModernPanelBuilder` (BOOK/MODERN/WHY)

### Commands
`content:generate {stage} {--dry-run}`, `content:approve {entity} {entity_id}`

### Livewire (admin)
`Admin\ReviewQueue` (diff view: AI draft vs current), `Admin\LessonEditor` (block JSON editor + preview), `Admin\ConceptGraphEditor`, `Admin\ImportJobs`

### Additional migrations
None structural (uses `content_versions`, `ai_generations`); possibly `lessons.ai_prompt_version`.

### Seeders
`Stage0FoundationSeeder` (12 lessons, full 20-block template), then generated batches per stage.

### Tests
`Unit/Ai/PromptValidationTest` (invalid JSON retry path), `Feature/Admin/ReviewQueueTest` (approve writes version + publishes), `Feature/Pipeline/IdempotencyTest` (same input_hash skips), `Feature/Pipeline/BudgetGuardTest`.

---

## Phase 8 — Sandboxed code execution

**Why:** real `Run` for exercises; only after the app is otherwise complete.

### New component
`runner/` — standalone PHP service (own composer project or Docker image): `POST /run` with signed token → executes under limits in `12-security-and-sandbox.md`.

### In-app
`CodeRun` table (attempt_id, stdout, stderr, exit_code, metrics, duration), `Services\Execution\SandboxClient` (HTTP), `Jobs\RunCodeJob`, updated `ExerciseGrader` to prefer live results when available.

### Views
Practice editor "Run" button + live result panel + timeout/OOM friendly messages.

### Tests
`Feature/Execution/SandboxClientTest` (with fake runner), `Unit/Security/BannedFunctionCheckTest`, `Feature/Execution/RateLimitTest`, plus runner repo's own limits tests.

---

## Phase 9 — Projects, interview, error library, Laravel bridge

### Migrations
`projects`, `project_tasks`, `interview_questions`, `error_patterns`

### Livewire
`Learn\Projects`, `Learn\ProjectDetail`, `Learn\InterviewMode`, `Learn\ErrorLibrary`

### Seeders
project ladder (4 beginner, 4 intermediate, 4 advanced, 1 capstone), ~80 interview questions, 10 error patterns.

### Views
project brief/task checklist, interview card flow, error pattern page (6-step flow), Laravel bridge panel partial reused in lessons.

### Tests
project unlock gating, interview self-grade → review item, error pattern rendering.

---

## Phase 10 — Polish, ops, QA

- Admin analytics (coverage per stage, exercise pass rates, AI spend chart)
- Mobile pass across all learner screens (bottom sheet rail)
- Performance: cache lesson payloads, eager-load audits, `php artisan optimize`
- Accessibility: keyboard navigation, focus states, contrast
- Backup/restore runbook, `README.md` for the repo, deploy notes
- Full regression: `composer test` + manual script of the MVP acceptance checklist

---

## Cross-phase rules

1. **No AI calls in read paths** — enforced by test from Phase 2 onward.
2. **Provenance everywhere** — any new content table gets the block from `06-database-design.md`.
3. **Enums over magic strings** — every status/type column has a backed enum + cast.
4. **Policies for mutations** — no inline `if (!auth()->user()->isAdmin())`.
5. **Seeders are fixtures for tests** — reusable, deterministic (no randomness).
6. **Report per phase** — migrations, models, services, routes, views, tests, seeders + `composer test` output before starting the next phase.
