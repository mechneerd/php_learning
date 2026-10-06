# 16 — Operations runbook (backup, restore, deploy)

Operational procedures for the PHP Learning Platform. Development runs on SQLite with database sessions and the database queue; production should keep the same drivers unless you deliberately migrate them.

## 1. What holds state

| State | Location | Backup? |
|---|---|---|
| Application data (everything) | `database/database.sqlite` (dev) / `DB_*` in `.env` (prod) | **Yes — primary artifact** |
| Environment & secrets | `.env` | **Yes — never commit** |
| User uploads (PDFs, flags) | `storage/app/private` | Yes |
| Public assets (generated) | `storage/app/public` | Optional (regenerable) |
| Compiled caches | `bootstrap/cache` | No — `php artisan optimize:clear` |
| Built frontend | `public/build` | No — `npm run build` |

## 2. Backup

While the app is stopped (safest for SQLite file copies):

```powershell
# PowerShell (dev)
Copy-Item database\database.sqlite "backups\app-$(Get-Date -Format yyyyMMdd).sqlite"
Copy-Item .env "backups\.env.$(Get-Date -Format yyyyMMdd)"
Copy-Item -Recurse storage\app\private "backups\storage-private-$(Get-Date -Format yyyyMMdd)"
```

If the app must stay online, use SQLite's online backup instead of a raw copy:

```bash
sqlite3 database/database.sqlite ".backup 'backups/app-$(date +%Y%m%d).sqlite'"
```

Keep `.env` backups **encrypted or outside the repo** — they contain `APP_KEY`, AI provider keys and DB credentials.

## 3. Restore

1. Stop the app and queue workers (`Ctrl+C` on `composer dev`).
2. Put the database file back (or point `DB_*` at the restored database) and restore `.env`.
3. Restore `storage/app/private` if uploads are part of the recovery.
4. Bring the schema up to date: `php artisan migrate --force`.
5. `php artisan optimize:clear` then, for production, `php artisan optimize`.
6. Verify: `composer test` (green) + smoke: login → dashboard → open a lesson → run one exercise.

## 4. Deploy

```bash
git pull
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then edit: APP_ENV=production, APP_DEBUG=false, APP_KEY, DB_*, AI_*
php artisan key:generate      # only if APP_KEY was empty
npm ci && npm run build
php artisan migrate --force
php artisan optimize          # config + events + routes + views caches
php artisan queue:restart     # pick up new code in workers
```

Queue worker (systemd/supervisor, or a foreground check):

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

There are **no scheduled tasks** currently; if you add any, cron the runner: `* * * * * php artisan schedule:run`.

After any `.env`/`config` change on a machine with caches enabled: `php artisan optimize:clear && php artisan optimize`.

## 5. Quality gates before every release

1. `composer test` — Pint + PHPStan + Pest (must be exit 0).
2. `composer test:runner` — sandbox limits (19 checks).
3. `npm run build`.
4. Manual smoke of the [MVP acceptance checklist](14-mvp-scope.md#mvp-acceptance-checklist):
   - register/login, dashboard rings + stage chips
   - open a lesson (all blocks, citation, bridge panel), **zero AI calls on read**
   - practice: attempt → hints → solution recorded; quiz: score + revision queued
   - flashcards, revision, skills, projects, error library, interview
   - admin: review queue publish (writes `content_versions`), `/admin/import-jobs`, `/admin/analytics`

## 6. Monitoring

| What | Where |
|---|---|
| AI spend (30-day chart), coverage, pass rates | `/admin/analytics` |
| Generation ledger, failures, token budget | `/admin/import-jobs` |
| Queue depth | database `jobs` table / worker logs |
| Runner health | `composer test:runner` (time/memory/output limits) |

Budgets: `AI_DAILY_TOKEN_BUDGET` and `AI_DAILY_GENERATION_BUDGET` in `.env` (see `config/ai.php`). Runner limits: `config/runner.php` (`wall_seconds`, `memory_mb`, `output_bytes`) — **never raise these to "fix" a failing test; fix the code.**

## 7. Rollback

1. `git revert` / checkout the previous release.
2. `php artisan migrate:rollback --step=N` if the release included migrations.
3. Restore the database backup if data was corrupted.
4. `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`.
5. Re-run the smoke checklist.

## 8. Security notes

- Learner code runs **only** in `runner/` subprocesses with time/memory/output limits — never via `exec`/`shell_exec`/`proc_open` in `app/`.
- Admin area is behind the `admin` route middleware **and** policies; verify both after touching auth code.
- Rotate `APP_KEY` only with a plan: existing encrypted fields (2FA secrets) become unreadable.
