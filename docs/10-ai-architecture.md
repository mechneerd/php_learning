# 10 — AI Architecture

Two pipelines share one provider abstraction.

```
app/Services/Ai/
  Contracts/AiClient.php        generate(Prompt): AiResult
  StubAiClient.php              deterministic canned output (tests / no-key mode)
  OpenAiClient.php              HTTP driver (provider-agnostic shape)
  AnthropicClient.php           (optional)
  ContextAssembler.php          builds learner + lesson context for Pipeline B
  PromptRunner.php              hashing, logging, retries, token accounting
  Prompts/*.php                 versioned prompt builders (one per stage)
```

Config: `config/ai.php` → `provider` (`stub|openai|anthropic`), `api_key`, `model`, `max_tokens`, `daily_token_budget`, `prompt_version` map.

---

## Pipeline A — content generation (offline)

### Job chain (queued, `database` queue driver)

| Job | Input | Output entities |
|---|---|---|
| `ExtractConceptsJob` | section chunk + TOC title | `concepts`, `lesson_concepts` |
| `DerivePrerequisitesJob` | concept set + section order | `concept_prerequisites` (cycle-checked) |
| `GenerateLessonJob` | section text + concept list + template | `lessons`, `lesson_blocks` |
| `GenerateCodeExamplesJob` | lesson + book listings + repo file | `code_examples` (tiers) |
| `GenerateDiagramJob` | lesson + figure captions | `diagrams` (Mermaid) |
| `GenerateExercisesJob` | lesson + concepts | `exercises`, `exercise_hints`, `exercise_tests` |
| `GenerateQuizJob` | lesson + concepts | `quiz_questions`, `quiz_options` |
| `GenerateCardsJob` | lesson + concepts | `flashcards` |
| `DetectOutdatedJob` | lesson blocks + book year | `is_outdated`, `modern_panel` blocks |
| `IndexContentJob` | published entity | `search_index` rows |

All writes land as `status = in_review` (never auto-publish).

### Output contract

Every generator returns **JSON validated against a PHP DTO** (`LessonDraft`, `ExerciseDraft`, …). Invalid JSON → retry once with a "fix your JSON" correction prompt → then fail the job (visible in `/admin/import-jobs`).

### Prompt pattern (example: lesson generation)

```
SYSTEM:
You are a PHP teacher writing for a complete beginner.
You are given EXCERPTS from the book "{book}" ({chapter}, pp. {pages}).
Rules:
- Use book material faithfully; mark anything you add as teaching scaffolding.
- Never invent book quotes. Output only the JSON schema provided.
- Explanation level: no assumed knowledge of OOP before this lesson.
- Include: what/why/prereqs/simple/analogy/visual/syntax/examples/mistakes/
  usage/summary blocks; leave exercise/quiz/card refs empty (generated later).
JSON schema: {json_schema}

USER:
Book excerpt:
"""
{section_text}
"""
Concepts to cover: {concept_slugs}
Prerequisites already taught: {prereq_slugs}
```

Prompt templates carry a `version` string; bumping it invalidates idempotency hashes (`ai_generations.input_hash`).

### Cost / caching rules

- Generated content lives in the DB — **opening a lesson never calls AI** (tested).
- Regeneration only from the admin review screen, per entity, with a version diff.
- Daily token budget from config; exceeding it pauses jobs (`status = failed`, reason `budget`).

---

## Pipeline B — runtime tutor

### Context payload (`ContextAssembler`)

```json
{
  "learner": {"stage": 2, "mastered": [...], "practicing": [...], "weak": [...]},
  "current": {"lesson": "constructors", "chapter": 3, "pages": [29,33],
               "concepts": [...], "source_blocks": [...]},
  "history": {"last_attempts": [...], "quiz_scores": [...], "common_mistakes": [...]},
  "mode": "tutor"
}
```

Trimmed to a token ceiling (oldest messages summarized).

### System policy (enforced in prompt + code)

1. Teach — do not just answer. Prefer questions, analogies, small steps.
2. **Hint ladder**: solution requires ladder position (from `exercise_attempts`/`session state`) ≥ 3 or 2 failures. Otherwise respond with the next hint level only.
3. After any full solution: immediately issue a transfer task ("now modify it to …"), and ask the learner to close the tab and recreate it.
4. Cite the lesson when the topic exists in it ("as the book says on p. 91…"), and mark AI-only additions.
5. When asked "quiz me" / "give me an exercise" → generate one in the schema and (in Phase 7+) persist it via admin queue.
6. Never output whole project solutions; never do the learner's project task for them.
7. Beginner register: short sentences, one idea at a time, no unexplained jargon.

### Intent handling

| Intent | Handler |
|---|---|
| explain simpler / like I'm new | re-explain with analogy, block-level citation |
| another example | tiered example from `code_examples` or fresh mini-example |
| why does this work | internals explanation (execution trace) |
| real-world / Laravel | real-world scenario / bridge panel |
| hint | next ladder level only |
| solution | ladder gate |
| quiz me / exercise | generator path |
| I don't understand this line | line-level explanation of quoted code |

### Guardrails

| Risk | Control |
|---|---|
| Dependency | ladder + transfer tasks + "answer from memory" prompts |
| Hallucination | lesson context injected; refusal to cite pages it wasn't given |
| Cost abuse | per-user daily token budget, rate limit 20 msg/10 min |
| Prompt injection via learner text | user content wrapped in delimiters; system prompt immutable |
| Leakage of book text into outputs | excerpt length caps; UI shows short quotes only |

### Storage

`ai_conversations` + `ai_messages` (tokens in/out per message) → feeds `/admin/analytics` spend chart and the tutor's own memory.

---

## Model selection

| Task | Default model setting |
|---|---|
| Lesson/block generation | `ai.model_quality` (higher reasoning) |
| Exercise/quiz generation | `ai.model_quality` |
| Tutor chat | `ai.model_fast` (low latency) |
| Recall scoring (rubric) | `ai.model_fast`, temperature 0 |

`StubAiClient` returns schema-valid fixtures so **the entire app works with no API key** (tests use it by default).
