# 04 — Learning Architecture

## 1. The 12 stages

Teaching order differs from book order where prerequisites demand it.

| Stage | Name | Source | Lessons (est.) | Gate to advance |
|---|---|---|---|---|
| 0 | PHP Foundations | **AI (not in book)** | 12 | pass foundation quiz + 10 exercises |
| 1 | Orientation: why design matters | Book Ch 1–2 | 2 | read + recall |
| 2 | Object Core | Book Ch 3–4 | 20 | mastery of core concepts (class, inheritance, interface, trait, exception) |
| 3 | Object Tools & Design | Book Ch 5–6 (+ Ch16 autoload pull-forward) | 10 | namespaces/autoload/attributes + design-principle recall |
| 4 | Pattern Principles | Book Ch 7–8 | 5 | composition-over-inheritance exercise |
| 5 | Creational Patterns | Book Ch 9 | 9 | DI exercise + quiz ≥ 80% |
| 6 | Structural Patterns | Book Ch 10 | 5 | one mixed structural exercise |
| 7 | Behavioral Patterns | Book Ch 11 | 8 | one mixed behavioral exercise |
| 8 | Architecture & Persistence | Book Ch 12–13 | 12 | layered mini-project task |
| 9 | Professional Practice | Book Ch 14–18 (+ Git basics pulled to Stage 0) | 8 | PHPUnit test written by learner |
| 10 | Build & Delivery | Book Ch 19–21 + MODERN panel | 5 | CI config exercise (modern track) |
| 11 | Synthesis & Capstone | Book Ch 22 + App. B + projects | 6 | capstone project + interview set |

**Stage 0 contents (AI-generated, badged):** how PHP runs; variables & types; strings & interpolation; conditions; loops; arrays (indexed/associative); functions & scope; superglobals & request lifecycle (GET/POST); sessions & cookies; files & include/require; errors/exceptions intro; PDO + SQL basics; Composer/CLI/Git primer.

## 2. Lesson template (the 20 blocks)

Every published lesson exposes these sections in order. Sections are rendered progressively — collapsed until requested where noted.

| # | Section | Block type(s) | Source | Disclosure |
|---|---|---|---|---|
| 1 | What will I learn? | `bullets` | ai | open |
| 2 | Why does this matter? | `paragraph` | ai | open |
| 3 | Prerequisites | `prereq_list` | ai (graph-derived) | open, links to concept pages |
| 4 | Simple explanation | `paragraph` | ai | open |
| 5 | Real-world analogy | `callout` (variant `analogy`) | ai | open |
| 6 | Visual explanation | `diagram` (Mermaid) | ai (figure_ref to book when applicable) | open |
| 7 | Syntax | `code` (tier 1) + `syntax_notes` | ai + book quote | open |
| 8 | Small PHP example | `code` (tier 1–2) | book listing (primary) | open |
| 9 | Line-by-line explanation | `table` | ai | collapsible |
| 10 | Output | `output` | book/verified | with code |
| 11 | More examples | `code` (tier 3 real-world, tier 4 professional) | ai + book | collapsible |
| 12 | Common mistakes | `callout` (variant `warning`) + `error_pattern` links | ai | open |
| 13 | Real-world usage | `paragraph` (backend scenario) | ai | collapsible |
| 14 | Practice | `exercise_refs` | ai | link to Practice mode |
| 15 | Quiz | `quiz_ref` | ai | link to Quiz mode |
| 16 | Debugging challenge | `exercise` (type `find_error`/`fix`) | ai | collapsible |
| 17 | Mini task | `exercise` (type `problem`) | ai | collapsible |
| 18 | Interview question | `interview_ref` | ai | collapsible |
| 19 | Summary | `bullets` | ai | open |
| 20 | Flashcards | `card_refs` | ai | link to flashcards |

Additional permitted blocks: `book_quote` (verbatim excerpt + citation), `modern_panel` (BOOK/MODERN/WHY), `tabs` (Book vs Modern comparison), `image` (book figure reference), `code_example` (linked row, not inline JSON).

## 3. Progressive disclosure rules

- Default viewport shows blocks 1–9 + summary. Blocks 10–18 collapsed behind `<details>`/Flux disclosure.
- **Tiered code:** tier 1 visible; tier 3–4 behind "Show real-world example".
- Never more than ~120 words without a break, code sample, list or diagram.
- "Teach me" mode replaces prose with a guided AI dialogue over the same blocks.

## 4. Modes

