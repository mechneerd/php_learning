# 06 — Database Design

Engine: **SQLite** for local dev (current `.env`, FTS5 available) — schema is portable to MySQL (`TEXT`/`INTEGER`/`DATETIME` via Laravel schema builder; no engine-specific SQL except the FTS virtual table, which has a LIKE-based fallback).

Conventions:
- Tables `snake_case`, plural. All tables have `id` PK and `timestamps` unless noted.
- Soft deletes only on content tables where history matters (`lessons`, `exercises`, `quiz_questions`, `flashcards`).
- Enumerations are `string` columns validated by PHP backed enums (`app/Enums/*`).
- `json` columns are cast arrays in models.
- **Provenance block** (below) is attached to every row that can carry book/AI origin.
- Foreign keys enforced (`foreign_keys = ON` in SQLite; Laravel default).

## Provenance block

```php
source            ENUM('book','ai','hybrid')      // who produced the row
book_id           FK nullable
chapter_id        FK nullable
section_id        FK nullable
page_printed_from / page_printed_to   nullable int
page_pdf_from     / page_pdf_to       nullable int
status            ENUM('draft','in_review','published','archived') default 'draft'
ai_model          nullable string
ai_generated_at   nullable datetime
ai_prompt_version nullable string
reviewed_by       FK users nullable
reviewed_at       nullable datetime
is_outdated       boolean default false
```

Applied to: `lessons`, `lesson_blocks`, `code_examples`, `diagrams`, `exercises`, `quiz_questions`, `flashcards`, `interview_questions`, `error_patterns`, `projects`, `terms`.

---

## Module A — Identity

### `users` (extend existing)
| Column | Type | Notes |
|---|---|---|
| id, name, email, password, remember_token, timestamps | existing | Fortify |
| email_verified_at | nullable | existing |
| **role** | string default `learner` | `learner` \| `admin`, `User::Role` enum |
| last_seen_at | nullable datetime | streak display |

Indexes: unique `email`.

## Module B — Source import

### `books`
| Column | Type |
|---|---|
| id, title, subtitle, author, edition, isbn, publisher, published_year | |
| source_pdf_document_id | FK `pdf_documents` nullable |
| status | `draft\|active\|archived` |
| timestamps | |

### `pdf_documents`
| Column | Type | Notes |
|---|---|---|
| id, book_id | FK | |
| original_name, path, sha256 | string, sha256 unique |
| page_count | int | |
| status | `uploaded\|extracting\|extracted\|failed` | |
| error | text nullable | |
| timestamps | | |

### `pdf_pages`
| Column | Type | Notes |
|---|---|---|
| id, pdf_document_id | FK | |
| page_pdf | int | 1-based PDF page |
| page_printed | int nullable | detected trailing number |
| text | longText | sanitized |
| word_count | int | |
| extraction_quality | decimal(4,2) | heuristic 0–1 |
| needs_review | boolean | |
| timestamps | | unique(`pdf_document_id`,`page_pdf`) |

### `import_jobs`
| Column | Type |
|---|---|
| id, pdf_document_id | FK |
| type | `extract\|detect_chapters\|detect_sections\|generate\|publish` |
| status | `queued\|running\|done\|failed` |
| payload, result | json |
| attempts | int default 0 |
| error | text nullable |
| started_at, finished_at, created_at, updated_at | |

### `page_review_flags`
| Column | Type |
|---|---|
| id, pdf_page_id | FK |
| reason | `low_text\|no_page_number\|heading_mismatch\|image_only` |
| note | text nullable |
| resolved_at, resolved_by (FK users), timestamps | |

## Module C — Content tree

### `stages`
| Column | Type | Notes |
|---|---|---|
| id, number (unique), slug, name, subtitle | | 12 rows |
| source | `book\|ai\|mixed` | Stage 0 = `ai` |
| description | text | |
| gate_rules | json | [{concept, min_level}] or [{quiz, min_score}] |
| ord | int | |

### `chapters`
| Column | Type |
|---|---|
| id, book_id | FK |
| number | int, unique per book |
| title, slug | |
| page_printed_from/to, page_pdf_from/to | int nullable |
| ord | int |
| timestamps | |

### `sections`
| Column | Type |
|---|---|
| id, chapter_id | FK |
| parent_id | FK `sections` nullable (self) |
| number | string nullable e.g. `3.2.1` |
| title, slug | |
| level | tinyint (1 = chapter section, 2 = subsection) |
| page_printed_from/to, page_pdf_from/to | int nullable |
| ord | int |
| timestamps | | unique(`chapter_id`,`slug`)

