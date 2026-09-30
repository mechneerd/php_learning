# 03 — Product Requirements

Origin: the 33-section product brief. Each requirement has an ID, priority (MUST / SHOULD / LATER), and the phase that delivers it (see `15-implementation-plan.md`).

Legend: **P0** = MVP, **P1** = post-MVP but planned, **P2** = later.

## A. Source material & provenance

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-01 | Admin uploads a PDF; system extracts text per page preserving PDF + printed page numbers | P0 | 1 |
| FR-02 | Detect chapters and sections from TOC + headings; keep page ranges | P0 | 1 |
| FR-03 | Low-quality extraction pages are flagged for manual review | P1 | 1 |
| FR-04 | Every lesson retains book, chapter, section, page number(s), original topic | P0 | 1–2 |
| FR-05 | AI explanations are visually and structurally distinguishable from book material | P0 | 2 |
| FR-06 | Outdated material gets `BOOK TEACHES → MODERN PHP → WHY` panels; never silently replaced | P0 | 7 |
| FR-07 | Diagram/image references from the book are preserved alongside generated Mermaid | P1 | 7 |
| FR-08 | Companion source-code repo listings attach to code examples | P1 | 7 |

## B. Learning content model

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-10 | Hierarchy: Book → Chapter → Section → Lesson → {Concepts, Examples, Exercises, Quiz, Flashcards, Project} | P0 | 1–2 |
| FR-11 | Teaching order can differ from page order; prerequisites identified and enforced | P0 | 3 |
| FR-12 | Lesson follows the 20-part template (what/why/prereq/simple/analogy/visual/syntax/examples/mistakes/usage/practice/quiz/debug/task/interview/summary/cards) | P0 | 2,4 |
| FR-13 | Progressive disclosure: basic → example → deeper → advanced; no wall of text | P0 | 2 |
| FR-14 | Visual explanations: flowcharts, class/sequence/ER/auth diagrams, rendered with Mermaid | P0 | 2,7 |
| FR-15 | Code examples in 4 difficulty tiers with code, output, explanation, syntax notes, common mistake | P0 | 4 |
| FR-16 | Real-world backend examples (HRMS, payroll, auth, API, e-commerce) per major concept | P1 | 7 |
| FR-17 | Optional PHP → Laravel mapping panels | P2 | 9 |

## C. Practice & assessment

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-20 | 8 exercise types: write, complete, predict output, find error, fix, MCQ, explain, small problem | P0 | 4 |
| FR-21 | 3 difficulty levels; answers hidden behind Hint 1 → Hint 2 → explanation → solution | P0 | 4 |
| FR-22 | Attempt tracking: tries, correct, hints used, completion, time taken | P0 | 4 |
| FR-23 | Quiz per lesson: MCQ, true/false, code output, completion, debugging, short answer; one question at a time | P0 | 4 |
| FR-24 | Post-quiz report: score, right/wrong, weak concepts, revision recommendation | P0 | 4 |
| FR-25 | Quiz/lesson completion never equals mastery | P0 | 5 |
| FR-26 | Active recall: learner types an explanation; system scores against rubric and lists missing points | P1 | 6 |
| FR-27 | Flashcards with spaced revision (definition, syntax, differences, rules, mistakes, interview) | P0 | 4 |
| FR-28 | PHP code editor: write, run/submit, see output and errors, reset, hints, test cases | P0 (editor) / P1 (execution) | 4 / 8 |
| FR-29 | Execution only inside an isolated sandbox with CPU/mem/time/fs/network/process limits | P1 | 8 |
| FR-30 | Error-learning library: what happened → why → identify → fix → prevent → practice | P1 | 9 |
| FR-31 | Interview questions per topic, answer from understanding | P1 | 9 |

