# PropertyFlow — Operations Runbook

> For whoever is on call or deploying PropertyFlow. Assumes a single app server (or a small
> cluster behind a load balancer) with MySQL, and no external services yet (email/S3/Stripe land
> in Phase 12 — see `04-implementation-plan.md`).

---

## 1. Processes that must be running

| Process | Command | Why |
|---|---|---|
| Web server | PHP-FPM + Nginx/Caddy (or `php artisan serve` only for local testing) | Serves the app |
| Queue worker | `php artisan queue:work --tries=3` | Webhook deliveries, company data exports, broadcast events, backups |
| Scheduler | A cron entry running `php artisan schedule:run` every minute | Billing, late fees, reminders, backups, ballot/announcement publishing (see §4) |
| Reverb (WebSockets) | `php artisan reverb:start` | Live front-desk activity feed. Put a reverse proxy in front that terminates TLS and forwards to `REVERB_PORT` over `wss://` |

Run the queue worker under a process supervisor (systemd, Supervisor, or your platform's
equivalent) so it restarts on crash and after each deploy. `composer run dev` starts all of the
above together for local development only — never use it in production.

---

## 2. Deploying

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. `php artisan down` (optional — see zero-downtime note below)
4. `php artisan migrate --force`
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. `php artisan permissions:sync-new --no-interaction` — grants any newly added `Permission` case
   to every existing company's roles. Skipping this after a release that adds a permission means
   the feature behind it silently 403s for every company that existed before the release.
7. Restart PHP-FPM, the queue worker, and Reverb so they pick up the new code (the queue worker
   especially: a running worker keeps executing the *old* job classes from memory until restarted).
8. `php artisan up`

### Zero-downtime

With `APP_MAINTENANCE_DRIVER=database` (set in `.env.production.example`), maintenance mode is
recorded in the database rather than a file, so it's visible even if you're running more than one
app server. For true zero-downtime: deploy to a new release directory, run steps 1-2 and 4-6
against it, atomically swap a symlink to make it live, *then* restart the workers — visitors never
see a maintenance page. `storage_path()` and `.env` should live outside the versioned release
directory (shared across releases) since uploaded files and secrets shouldn't be duplicated per
deploy.

### Rolling back

`php artisan migrate:rollback` reverses the last batch of migrations. Prefer rolling forward with
a fix when possible — MySQL DDL isn't transactional, so a failed `down()` can leave a table
half-dropped. If a migration truly needs to go, check its `down()` method actually reverses
everything it did (some of this project's migrations only add nullable columns and are safe to
roll back; a few drop columns that had data in them, which is destructive — confirm before
rolling those back in production).

---

## 3. Backups & restore

Configured in `config/backup.php` (spatie/laravel-backup), scheduled daily in `routes/console.php`:

- `backup:run` (01:30) — dumps the database and zips it together with `storage/app` (documents,
  company logos, signatures, data exports) onto the `backups` disk, which is `storage/backups` —
  deliberately *outside* `storage/app`, so a backup never includes previous backups of itself.
- `backup:clean` (01:00, before the run) — prunes old backups per the retention strategy in
  `config/backup.php`.
- `backup:monitor` (02:30) — checks the latest backup is recent enough and the destination isn't
  over its size limit; fires `UnhealthyBackupWasFound` if not.

No email provider is configured yet, so failure/unhealthy events don't send anywhere — they're
logged instead (`AppServiceProvider::configureReliability()`) at `critical` level as
`Backup failed`, `Backup cleanup failed`, or `Backup is unhealthy` in `storage/logs/laravel.log`.
Point your log-based alerting at that level.

### Restoring (drill this periodically, not just when something's on fire)

