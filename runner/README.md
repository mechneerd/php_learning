# Runner — sandboxed code execution

Standalone, zero-dependency PHP service that executes learner code under the
limits in `docs/12-security-and-sandbox.md` §2. The Laravel app calls it over
internal HTTP with signed requests; code and output travel inline (no shared
filesystem). This service is intentionally **outside `app/`** — it is the only
place allowed to spawn processes for user code.

## Run

```bash
RUNNER_SECRET=change-me php -S 127.0.0.1:8090 runner/router.php
```

The app expects it at `RUNNER_URL` (default `http://127.0.0.1:8090`) with the
same `RUNNER_SECRET`.

## Environment

| Variable | Default | Meaning |
|---|---|---|
| `RUNNER_SECRET` | *(required)* | HMAC key for request signing (fail closed if empty) |
| `RUNNER_WALL_SECONDS` | `3` | Wall-clock budget per run |
| `RUNNER_KILL_SECONDS` | `wall + 0.5` | Hard kill deadline |
| `RUNNER_MEMORY_MB` | `64` | Child `memory_limit` |
| `RUNNER_OUTPUT_BYTES` | `65536` | stdout/stderr cap (truncated) |
| `RUNNER_SOURCE_BYTES` | `32768` | Max source size (413 above) |
| `RUNNER_SIGNATURE_TTL` | `60` | Max age of `X-Runner-Timestamp` |

## Protocol

`POST /run` with headers `X-Runner-Timestamp` (unix seconds) and
`X-Runner-Signature` = `HMAC-SHA256(secret, timestamp + "\n" + body)`, body
`{"code": "..."}`. Response: `{"status": "ok|timeout|blocked", "stdout",
"stderr", "exit_code", "duration_ms", "truncated", "oom", "error"}`.
`GET /health` → `{"ok": true}`.

## Tests

```bash
php runner/tests/limits_test.php
```

Boots the service with small test limits and asserts signing, banned-function
blocking, wall kill (exit 124), output truncation, OOM detection, and source
size limits — 19 checks.
