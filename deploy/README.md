# Speed.mn deployment (Ubuntu 22.04/24.04 + Apache2)

## First-time VPS setup

Install the runtime packages and clone the repository. Do not copy `.env` into
GitHub; create it only on the VPS.

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server git unzip php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath
sudo a2enmod rewrite headers ssl
sudo mkdir -p /var/www/awe
sudo git clone https://github.com/awesomemng688/SpeedMN.git /var/www/awe
cd /var/www/awe
sudo cp .env.example .env
sudo chown -R "$USER":www-data /var/www/awe
composer install --no-dev --optimize-autoloader
php artisan key:generate
# Edit .env and set production DB credentials, APP_KEY, APP_URL, and STEAM_API_KEY.
# Keep APP_DEBUG=false and use an HTTPS domain before opening the site publicly.
php artisan migrate --force
php artisan storage:link
sudo ln -s /var/www/awe /var/www/awe/current
sudo cp deploy/apache/speed.mn.conf /etc/apache2/sites-available/speed.mn.conf
sudo a2ensite speed.mn.conf
sudo systemctl reload apache2
sudo chown -R www-data:www-data storage bootstrap/cache
```

The existing `/var/www/awe/.env` must remain untracked and must never be
replaced by `git pull`.

## Existing VPS: one-time release setup

Keep `/var/www/awe/.env`, `/var/www/awe/storage`, `/var/www/awe/public/skins/img`,
`/var/www/awe/public/fastdl`, and `/var/www/awe/fastdl` in place. They remain
outside releases. Before the first release, create the compatibility symlink if
it does not already exist:

```bash
sudo ln -s /var/www/awe /var/www/awe/current
```

Update the active Apache virtual host so its `DocumentRoot` and matching
`<Directory>` path are `/var/www/awe/current/public`. Change the Supervisor
worker and scheduler commands/directories to `/var/www/awe/current`, then run
`sudo apachectl configtest`, reload Apache, and restart the Supervisor programs.
Review the existing virtual host before editing so TLS and certificate settings
are retained.

## Updating the VPS

After pushing tested changes to `main`, run:

```bash
sudo bash /var/www/awe/current/deploy/update.sh
```

The script fetches `origin/main` and builds an isolated release without merging
or cleaning the existing checkout. It shares the existing `.env` and `storage`,
links the image and FastDL directories above, runs migrations, atomically
switches `current`, rebuilds Laravel caches, and restarts Apache, PHP-FPM, and
configured Supervisor programs. It keeps the five newest releases; only old
code directories under `/var/www/awe/releases` are pruned. Existing checkout
changes and shared data are not removed. On a failed post-switch step it restores
the previous code link; migrations are not automatically rolled back.

The script discovers `/var/www/awe` when invoked through `current`; set `APP_DIR`
only when using a different deployment root. Ensure `.env` and `storage` exist
before running it. Check `/var/www/awe/storage/logs/laravel.log` after a failed
deploy.

Server checks are dispatched every 30 seconds; an open page refreshes the API
state every 15 seconds. Run exactly one scheduler driver. The simplest option
is cron (`crontab -e`):

```cron
* * * * * cd /var/www/awe/current && php artisan schedule:run >> /dev/null 2>&1
```

To receive Discord alerts when enabled servers go unpolled for 2 minutes or
queue jobs fail, set `DISCORD_ADMIN_WEBHOOK` in `/var/www/awe/.env`. The monitor
checks once per minute, suppresses duplicate state alerts, and posts recovery
notices. `php artisan speedmn:monitor --test` sends a harmless delivery-test
message without changing incident state or touching server/job data. Run it on
the VPS to verify the real webhook. Automated tests also verify stale and
failed-job payloads, de-duplication, and recovery without sending to Discord.

To post online CS2 and CS 1.6 player lists to a separate Discord channel, set
`DISCORD_SERVER_PLAYERS_WEBHOOK` in `.env`. The report runs every 10 minutes,
includes only fresh online server status, groups servers by game, and shows map
thumbnails and player counts without player names, scores, or play durations.
It updates its existing Discord embeds and disables mentions. Apply the config
with `php artisan config:cache`. Run `php artisan speedmn:discord-players` to
test it immediately.

Daily compressed MySQL/MariaDB backups run at 03:00 through Laravel's scheduler.
They are stored in `storage/app/private/backups` with owner-only permissions;
`DB_BACKUP_RETENTION_DAYS` controls retention (14 days by default). The command
requires `mysqldump` or `mariadb-dump` and `gzip`, and checks each archive with
`gzip -t`. Periodically test a restore into a disposable database, never the
live database:

```bash
sudo mariadb -e 'CREATE DATABASE speedmn_restore_test'
gzip -t /var/www/awe/current/storage/app/private/backups/CHOOSE_BACKUP.sql.gz
gzip -dc /var/www/awe/current/storage/app/private/backups/CHOOSE_BACKUP.sql.gz | sudo mariadb speedmn_restore_test
sudo mariadb -e 'DROP DATABASE speedmn_restore_test'
```

The deploy script verifies Laravel route boot and requests the configured
`APP_URL` before reporting success. Install `curl` on the VPS for this HTTP
smoke test.

Admin demo videos are stored on Laravel's private local disk and are only
streamed through authenticated admin routes. MP4 files up to 1 GiB play in the
browser. Configure the active PHP-FPM `php.ini` with
`upload_max_filesize=1024M`, `post_max_size=1050M`, `max_execution_time=300`, and
`max_input_time=300`, then restart PHP-FPM for large uploads.

Admin `.dem` uploads (up to 500 MiB) are parsed asynchronously into match,
round, kill, and player statistics. The parser is a separate Python tool, not a
video renderer; raw demos do not play in the browser. Install a pinned copy
outside the application releases and run it as the queue worker user:

```bash
sudo apt install -y python3 python3-venv git
sudo git clone https://github.com/faschmitz/cs2-demo-parser.git /opt/speedmn-cs2-demo-parser
sudo git -C /opt/speedmn-cs2-demo-parser checkout 319833c4f846a1f14fb2d2cf3d2cf7d604704a99
sudo python3 -m venv /opt/speedmn-cs2-demo-parser/.venv
sudo /opt/speedmn-cs2-demo-parser/.venv/bin/pip install -r /opt/speedmn-cs2-demo-parser/requirements.txt
sudo chown -R root:www-data /opt/speedmn-cs2-demo-parser
sudo chmod -R o-w /opt/speedmn-cs2-demo-parser
```

Set these in `/var/www/awe/.env`, then refresh config and restart the queue:

```dotenv
CS2_DEMO_PARSER_PYTHON=/opt/speedmn-cs2-demo-parser/.venv/bin/python
CS2_DEMO_PARSER_ENTRYPOINT=/opt/speedmn-cs2-demo-parser/main.py
CS2_DEMO_PARSER_TIMEOUT_SECONDS=1800
```

```bash
cd /var/www/awe/current
sudo -u www-data php artisan config:cache
sudo supervisorctl restart 'speedmn-worker:*'
```

Demo parsing uses its own long-running queue so large files do not block server
polling. Install the dedicated Supervisor worker once:

```bash
sudo cp /var/www/awe/current/deploy/supervisor/speedmn-demo-parser.conf /etc/supervisor/conf.d/speedmn-demo-parser.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status 'speedmn-demo-parser:*'
```

Server status history is pruned daily at 03:30. `SERVER_STATUS_RETENTION_DAYS`
is clamped to 30–90 days and defaults to 90; a timestamp index and batched
deletes limit cleanup work.

Audit entries are pruned daily after 365 days by default. Set
`ADMIN_AUDIT_RETENTION_DAYS` in `.env` to change that period. After environment
changes, refresh cached config and restart workers:

```bash
cd /var/www/awe/current
php artisan config:cache
sudo supervisorctl restart speedmn-worker:*
sudo supervisorctl restart speedmn-scheduler
```

Alternatively, run Laravel's scheduler under Supervisor instead of adding the
cron entry:

```bash
sudo cp deploy/supervisor/speedmn-scheduler.conf /etc/supervisor/conf.d/speedmn-scheduler.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status speedmn-scheduler
```

### Keep queue monitoring running

Install Supervisor once:

```bash
sudo apt install -y supervisor
sudo cp deploy/supervisor/speedmn-worker.conf /etc/supervisor/conf.d/speedmn-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart speedmn-worker:*
sudo supervisorctl status speedmn-worker:*
```

The worker runs as `www-data`, processes the database queue, and restarts
automatically after a crash or reboot. Check its output with:

```bash
sudo tail -f /var/www/awe/storage/logs/worker.log
```

Administrator routes require TOTP setup on first access. Store the one-time
recovery codes outside the server; failed-job retry in the admin panel is
restricted to `PollServer` jobs.

The admin navigation includes server management, user-role management,
failed-job retry/cleanup, and an audit log. The server page reports
scheduler/worker heartbeats, recent failed jobs, stale polls, each server's
last successful query, and its latest A2S error. Schema changes are applied by
`deploy/update.sh`; the first administrator is assigned by setting
`users.is_admin` for a trusted Steam account.

## Check live server polling

Confirm the scheduler and worker use this same app directory. To test polling
immediately, dispatch checks and drain the current queue:

```bash
cd /var/www/awe/current
php artisan schedule:list
sudo -u www-data php artisan speedmn:poll
sudo -u www-data php artisan queue:work database --stop-when-empty
sudo -u www-data php artisan queue:failed
tail -f /var/www/awe/storage/logs/laravel.log
```

Each poll writes a new status row. If its timestamp updates but the server is
still offline, verify that outbound UDP queries to the configured game-server
ports are allowed and that the server's A2S query is enabled. If timestamps
remain stale, check that the cron entry and Supervisor worker are running and
point to `/var/www/awe/current`.

Normal deployment does not need Node.js or a Vite build: the main Speed.mn pages
serve CSS and JavaScript directly from `public/`. The Laravel welcome view uses
Vite; build assets only if that view is used or its Vite-managed files change.

For HTTPS:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d speed.mn -d www.speed.mn
```
