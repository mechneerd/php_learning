# 12 — Security & Sandbox

## 1. Application security

| Area | Control |
|---|---|
| Auth | Fortify (installed): login, register, password reset, 2FA optional, email verification |
| Roles | `users.role` = `learner`\|`admin`; `EnsureUserIsAdmin` middleware on `/admin/*` |
| Authorization | Policies per content aggregate (`LessonPolicy`, `ExercisePolicy`, …); admin-only mutations |
| Validation | Form Requests on every write endpoint; Livewire property validation rules |
| CSRF | Laravel tokens (Flux/Livewire handles automatically) |
| XSS | Blade escaping by default; Markdown/blocks rendered through allow-listed renderer (`{!! !!}` only for code/diagram output after sanitization) |
| SQL injection | Eloquent/bindings only; no raw string-built queries |
| File upload | PDF only, size cap (50 MB), hash + random store path, outside `public/`, served via signed temporary URLs |
| Rate limiting | tutor messages (20/10 min), attempts (60/min), login via Fortify |
| Secrets | `.env` only; AI key never in JS or logs (`ai_messages` stores content, not keys) |
| Audit | `content_versions` for published content edits; `ai_generations` for AI spend |

CI grep gate (test): no `eval(`, `exec(`, `proc_open(`, `shell_exec(`, `passthru(`, `system(` anywhere under `app/`.

## 2. Why the sandbox exists

Learner code must run for `write/fix/complete` exercises. **Never inside the Laravel process.**

### Hard rules

1. Web process never spawns PHP for user code.
2. Execution happens in a separate, disposable container/process with:
3. The runner exposes only an internal HTTP endpoint; Laravel calls it via signed requests.
4. No shared filesystem — code is transferred inline (stdin/POST), output returned inline.
5. Results stored in DB (`code_runs` in Phase 8), never in session.

### Limits (per run)

| Limit | Value |
|---|---|
| Wall time | 3 s (kill at 3.5 s) |
| CPU | 1 core, 100% quota |
| Memory | 64 MB |
| Output size | 64 KB (truncate) |
| Source size | 32 KB |
| Processes | 1 (no `pcntl`, no `exec` family) |
| Filesystem | read-only tmpfs, 16 MB, no reads outside `/app` |
| Network | none (drop all egress) |
| Privileges | non-root, `no-new-privileges`, seccomp default profile |
| Concurrency | per-user 2, global 8 (queue-level) |

### Banned functions (static pre-check)

`eval, assert, exec, system, shell_exec, passthru, proc_open, popen, pcntl_*, curl_exec, fsockopen, file_get_contents(http…), include of remote URLs, dl, putenv, mail`.

Pre-checks run **before** dispatch (fast fail) and again inside the runner (defense in depth).

### Pre-MVP behavior (Phases 1–7)

- Editor is fully functional for writing, hinting, resetting.
- `Run` button shows the **stored expected output** for `predict_output` exercises and runs **static checks** for `write/fix` (regex/AST-lite rules stored in `exercise_tests.type = static_check`).
- UI labels: "Live execution arrives in Phase 8 — answers are checked with static rules for now."

## 3. Content safety

- AI output is schema-validated and admin-approved; raw model output is never rendered directly to learners.
- Book excerpts capped (≤ 240 words per `book_quote` block) with citation — personal-use study, not redistribution.
- Mermaid sources sanitized (allow-list of diagram types; no HTML in labels beyond basic entities).

## 4. Data protection

- Personal data limited to name/email + learning records.
- `php artisan app:forget-user` style deletion via Fortify delete-account flow (cascades progress).
- Backups: SQLite file copy / MySQL dump — documented in ops runbook (Phase 10).
