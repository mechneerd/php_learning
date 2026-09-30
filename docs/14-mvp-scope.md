# 14 — MVP Scope

Goal of the MVP: **prove the entire learning loop on one chapter** (Stage 0 + Chapter 3 "Object Basics") before scaling content generation.

## In scope (P0)

| Area | Included |
|---|---|
| Auth & roles | Fortify login/register, `admin` role gate |
| Import | PDF upload → text extraction → page rows → TOC-driven chapters/sections with page refs |
| Content | `lessons` + `lesson_blocks` renderer (all block types), source badges, citations, Mermaid, syntax highlight |
| Structure | stages (12), concepts, prerequisites, learning path screen |
| Practice | exercises + hint ladder + attempts + static/expected-output grading (no live execution) |
| Quiz | MCQ/true-false/output, one-at-a-time, report, weak-concept queue |
| Flashcards | session with SM-2-lite scheduling |
| Progress | lesson progress, mastery evidence, skill domains, review queue, dashboard |
| Search | FTS5 global search |
| Tutor | context-aware chat with hint-ladder policy (Stub client works without key) |
| Admin | upload, lesson block editor, review queue (approve AI content), concept graph |
| Content shipped | Stage 0 (12 AI lessons) + Ch 3 (20 lessons) seed |

## Out of scope (P1/P2)

- Live code execution sandbox (Phase 8)
- Full-book content generation for all 12 stages (Phase 7, incremental)
- Projects, interview mode, error library, Debug lab (Phase 9)
- PHP→Laravel bridge panels (Phase 9)
- Book figure image extraction/rendering (Phase 7)
- Companion repo auto-import (Phase 7)
- Notes/bookmarks UI polish, analytics dashboards (Phase 5/10)
- Multi-book support beyond schema readiness

## MVP acceptance checklist

- [ ] Admin can upload the PDF and see chapters/sections with correct printed + PDF page numbers.
- [ ] Learner can open any published lesson and read all block types with the citation line.
- [ ] Lesson view performs **zero AI API calls** (test asserts).
- [ ] Every content row shows `📖 book` or `🤖 ai` badge correctly.
- [ ] Concept graph renders with prerequisite and "used later" edges.
- [ ] Exercise flow: attempt → hints 1-3 → solution, all recorded.
- [ ] Quiz flow produces score + weak concepts + queued revision.
- [ ] Flashcard session schedules the next review.
- [ ] Mastery only reaches `learning` from reading; `mastered` requires 6 evidence keys (tests).
- [ ] Dashboard shows stage progress, due reviews, weak concepts, skill domains.
- [ ] Search returns grouped results for "array", "interface", "constructor".
- [ ] Admin review queue can publish an AI lesson with an audit version row.
- [ ] `composer test` green (Pint + Larastan + Pest), no exec-family calls in `app/`.

## MVP timeline (phases 0–6)

| Phase | Effort | Deliverable |
|---|---|---|
| 0 Foundation | 0.5 d | roles, config, green baseline |
| 1 Content schema + import | 2–3 d | PDF → DB tree, commands, admin upload |
| 2 Lesson rendering | 2 d | 3-pane lesson, blocks, badges |
| 3 Concepts & path | 1–2 d | graph + `/path` |
| 4 Practice & quiz & cards | 3–4 d | exercise/quiz/card engines |
| 5 Progress & search | 2 d | mastery, skills, dashboard, FTS |
| 6 Tutor + recall | 2–3 d | Pipeline B, hint policy |