| Mode | Route suffix | Behavior |
|---|---|---|
| Read | `/lessons/{slug}` | template above |
| Teach | `?mode=teach` | AI tutor anchored to lesson, block-aware |
| Practice | `/lessons/{slug}/practice` | exercises only, editor, hint ladder |
| Quiz | `/lessons/{slug}/quiz` | one question at a time, no back-navigation after submit |
| Revision | `/revision` | due items only (cards, weak concepts, mistakes) |
| Interview | `/interview` | question → attempt → reveal model answer |
| Project | `/projects/{slug}` | brief, tasks, gated solution |
| Debug | `/lessons/{slug}/debug` | seeded broken code, find & fix |

## 5. Exercise design

| Type | Description | Auto-graded |
|---|---|---|
| `write` | produce code from a prompt | by test cases (sandbox) / static in MVP |
| `complete` | fill the blank(s) | tests |
| `predict_output` | guess output, no execution | stored answer compare |
| `find_error` | locate the bug (MCQ or marker) | stored answer |
| `fix` | repair broken code | tests |
| `mcq` | multiple choice | stored |
| `explain` | free text | rubric (AI or keyword rubric) |
| `problem` | small programming problem | tests |

Difficulty: `easy | medium | hard`.

Hint ladder per exercise (levels 1..3, then solution):

1. **Conceptual** — names the idea, shows no syntax.
2. **Syntax** — shows the construct/signature only.
3. **Implementation** — describes the steps in prose.
4. **Solution** — full code + explanation, only after ≥30s and all prior hints or 2 failed attempts.

Every attempt stores: `code`, `result`, `hints_used`, `duration_sec`, `test_results`.

## 6. Quiz design

- 4–6 questions per lesson, one at a time, instant feedback per answer, final report only.
- Types: `mcq`, `true_false`, `output`, `completion`, `debug`, `short_answer`.
- Report: score, correct/incorrect list, weak concepts (score < 60% per concept), recommended revision items queued automatically.
- Re-quiz allowed; best score retained for mastery evidence, latest score for analytics.

## 7. Flashcards

Types: `definition`, `syntax`, `difference`, `rule`, `mistake`, `interview`.
Scheduling (SM-2 lite): first exposure → `1d`, then grades:
- `hard` → interval × 1.2
- `ok` → interval × 2.5
- `easy` → interval × 4
Lapses reset to 1d. Stored in `flashcard_reviews` with `next_review_at`.

## 8. Mastery rules (evidence matrix)

States: `unseen → learning → practicing → comfortable → mastered`.

| Evidence key | How earned | Counts toward |
|---|---|---|
| `read` | lesson opened + ≥60s active + summary block viewed | learning |
| `recall` | recall attempt scored ≥ 70% by rubric | learning → practicing |
| `easy` | 1 easy exercise correct | practicing |
| `medium` | 1 medium exercise correct | practicing → comfortable |
| `debug` | 1 find_error/fix exercise correct | comfortable |
| `mixed` | 1 exercise tagged with ≥2 concepts correct | comfortable → mastered |

Rules:
- Reading alone caps at `learning`.
- `mastered` requires **all six** keys; any failed attempt > 30 days ago triggers `review_items` and demotes to `comfortable`.
- Concepts, not lessons, are mastered.

## 9. Skill domains (16)

`php_syntax, variables, data_types, conditions, loops, functions, arrays, strings, files, http, oop, exceptions, database, security, testing, modern_php`

Each `concept.skill_domain` maps into one; dashboard computes per-domain levels from concept mastery counts + exercise pass rate (never from read %).

## 10. Project ladder

| Level | Project | Reuses |
|---|---|---|
| Beginner | Calculator, Todo, Student management, Employee salary calculator | Stage 0–2 |
| Intermediate | Employee management (file CRUD → MySQL CRUD), Authentication system | Stage 2–3, 8 |
| Advanced | REST API, Authentication API, Employee API, HRMS backend | Stage 8–10 |
| Capstone | Professional backend with CI, tests, Composer package | all |

Projects unlock when ≥70% of their tagged concepts reach `comfortable`.

## 11. Error learning

`error_patterns` rows: `name, symptom, cause, identify_steps, fix_steps, prevent_steps, practice_ref`.
Learner-facing flow: what happened → why → how to identify → how to fix → how to prevent → practice problem.
Seeded set: parse error, TypeError, undefined variable, undefined array key, call to undefined method, fatal error, uncaught exception, division by zero, memory limit, CORS/headers already sent.
