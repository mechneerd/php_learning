# 11 — Learning Workflow

The product's core promise: **the learner writes code, explains, predicts, debugs and recalls on every topic — reading alone never counts as learning.**

## 1. The 10-step loop

```
UNDERSTAND → SEE → EXPLAIN → PREDICT → WRITE → DEBUG → PRACTICE → APPLY → RECALL → USE IN A PROJECT
```

| Step | Learner action | System action | Evidence key |
|---|---|---|---|
| UNDERSTAND | reads blocks 1–4 (what/why/prereq/simple) | starts `lesson_progress`, records `state=opened` | `read` (needs ≥60 s + summary viewed) |
| SEE | studies Mermaid diagram + tier-1 code + output | nothing (passive) | — |
| EXPLAIN | types their own explanation | scores against rubric, lists `missing_points` | `recall` ≥ 70% |
| PREDICT | predicts output of a snippet (no running) | compares to stored answer | contributes to `easy/medium` |
| WRITE | writes code for a tier-2 task | runs tests (or static checks pre-sandbox) | `easy`, `medium` |
| DEBUG | finds/fixes seeded bug | validates fix | `debug` |
| PRACTICE | mixed-topic exercise | requires ≥2 concepts on the exercise | `mixed` |
| APPLY | mini task with a backend scenario | manual/self report + AI spot check | counts to `comfortable` |
| RECALL | spaced revisit (D+1/3/7/14) | schedules `review_items` | keeps level alive |
| USE | project task that needs the concept | task checklist | project completion |

## 2. Evidence → mastery

```
read + recall                → learning
+ easy                       → practicing
+ medium + debug             → comfortable
+ mixed (all six)            → mastered
```

Enforced centrally in `MasteryEvaluator::promote(User, Concept, evidenceKey)` — UI never sets levels directly.

Demotion: 3 consecutive failures on a concept, or a `mastered` concept idle > 30 days → drop one level + queue review.

## 3. Daily rhythm (what the UI nudges)

1. **Revision due first** (`/revision` badge on login).
2. Then the current lesson of the active stage.
3. Then practice queue (target: ≥3 exercise attempts/day).
4. Weekly: quiz re-run on the previous chapter + interview set.

Streak = days with ≥1 *active* action (attempt, recall, quiz, card review) — not logins.

## 4. Mode ↔ step mapping

| Mode | Steps served |
|---|---|
| Read | UNDERSTAND, SEE |
| Teach (AI) | UNDERSTAND, EXPLAIN |
| Practice | PREDICT, WRITE, DEBUG, PRACTICE |
| Quiz | EXPLAIN, PREDICT |
| Debug lab | DEBUG |
| Revision | RECALL |
| Project | APPLY, USE |
| Interview | EXPLAIN (under articulating) |

## 5. Feedback principles

- **Never show answers before an attempt.** Failure feedback = what the test expected, not the fix.
- **Hints are graded and logged** (`hints_used` reduces "clean attempt" credit).
- **Mistakes become content:** a failed attempt can be bookmarked into `/errors` and scheduled for revision.
- **Every correct answer reinforces the concept graph:** `concept_mastery` and `skill_progress` update in the same transaction as the attempt (DB transaction per attempt).

## 6. Skill dashboard computation

```
domain_level = f(
  count(concepts in domain by mastery level),
  exercise pass rate in domain,
  quiz best scores in domain
)
```
Reading percentage is shown separately and **never** feeds `skill_progress`.

## 7. Sample learner journey (first week)

| Day | Activity | Outcome |
|---|---|---|
| 1 | Stage 0: variables, types, conditions + 6 exercises | 3 concepts `practicing` |
| 2 | Revision (8 cards) + loops/arrays + quiz | streak 2, weak: `associative arrays` queued |
| 3 | Fix weak concept + functions + closures intro | weak cleared |
| 4 | Stage 1 orientation + Stage 2 first lesson (classes) | `read+recall` on `class` |
| 5 | Practice: 4 write exercises, 1 debug | `easy` evidence ×4 |
| 6 | Quiz Ch3 + revision | gate progress visible on `/path` |
| 7 | Project: todo (Stage 0–2 concepts) | first project started |