### `lessons`
| Column | Type | Notes |
|---|---|---|
| id, stage_id | FK | |
| chapter_id | FK nullable | provenance convenience |
| section_id | FK nullable | null for AI foundation lessons |
| title, slug, summary | slug unique | |
| status | `draft\|in_review\|published\|archived` | |
| est_minutes | int default 10 | |
| ord | int | order inside section/stage |
| source, page_* , ai_*, reviewed_*, is_outdated | | provenance block |
| soft deletes | | |
| timestamps | | |

### `lesson_blocks`
| Column | Type | Notes |
|---|---|---|
| id, lesson_id | FK | cascade |
| ord | int | unique per lesson |
| type | enum | `heading,paragraph,bullets,callout,code,output,table,diagram,book_quote,modern_panel,prereq_list,exercise_ref,quiz_ref,card_refs,interview_ref,tabs,image` |
| payload | json | type-specific (e.g. `{"markdown": "…"}`, `{"lang":"php","code":"…"}`) |
| source | `book\|ai` | |
| page_printed_from/to, page_pdf_from/to | int nullable | |
| created_at, updated_at | | |

## Module D — Concepts & graph

### `concepts`
| Column | Type |
|---|---|
| id, slug unique, name | |
| definition | text |
| skill_domain | string (one of 16) |
| granularity | `topic\|concept\|detail` |
| is_core | boolean (gates) |
| source, status | provenance-lite |
| timestamps | |

### `lesson_concepts`
`lesson_id` FK, `concept_id` FK, `role` (`core`,`prereq`,`built_on`) — composite PK.

### `concept_prerequisites`
`concept_id` FK, `prereq_concept_id` FK, `weight` int default 1, `source` (`book`,`ai`,`manual`) — unique pair. Cycle prevention validated in service + test.

## Module E — Learning assets

### `code_examples`
| Column | Type |
|---|---|
| id, lesson_id | FK |
| concept_id | FK nullable |
| listing_ref | string nullable (`04.19`) |
| tier | tinyint 1–4 |
| title, code (text), expected_output (text nullable) |
| explanation, syntax_notes, common_mistake | text nullable |
| external_ref | string nullable (repo path) |
| ord | int |
| provenance block (source/page_*/status/…) | |
| timestamps | |

### `diagrams`
| Column | Type |
|---|---|
| id, lesson_id | FK nullable |
| kind | `flowchart,class,sequence,state,er,gantt,other` |
| title, mermaid_source | |
| source | `book_figure\|ai` |
| figure_ref | string nullable (`9-2`) |
| page_pdf | int nullable |
| status, timestamps | |

### `terms` + `lesson_terms`
`terms`: id, slug unique, term, definition, timestamps.
`lesson_terms`: lesson_id, term_id (pivot).

## Module F — Practice

### `exercises`
| Column | Type |
|---|---|
| id, lesson_id | FK |
| concept_id | FK nullable |
| type | `write,complete,predict_output,find_error,fix,mcq,explain,problem` |
| difficulty | `easy,medium,hard` |
| prompt, starter_code, solution_code, explanation, expected_answer (json) |
| ord | int |
| provenance block | |
| soft deletes, timestamps | |

### `exercise_hints`
id, exercise_id FK, level (1–3), text. Unique(exercise_id, level).

### `exercise_tests`
id, exercise_id FK, ord, type (`assert_output`,`assert_contains`,`assert_regex`,`static_check`,`phpunit`), payload json, weight int default 1.

### `exercise_attempts`
id, user_id FK, exercise_id FK, code text, result (`correct`,`incorrect`,`partial`,`error`), hints_used int, duration_sec int, test_results json, created_at.
Index: (user_id, exercise_id, created_at).

### `quiz_questions`
id, lesson_id FK, concept_id FK nullable, type (`mcq,true_false,output,completion,debug,short_answer`), stem text, explanation text, difficulty, ord, provenance block, soft deletes, timestamps.

### `quiz_options`
id, question_id FK, text, is_correct bool, feedback text nullable, ord.

### `quiz_attempts`
id, user_id FK, lesson_id FK, score decimal(5,2), total int, correct_count int, weak_concepts json, duration_sec int, created_at. Index (user_id, lesson_id).

### `quiz_answers`
id, attempt_id FK, question_id FK, option_id FK nullable, answer_text text nullable, is_correct bool.

### `recall_attempts`
id, user_id FK, concept_id FK, lesson_id FK nullable, answer text, score decimal(5,2), evaluation json (rubric points, missing_points), created_at.