## D. Progress, mastery, maps

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-40 | Dashboard: overall, chapter, lesson, exercise, quiz, coding performance, weak/strong concepts, revision, projects | P0 | 5 |
| FR-41 | Skill tracking across 16 domains, separate from reading percentage | P0 | 5 |
| FR-42 | Knowledge map: "learn this first" / "used later" edges, per concept | P0 | 3 |
| FR-43 | Mastery requires evidence: read + explain + easy + medium + debug + mixed → learning/practicing/comfortable/mastered | P0 | 5 |
| FR-44 | Spaced revision queue (cards, weak concepts, past mistakes) | P0 | 5 |

## E. AI

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-50 | Offline pipeline: PDF → extraction → chapters → sections → concepts → prerequisites → lessons → examples → diagrams → exercises → quiz → flashcards → review → publish | P1 | 7 |
| FR-51 | Generated content stored in DB; never call AI on lesson open | P0 (principle) | all |
| FR-52 | AI tutor knows current chapter/lesson, learned concepts, exercise history, quiz results, weak areas, past mistakes | P1 | 6 |
| FR-53 | Tutor answers teaching questions (simpler, another example, why, real-world, Laravel use) | P1 | 6 |
| FR-54 | Tutor hint ladder: question → conceptual hint → syntax hint → implementation hint → solution | P0 policy | 6 |
| FR-55 | After a solution, tutor asks the learner to modify/recreate it | P1 | 6 |
| FR-56 | AI never positions itself as the replacement: prompts enforce teaching over answering | P0 | 6 |

## F. Projects & practice modes

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-60 | Learning modes: Read, Teach, Practice, Quiz, Revision, Interview, Project, Debug | P0 (Read/Practice/Quiz/Revision) / P1 (rest) | 2,4,9 |
| FR-61 | Project ladder: beginner (calculator, todo, student mgmt, salary) → intermediate (employee mgmt, auth, file CRUD, MySQL CRUD) → advanced (REST API, auth API, employee API, HRMS) → capstone | P1 | 9 |
| FR-62 | Projects reuse previously learned concepts and unlock by mastery | P1 | 9 |

## G. Application shell

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-70 | Navigation: Dashboard, Learning Path, Book, Lessons, Practice, Projects, Flashcards, Revision, Skills, Interview, AI Tutor | P0 | 2–5 |
| FR-71 | Lesson layout: desktop 3-pane (nav / content / context rail), mobile single column | P0 | 2 |
| FR-72 | Notes, bookmarks, important, highlight on lessons/concepts/code/exercises | P1 | 5 |
| FR-73 | Global search over lessons, concepts, examples, exercises, errors, cards, projects, interview questions | P0 | 5 |
| FR-74 | Admin panel: books, chapters, sections, lessons, concepts, examples, exercises, questions, answers, hints, projects, cards, diagrams, images, tags; AI content editable | P0 (subset) | 1,7 |
| FR-75 | Modern educational UI: syntax highlighting, tabs, expand/collapse, cards, progress, badges, Mermaid, interactive quizzes; readability over effects | P0 | 2 |

## H. Technical

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-80 | Laravel + PHP + MySQL (or SQLite dev) + Tailwind | P0 | 0 |
| FR-81 | Clean architecture: models, form requests, policies, services, jobs, events, resources, notifications, transactions, caching, tests — without over-engineering | P0 | all |
| FR-82 | Relational schema with proper FKs, indexes, no redundant tables | P0 | 1 |
| FR-83 | Tests for every phase (Pest), plus Pint + PHPStan gates | P0 | all |
| FR-84 | Phased delivery; report per phase before proceeding | P0 | all |

## Acceptance criteria (sample)

- **FR-43:** a concept only moves to `mastered` when `evidence` contains all six required keys; test asserts transitions and rejects partial evidence.
- **FR-21:** hint level > 1 requires previous level recorded; solution text never returned before level 3.
- **FR-04:** every published lesson block exposes `source`, `page_printed_from`, `chapter_id`; UI renders the citation line.
- **FR-51:** opening a lesson performs zero AI API calls (assert request count = 0 in test).
- **FR-29:** no code path in `app/` executes learner code (`proc_open`/`exec`/`eval` grep gate in CI).
