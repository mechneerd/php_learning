# 08 — Application Screens

Framework: **Livewire 4 + Flux UI + Tailwind**, layouts already provided by the starter kit (`layouts.app.sidebar`). All learner screens sit inside the authenticated sidebar shell; admin screens add an admin nav group.

## Navigation (sidebar)

```
LEARN
  Dashboard          /dashboard
  Learning Path      /path
  Book               /book
  Lessons            /lessons/{slug}   (last opened by default)
PRACTICE
  Practice Queue     /practice
  Quiz Arena         /quiz
  Debug Lab          /debug
REINFORCE
  Flashcards         /flashcards
  Revision           /revision
  Skills             /skills
  Interview          /interview
BUILD
  Projects           /projects
AI
  AI Tutor           /tutor
ADMIN (role=admin only)
  Import             /admin/books
  Content            /admin/lessons
  Review Queue       /admin/review
  Concepts & Graph   /admin/concepts
  Analytics          /admin/analytics
```

---

## 1. Dashboard `/dashboard`

Three-column-free single flow, card grid:

| Card | Content | Source |
|---|---|---|
| Overall progress | ring % = weighted (lessons read 20%, concepts mastered 40%, exercises 30%, quizzes 10%) | computed service |
| Stage tracker | 12 stage chips, current highlighted, gates shown as locks | `stages` + gates |
| Due today | count of `review_items` due + Start button | `review_items` |
| Weak concepts | top 5 with level badges + "revise" | `concept_mastery` where level < practicing and attempts > 0 |
| Recent activity | last 10 attempts/quizzes | attempts tables |
| Streak | days with ≥1 action | `last_seen_at` + attempts |

Components: `livewire/learn/dashboard.blade.php`.

## 2. Learning Path `/path`

- Horizontal stage rail (12 nodes) with % per stage.
- Mermaid `flowchart TD` of the concept graph (from `05-knowledge-map.md`), nodes colored by mastery level, next recommended node highlighted.
- Right rail: gate requirements for the next stage with live pass/fail.

## 3. Book browser `/book`

- Tree: Parts → Chapters → Sections (expandable), each row shows printed page range and lesson status icons (unread / read / mastered).
- Click section → lesson. "Open PDF page" link renders `page_pdf` (local PDF stored on disk; served through a signed route).

## 4. Lesson `/lessons/{slug}` — the core screen

**Desktop (≥1024px):** 3 panes.

```
┌────────────┬──────────────────────────────────────┬──────────────────────┐
│ Course nav │  Lesson header                       │ Context rail         │
│ (sticky)   │  - title + citation (ch, pp.)        │ [Progress] bars      │
│            │  - source badge: 📖 Book / 🤖 AI     │ [Concepts] chips +   │
│ chapter    │  - mode tabs: Read Teach Practice     │   prereq status      │
│  ├ sec     │                Quiz Debug            │ [Notes] add/list     │
│  │  └ les ←────────────────────────────────────── │ [Tutor] collapsed    │
│            │  Blocks 1..9 (open)                  │   chat launcher      │
│            │  <details> blocks 10..18             │                      │
│            │  Summary + card links                │                      │
└────────────┴──────────────────────────────────────┴──────────────────────┘
```

- Left: chapter/section/lesson tree, current highlighted, status icons.
- Center: block renderer (`resources/views/lessons/blocks/*.blade.php` per type), Mermaid, syntax highlighting, collapsible sections, `modern_panel` rendered as 3 tabs (BOOK TEACHES / MODERN PHP / WHY).
- Right rail: tabs (Progress | Concepts | Notes | Tutor). On <1024px the rail becomes a bottom sheet triggered from the header.

**Mode tabs** switch center content while preserving lesson context:
Read (default) · Teach (tutor anchored to lesson) · Practice (exercise list) · Quiz · Debug.

**Time tracking:** Livewire `window` events → `lesson_progress.active_seconds` increments every 15s while the tab is focused.

## 5. Practice `/lessons/{slug}/practice` and `/practice`

- Exercise list (type, difficulty, status chips) → open one exercise.
- Layout: prompt (top) · code editor (CodeMirror, PHP mode) · right actions: Run (sandbox; disabled → "reveal expected output" in MVP), Hint (ladder), Reset, Submit.
- Hint ladder UI: `Hint 1` unlocked initially; `Hint 2` after Hint 1 viewed; `Hint 3` after 2 attempts; `Solution` after level 3 + 30s or 2 failed attempts.
- Result panel: pass/fail per test case, expected vs actual, explanation after submission, "Next exercise" CTA.
- Attempt history strip (last 5 attempts with result icons).

