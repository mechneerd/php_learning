# 09 — Flows

All diagrams are Mermaid; they render on `/path`, in docs, and in the admin help pages.

## 1. Book import (admin, Phase 1)

```mermaid
flowchart TD
    A[Admin uploads PDF] --> B[sha256 + pdfinfo]
    B --> C{Already imported?}
    C -- yes --> D[Reject duplicate]
    C -- no --> E[store file + pdf_documents row]
    E --> F[job: extract]
    F --> G[pdftotext per page]
    G --> H[sanitize control chars]
    H --> I[store pdf_pages + printed page no.]
    I --> J{quality < threshold?}
    J -- yes --> K[page_review_flags]
    J -- no --> L[job: detect chapters]
    K --> L
    L --> M[parse TOC entries]
    M --> N[verify heading on expected PDF page]
    N --> O{mismatch?}
    O -- yes --> P[flag section, keep TOC page ref]
    O -- no --> Q[write chapters + sections]
    P --> Q
    Q --> R[books.source_pdf_document_id linked]
    R --> S[ready for content generation]
```

## 2. AI content generation (Pipeline A, Phase 7)

```mermaid
flowchart TD
    A[admin: Generate for stage/section] --> B[job chain queued]
    B --> C[chunk section text ≤3k tokens]
    C --> D[extract concepts + dedupe by slug]
    D --> E[derive prerequisites]
    E --> F[validate graph: no cycles]
    F --> G{cycle?}
    G -- yes --> H[reject, require manual edit]
    G -- no --> I[generate lesson blocks]
    I --> J[generate code examples tiers 1-4]
    J --> K[generate Mermaid diagrams]
    K --> L[generate exercises + hints + tests]
    L --> M[generate quiz questions]
    M --> N[generate flashcards]
    N --> O[run outdated detection]
    O --> P[write ai_generations log]
    P --> Q[status = in_review]
    Q --> R{admin approves?}
    R -- edit --> S[content_versions diff saved]
    R -- reject --> T[discarded, log kept]
    S --> U[status = published]
    P --> U
    U --> V[search_index rebuilt]
```

Idempotency: each job hashes `(stage, prompt_version, input_chunk)`; re-runs skip completed work.

## 3. Learner opens a lesson (must not call AI)

```mermaid
sequenceDiagram
    participant L as Livewire Lesson
    participant D as DB (cached)
    L->>D: lesson + blocks (where status=published)
    D-->>L: blocks ordered
    L->>D: lesson_progress upsert (state=opened)
    L->>D: concept_prerequisites for rail
    L-->>L: render blocks (static only)
    Note over L: 0 AI API calls — enforced by test
```

## 4. Learning loop (one lesson)

```mermaid
flowchart TD
    A[Open lesson] --> B[Read blocks 1-9]
    B --> C[View diagram + examples]
    C --> D[Expand deeper blocks]
    D --> E{Explain it - recall}
    E -- score < 70% --> F[Missing points shown + retry]
    F --> E
    E -- >= 70% --> G[Practice mode]
    G --> H[Easy exercise]
    H -- fail --> I[Hint ladder]
    I --> H
    H -- pass --> J[Medium exercise]
    J --> K[Debug exercise]
    K --> L[Mixed-topic exercise]
    L --> M[Mini task]
    M --> N[Quiz]
    N -- < 80% --> O[Weak concepts queued for revision]
    O --> G
    N -- >= 80% --> P[Recall re-check after 1 day]
    P --> Q{evidence complete?}
    Q -- no --> G
    Q -- yes --> R[concept_mastery level up]
    R --> S[Skill domain updated]
    S --> T[Next lesson unlocked]
```

## 5. Exercise attempt with hint ladder

```mermaid
flowchart TD
    A[Open exercise] --> B[Attempt 1 - no hints]
    B --> C{Correct?}
    C -- yes --> D[result=correct, evidence key added]
    C -- no --> E[Show failure + test diffs]
    E --> F{Request hint?}
    F -- no --> B
    F -- yes --> G[Hint 1 conceptual]
    G --> H[Attempt 2]
    H --> I{Correct?}
    I -- yes --> D
    I -- no --> J[Hint 2 syntax]
    J --> K[Attempt 3]
    K --> L{Correct?}
    L -- yes --> D
    L -- no --> M[Hint 3 implementation]
    M --> N[Attempt 4]
    N --> O{Correct?}
    O -- yes --> D
    O -- no --> P[Solution unlocked after 2 failures]
    P --> Q[Show solution + explanation]
    Q --> R[Transfer task: modify without looking]
    R --> S[attempt recorded with hints_used]
```