```bash
# 1. Get the latest backup zip off the backups disk.
LATEST=$(ls -t storage/backups/PropertyFlow/*.zip | head -1)

# 2. Pull just the database dump out of it.
unzip -o "$LATEST" "db-dumps/mysql-*.sql" -d /tmp/restore

# 3. Restore into a *new* database first — never straight into production.
mysql -u root -p -e "CREATE DATABASE property_flow_restore_check;"
mysql -u root -p property_flow_restore_check < /tmp/restore/db-dumps/mysql-*.sql

# 4. Sanity-check row counts against a few major tables before trusting it, then drop the
#    scratch database once you're satisfied.
mysql -u root -p -e "SELECT COUNT(*) FROM property_flow_restore_check.units;"
```

Only once you've confirmed the dump is good do you swap it into the real database (stop the app,
rename/drop the old database, restore into `property_flow`, restart). The private files
(`storage/app/private/...`) are in the same zip under their own paths — extract and copy them back
if a files-level restore is also needed, not just the database.

---

## 4. Scheduled jobs (what runs and when)

All defined in `routes/console.php`; all require the cron-driven scheduler (§1) to be running.

| Command | Schedule | What it does |
|---|---|---|
| `announcements:publish-due` | every minute | Publishes announcements scheduled for a time that has now passed |
| `ballots:close-ended` | every minute | Closes ballots/votes whose window has ended and tallies results |
| `maintenance:generate-due-work-orders` | daily | Turns due preventive-maintenance schedules into work orders |
| `packages:remind-uncollected` | daily | Nudges residents about packages still waiting at the front desk |
| `finance:run-billing` | 01:00 | Generates recurring charges/invoices for the period |
| `finance:assess-late-fees` | 02:00 | Applies late fees to overdue invoices per each community's rule |
| `finance:send-overdue-reminders` | 09:00 | Notifies residents with overdue balances |
| `violations:escalate` | 06:00 | Escalates violations that have sat unresolved past their window |
| `backup:clean` / `backup:run` / `backup:monitor` | 01:00 / 01:30 / 02:30 | See §3 |

If a scheduled command silently stops running, the first thing to check is whether the cron
entry / scheduler process is still alive — a stopped scheduler fails silently (nothing errors,
things just stop happening).

---

## 5. Health & monitoring

- `GET /up` — Laravel's built-in health check. Returns 200 if the app booted; point your
  load balancer / uptime monitor at it.
- Failed queued jobs are logged at `critical` (`Queued job failed`, with the job class and
  exception message) — see `AppServiceProvider::configureReliability()`. The `failed_jobs` table
  also has the full record for `php artisan queue:retry` if a failure was transient.
- Webhook endpoints disable themselves automatically after
  `config('webhooks.disable_after_failures')` consecutive failures — check the Webhooks page
  (`/webhooks`, company admins) if a company reports events stopped arriving.

---

## 6. Load testing

`tests/load/` has a k6 script (`dashboard-and-lists.js`) exercising the dashboard and the two
main community lists (units, residents) under concurrent load — see its README for setup. Re-run
it after any change likely to affect those pages, and before trusting a specific server's
capacity — the result depends on the PHP-FPM worker pool size of whatever it's run against, not
just the code.

---

## 7. Common support situations

| Situation | What's happening | Where to look / what to do |
|---|---|---|
| A team member can't sign in | Deactivated, or their company was suspended | `users.deactivated_at`, `companies.suspended_at`. Reactivate from the Team page (deactivation) or `/platform/companies` (suspension) |
| "The whole company is locked out" | The company was suspended (by a super admin) or its plan/billing lapsed (no billing yet, so only suspension applies today) | `/platform/companies` → Reactivate |
| Need to see what a company sees, to debug a report | Impersonation | `/platform/companies` → Impersonate (super admin only, fully audited via the activity log — `impersonation_started`/`impersonation_stopped` events) |
| A resident says a notification never arrived | No email/SMS/push yet — everything is in-app only until Phase 12 | Check `/notifications` for that user, and their notification preferences |
| Webhook stopped firing | Endpoint auto-disabled after repeated failures | `/webhooks` (company admin) → check delivery history, re-enable after fixing the receiving end |