### `flashcards`
id, concept_id FK nullable, lesson_id FK nullable, card_type (`definition,syntax,difference,rule,mistake,interview`), front, back, ord, provenance block, soft deletes, timestamps.

### `flashcard_reviews`
id, user_id FK, card_id FK, grade (`hard,ok,easy`), interval_days int, ease decimal(4,2), next_review_at datetime, reviewed_at datetime. Unique(user_id, card_id). Index (user_id, next_review_at).

### `interview_questions`
id, concept_id FK nullable, topic string, question, model_answer, follow_ups json, difficulty, ord, provenance block, timestamps.

### `error_patterns`
id, slug unique, name, category (`parse,type,undefined,method,fatal,exception,runtime`), symptom, cause, identify_steps json, fix_steps json, prevent_steps json, practice_ref string nullable, provenance block, timestamps.

### `projects` / `project_tasks`
projects: id, stage_id FK nullable, level (`beginner,intermediate,advanced,capstone`), slug unique, title, brief, requirements json, solution_ref, ord, status, timestamps.
project_tasks: id, project_id FK, ord, brief, concept_ids json, solution_hint text nullable.

## Module G — User progress

### `lesson_progress`
id, user_id FK, lesson_id FK, state (`opened,read,explained,practiced,reviewed`), active_seconds int default 0, opened_at, last_at. Unique(user_id, lesson_id).

### `concept_mastery`
id, user_id FK, concept_id FK, level (`unseen,learning,practicing,comfortable,mastered`), evidence json `{read,recall,easy,medium,debug,mixed}` timestamps, mastered_at nullable. Unique(user_id, concept_id). Index (user_id, level).

### `skill_progress`
id, user_id FK, skill_domain string, level, mastered_count int default 0, comfortable_count int default 0, updated_at. Unique(user_id, skill_domain).

### `review_items`
id, user_id FK, item_type (`card,concept,exercise,error,lesson`), item_id, concept_id FK nullable, reason string, due_at datetime, status (`due,done,snoozed`). Index (user_id, due_at, status).

### `notes`
id, user_id FK, noteable_type string, noteable_id, kind (`note,highlight,bookmark,important`), body text, timestamps. Index (user_id, noteable_type, noteable_id).

### `tags` / `taggables`
tags: id, name, slug unique, timestamps.
taggables: tag_id FK, taggable_type, taggable_id. Unique(tag_id, taggable_type, taggable_id).

## Module H — AI

### `ai_conversations`
id, user_id FK, mode (`tutor,teach,quiz,exercise`), lesson_id FK nullable, context json, created_at, updated_at.

### `ai_messages`
id, conversation_id FK, role (`system,user,assistant`), content text, tokens_in, tokens_out, meta json, created_at. Index (conversation_id, created_at).

### `ai_generations`
id, entity_type, entity_id nullable, stage string (`lesson,exercise,quiz,cards,diagram,outdated`), prompt_version, model, status (`queued,running,done,failed`), tokens_in, tokens_out, cost decimal(10,4), error text nullable, input_hash string (idempotency), created_at. Unique(stage, prompt_version, input_hash).

### `content_versions`
id, entity_type, entity_id, payload json, actor (`ai,admin,system`), diff_summary text, created_at. Index (entity_type, entity_id).

## Module I — Search

### `search_index` (SQLite FTS5 virtual table)
`entity_type, entity_id, title, body` — external-content-less virtual table, rebuilt by `search:reindex` command. Fallback query path uses `LIKE` when FTS5 unavailable (MySQL).

---

## Index summary (non-unique)

| Table | Index |
|---|---|
| pdf_pages | (pdf_document_id, page_printed) |
| lesson_blocks | (lesson_id, ord) |
| lessons | (stage_id, ord), (status, source) |
| code_examples / exercises / quiz_questions / flashcards | (lesson_id, ord), (status) |
| exercise_attempts | (user_id, exercise_id, created_at) |
| quiz_attempts | (user_id, lesson_id) |
| flashcard_reviews | (user_id, next_review_at) |
| review_items | (user_id, status, due_at) |
| concept_mastery | (user_id, level) |
| ai_messages | (conversation_id, created_at) |
| notes | (user_id, noteable_type, noteable_id) |

## Deletion rules

- Deleting a `lessons` row soft-deletes; `lesson_blocks`, `code_examples`, `diagrams` cascade on hard delete.
- Deleting a `chapter` is blocked while published lessons exist (policy check).
- `pdf_documents` deletion keeps `books` intact (pages cascade).
- User deletion: Fortify flow; progress/attempts cascade; notes cascade.