## 6. Quiz `/lessons/{slug}/quiz`

- Single question card, progress dots, options as Flux radio cards; code questions use highlighted `<pre>`.
- Submit → immediate correct/incorrect flash + explanation; **no going back**.
- Final: score ring, per-question review table, weak concepts list, "Add these to revision" button (auto-queued anyway), CTA to targeted practice.

## 7. Revision `/revision`

- Queue grouped by kind: Cards (flip deck), Concepts (recall prompt), Exercises (retry), Errors (read the pattern).
- Each item: `Snooze 1d` / `Mark done` / `Open`.
- Empty state: "Nothing due — your next review is …".

## 8. Flashcards `/flashcards`

- Session: big card, tap/space to flip, 3 grade buttons (Hard/Ok/Easy) showing next interval.
- Browse mode: filter by type/tag/concept, create personal cards.

## 9. Skills `/skills`

- 16-domain radar or bar list; each domain expandable to concepts with level chips and evidence counts (read/recall/easy/medium/debug/mixed).
- Explicit note in UI: "Reading does not raise skill levels."

## 10. Interview `/interview`

- Topic filter → question card → free-text attempt → reveal model answer + follow-ups → grade myself (Confident / Shaky / Missed) which creates a review item.

## 11. Projects `/projects`, `/projects/{slug}`

- Cards by level with unlock state (locked = prerequisite concepts not `comfortable`).
- Detail: brief, requirements checklist, tasks with hints, gated solution ("available after 1 attempt").

## 12. AI Tutor `/tutor` (+ lesson rail)

- Chat UI, mode indicator ("Anchored to: Ch 3 · Constructors"), quick actions: `Explain simpler`, `Another example`, `Quiz me`, `Give exercise`, `Hint only`.
- Source badges on every AI message; "This is AI, the book says…" citations when the question touches a lesson.
- Token/usage footer for transparency.

## 13. Search `/search?q=`

- Single input (⌘K opens overlay), grouped results: Lessons / Concepts / Examples / Exercises / Errors / Flashcards / Projects / Interview.
- Each hit shows type badge + snippet with highlight; empty state suggests tags.

## 14. Notes `/notes`, bookmarks

- List grouped by kind (note/highlight/bookmark/important), each linking back to the anchor in the lesson.

---

## Admin screens

| Route | Purpose | Key interaction |
|---|---|---|
| `/admin/books` | upload PDF, book metadata, import status | dropzone → job progress bar |
| `/admin/books/{id}/pages` | page review: text, extracted printed number, flags | inline edit, re-extract |
| `/admin/chapters` `/admin/sections` | TOC editor, page ranges, reorder | tree drag & drop (later) |
| `/admin/lessons` | lesson list filtered by status/source/stage | bulk publish |
| `/admin/lessons/{id}` | **block editor**: reorder blocks, edit JSON payload, live preview, source badges, diff vs AI draft | save → `in_review` |
| `/admin/concepts` | CRUD + prerequisite graph editor (Mermaid preview, cycle warnings) | |
| `/admin/exercises` | CRUD, hints ladder editor, test cases editor, "try it" panel | |
| `/admin/questions` `/admin/flashcards` | CRUD with bulk AI import | |
| `/admin/review` | review queue: AI generations awaiting approval, side-by-side diff, Approve / Edit / Reject | writes `content_versions` |
| `/admin/import-jobs` | pipeline monitor (queue states, errors, retry) | |
| `/admin/analytics` | content coverage, exercise pass rates, AI spend (`ai_generations`) | |

## Responsive rules

| Breakpoint | Behavior |
|---|---|
| ≥1280px | 3 panes visible |
| 1024–1279px | rail collapses to icons/tabs |
| <1024px | single column; left nav = sidebar drawer (Flux `collapsible="mobile"`); rail = bottom sheet; mode tabs scroll horizontally |

## UI component inventory (to build)

`status-badge`, `source-badge` (Book/AI), `citation-line`, `progress-ring`, `stage-chip`, `concept-chip`, `block-*` (paragraph, code, diagram, callout, table, tabs, modern-panel, quote), `hint-ladder`, `test-result-row`, `quiz-progress`, `flashcard`, `mastery-meter`, `empty-state`.
