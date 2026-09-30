# 07 — Entity Relationship Diagrams

Mermaid `erDiagram` renders in the `/admin` schema page and in GitHub. Split by module to stay readable.

## 1. Source & content tree

```mermaid
erDiagram
    books ||--o{ chapters : has
    books ||--o{ pdf_documents : has
    pdf_documents ||--o{ pdf_pages : has
    pdf_documents ||--o{ import_jobs : has
    pdf_pages ||--o{ page_review_flags : flagged_by
    chapters ||--o{ sections : has
    sections |o--o{ sections : "parent_id"
    sections ||--o{ lessons : spawns
    stages ||--o{ lessons : contains
    lessons ||--o{ lesson_blocks : renders_as
    chapters ||--o{ lessons : "provenance"

    books {
        int id PK
        string title
        string author
        string edition
        int published_year
        string status
    }
    chapters {
        int id PK
        int book_id FK
        int number
        string title
        int page_printed_from
        int page_pdf_from
    }
    sections {
        int id PK
        int chapter_id FK
        int parent_id FK
        string title
        tinyint level
        int page_printed_from
    }
    lessons {
        int id PK
        int stage_id FK
        int section_id FK
        int chapter_id FK
        string slug UK
        string status
        string source
        int page_printed_from
    }
    lesson_blocks {
        int id PK
        int lesson_id FK
        int ord
        string type
        json payload
        string source
    }
```

## 2. Concepts & learning assets

```mermaid
erDiagram
    lessons ||--o{ lesson_concepts : tags
    concepts ||--o{ lesson_concepts : appears_in
    concepts |o--o{ concept_prerequisites : "requires"
    concepts |o--o{ concept_prerequisites : "required_by"
    lessons ||--o{ code_examples : has
    lessons ||--o{ diagrams : has
    lessons ||--o{ exercises : has
    lessons ||--o{ quiz_questions : has
    lessons ||--o{ flashcards : has
    concepts ||--o{ exercises : "tested_by"
    concepts ||--o{ quiz_questions : "tested_by"
    concepts ||--o{ flashcards : "card_for"
    concepts ||--o{ interview_questions : asked_in
    exercises ||--o{ exercise_hints : has
    exercises ||--o{ exercise_tests : graded_by
    quiz_questions ||--o{ quiz_options : offers
    stages ||--o{ projects : contains
    projects ||--o{ project_tasks : split_into
    lessons ||--o{ terms : mentions
    terms ||--o{ lesson_terms : ""

    concepts {
        int id PK
        string slug UK
        string name
        string skill_domain
        bool is_core
    }
    concept_prerequisites {
        int concept_id PK_FK
        int prereq_concept_id PK_FK
        int weight
        string source
    }
    exercises {
        int id PK
        int lesson_id FK
        int concept_id FK
        string type
        string difficulty
        string status
    }
    quiz_questions {
        int id PK
        int lesson_id FK
        int concept_id FK
        string type
        string stem
    }
```

## 3. Practice & attempts

```mermaid
erDiagram
    users ||--o{ exercise_attempts : makes
    exercises ||--o{ exercise_attempts : receives
    users ||--o{ quiz_attempts : takes
    lessons ||--o{ quiz_attempts : "per lesson"
    quiz_attempts ||--o{ quiz_answers : contains
    quiz_questions ||--o{ quiz_answers : "answers"
    users ||--o{ recall_attempts : writes
    concepts ||--o{ recall_attempts : "about"
    users ||--o{ flashcard_reviews : reviews
    flashcards ||--o{ flashcard_reviews : "reviewed as"

    exercise_attempts {
        int id PK
        int user_id FK
        int exercise_id FK
        string result
        int hints_used
        int duration_sec
        json test_results
    }
    quiz_attempts {
        int id PK
        int user_id FK
        int lesson_id FK
        decimal score
        json weak_concepts
    }
    flashcard_reviews {
        int id PK
        int user_id FK
        int card_id FK
        string grade
        int interval_days
        datetime next_review_at
    }
```

## 4. Progress, notes, AI

```mermaid
erDiagram
    users ||--o{ lesson_progress : tracks
    lessons ||--o{ lesson_progress : ""
    users ||--o{ concept_mastery : earns
    concepts ||--o{ concept_mastery : ""
    users ||--o{ skill_progress : accumulates
    users ||--o{ review_items : queued_for
    concepts ||--o{ review_items : ""
    users ||--o{ notes : writes
    notes }o--|| taggables : "polymorphic"
    tags ||--o{ taggables : labels
    users ||--o{ ai_conversations : holds
    ai_conversations ||--o{ ai_messages : contains
    lessons ||--o{ ai_conversations : context
    users ||--o{ content_versions : approves
    ai_generations ||--o{ content_versions : produces

    lesson_progress {
        int id PK
        int user_id FK
        int lesson_id FK
        string state
        int active_seconds
    }
    concept_mastery {
        int id PK
        int user_id FK
        int concept_id FK
        string level
        json evidence
    }
    review_items {
        int id PK
        int user_id FK
        string item_type
        int item_id
        datetime due_at
        string status
    }
    ai_messages {
        int id PK
        int conversation_id FK
        string role
        text content
        int tokens_in
        int tokens_out
    }
```

## Relationship rules

| Relationship | On delete |
|---|---|
| `lessons → lesson_blocks` | cascade (hard delete) |
| `lessons → exercises/quiz/flashcards` | soft delete lesson → children stay but hidden |
| `exercises → hints/tests` | cascade |
| `quiz_questions → options` | cascade |
| `users → attempts/progress/notes` | cascade |
| `sections → lessons` | restrict if published children |
| `concepts → prerequisites` | cascade both directions (service cleans orphans) |