## 6. Quiz flow

```mermaid
flowchart TD
    A[Start quiz] --> B[Load 4-6 questions shuffled]
    B --> C[Question 1 shown - one at a time]
    C --> D[Answer submitted - no back]
    D --> E[Immediate feedback + explanation]
    E --> F{More questions?}
    F -- yes --> C
    F -- no --> G[Compute score]
    G --> H[Aggregate per concept]
    H --> I[weak concepts = concept score < 60%]
    I --> J[Show report: score, right/wrong, weak list]
    J --> K[Queue review_items for weak concepts]
    K --> L[Best score stored for mastery evidence]
```

## 7. Mastery state machine

```mermaid
stateDiagram-v2
    [*] --> unseen
    unseen --> learning : lesson opened + 60s + summary seen
    learning --> practicing : recall >= 70% AND easy exercise correct
    practicing --> comfortable : medium exercise correct AND debug exercise correct
    comfortable --> mastered : mixed exercise correct AND all 6 evidence keys present
    mastered --> comfortable : failed attempt or 30 days idle (review queued)
    practicing --> learning : 3 consecutive failures
    learning --> unseen : progress reset by user
```

## 8. Spaced review

```mermaid
flowchart TD
    A[Nightly / on login] --> B[Query review_items due]
    B --> C[Cards due from flashcard_reviews]
    B --> D[Concepts due from schedule]
    B --> E[Past mistakes due]
    C --> F[Review session]
    D --> F
    E --> F
    F --> G{Grade}
    G -- hard --> H[interval x1.2]
    G -- ok --> I[interval x2.5]
    G -- easy --> J[interval x4]
    H --> K[next_review_at set]
    I --> K
    J --> K
    K --> L[Skill counts recalculated]
```

## 9. AI tutor (Pipeline B) — hint ladder policy

```mermaid
flowchart TD
    A[Question in tutor] --> B[Assemble context:<br/>lesson, concepts, attempts,<br/>quiz scores, weak areas]
    B --> C[Classify intent:<br/>explain / example / hint / solution / quiz / exercise]
    C --> D{Wants a solution?}
    D -- no --> E[Teach directly - cite lesson blocks]
    D -- yes --> F{Attempt state}
    F -- no active attempt --> G[Give conceptual hint first]
    F -- active attempt --> H[Respect ladder position]
    H --> I[Hint 1 -> Hint 2 -> Hint 3]
    I --> J{Ladder complete or 2 failures?}
    J -- no --> K[Refuse solution, push learner to try]
    J -- yes --> L[Provide solution]
    L --> M[Immediate transfer task: change X without looking]
    E --> N[Log ai_messages + token usage]
    K --> N
    M --> N
```

## 10. Global search

```mermaid
flowchart TD
    A[Type query] --> B[debounce 250ms]
    B --> C{FTS5 available?}
    C -- yes --> D[match against search_index]
    C -- no --> E[LIKE fallback on title/body]
    D --> F[Group by entity_type]
    E --> F
    F --> G[Rank: lessons > concepts > exercises > cards]
    G --> H[Render grouped results + snippets]
```

## 11. Admin publish gate

```mermaid
flowchart LR
    A[AI generation] --> B[in_review]
    C[Admin edits] --> B
    B --> D{Approve}
    D -- approve --> E[published + reviewed_by/at]
    D -- edit more --> C
    D -- reject --> F[archived + log kept]
    E --> G[search:reindex]
```

## 12. Code execution (Phase 8, sandboxed service)

```mermaid
sequenceDiagram
    participant FE as Editor (Livewire)
    participant APP as Laravel app
    participant Q as Queue worker
    participant SB as Sandbox container
    FE->>APP: submit code + exercise_id (auth, rate-limited)
    APP->>APP: static checks (size, banned funcs)
    APP->>Q: dispatch RunCodeJob (signed payload)
    Q->>SB: HTTP POST /run (no shared FS, no network)
    SB->>SB: run php with cpu/mem/time limits
    SB-->>Q: stdout, stderr, exit code, metrics
    Q-->>APP: store CodeRun result
    APP-->>FE: poll/subscribe → output + tests
    Note over APP,SB: Never exec() in web process
```
