# Deploying MCI

MCI is built to run on **standard shared PHP hosting** (PHP 8.3+, MySQL 8 /
MariaDB 10.6+, SSH or at least cron + file upload). Nothing needs to run
permanently: one cron entry drives the scheduler, which also processes the
queue. Moving to a VPS later is a matter of changing `.env`.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`, `openssl`, `zip`
- PHP CLI available to cron (often `/usr/local/bin/php` or `php83`)
- `proc_open` / `exec` allowed (used by `schedule:run`); if your host forbids
  it, use the alternative cron line below
- HTTPS (required for the PWA and Web Push in later steps)

## Profile A — shared hosting (default)

1. **Build locally** (or in CI): `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
2. **Upload** the project *outside* the web root (e.g. `~/mci`), then point the
   domain's document root at `~/mci/public`. If the panel cannot change the
   document root, symlink `public_html` → `~/mci/public`.
3. **Configure** `~/mci/.env` from `.env.example` (keep the shared-hosting defaults):
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://mci.twoja-szkola.edu.pl
   DB_CONNECTION=mysql
   QUEUE_CONNECTION=database
   MCI_QUEUE_VIA_SCHEDULER=true
   MCI_REALTIME=poll
   CACHE_STORE=database
   SESSION_DRIVER=database
   MAIL_MAILER=smtp   # school SMTP / hosting mailbox
   MCI_ADMIN_EMAIL=...
   MCI_ADMIN_PASSWORD=...   # change after first login
   ```
4. **Install** (SSH):
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force      # safe to rerun: adds missing roles/permissions, missing starter groups and the first admin only if absent
   php artisan storage:link         # optional: only logos uploaded before the media update use it
   php artisan optimize
   ```
   Images (logos, covers, post images) live in `storage/app/media` and are served by the app at `/media/…`, so they work even where the host forbids symlinks. Make sure PHP's `upload_max_filesize` and `post_max_size` are at least `MCI_MEDIA_MAX_UPLOAD_KB` (default 8 MB); include `storage/app/media` in backups.
5. **Cron** (hosting panel → Cron jobs), every minute:
   ```
   * * * * * cd ~/mci && php artisan schedule:run >> /dev/null 2>&1
   ```
   The scheduler starts `queue:work --stop-when-empty --max-time=50` each
   minute (queues `alerts,push,default,mail`, alerts first).

   *If `schedule:run` cannot spawn processes on your host*, set
   `MCI_QUEUE_VIA_SCHEDULER=false` and add a second cron line:
   ```
   * * * * * cd ~/mci && php artisan queue:work --queue=alerts,push,default,mail --stop-when-empty --max-time=50 >> /dev/null 2>&1
   ```

### Updating

```bash
php artisan down
# upload new code + public/build
composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan db:seed --force
php artisan optimize
php artisan up
```

### Known limits of shared hosting

- **Latency**: queued work (e-mails now; push notifications later) starts
  within ~1 minute. Safety alerts (Step 5) will send their first push batch
  immediately after the HTTP response instead of waiting for cron.
- **Live updates** use Livewire polling (`MCI_POLL_SECONDS`, default 5 s).
  At ~70–150 users this is light load for a typical host.

## Profile B — VPS (optional, later)

Change only `.env`:

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
MCI_QUEUE_VIA_SCHEDULER=false
BROADCAST_CONNECTION=reverb   # after installing laravel/reverb
MCI_REALTIME=broadcast
```

…and run `php artisan queue:work --queue=alerts,push,default,mail` under
Supervisor (plus `php artisan reverb:start` if broadcasting is enabled). The
cron line for `schedule:run` stays.
