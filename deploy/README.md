echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
# Speed.mn deployment (Debian 13/Ubuntu 24.04 + Apache2)

## First-time VPS setup

Install the runtime packages and clone the repository. Do not copy `.env` into
GitHub; create it only on the VPS.

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server git unzip php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath
sudo a2enmod rewrite headers ssl
sudo mkdir -p /var/www/awe
sudo git clone https://github.com/awesomemng688/SpeedMNAdmin.git /var/www/awe
cd /var/www/awe
sudo cp .env.example .env
sudo chown -R "$USER":www-data /var/www/awe
composer install --no-dev --optimize-autoloader
php artisan key:generate
# Edit .env and set production DB credentials, APP_KEY, APP_URL, and STEAM_API_KEY.
# Keep APP_DEBUG=false and use an HTTPS domain before opening the site publicly.
php artisan migrate --force
php artisan storage:link
sudo cp deploy/apache/speed.mn.conf /etc/apache2/sites-available/speed.mn.conf
sudo a2ensite speed.mn.conf
sudo systemctl reload apache2
sudo chown -R www-data:www-data storage bootstrap/cache
```

The existing `/var/www/awe/.env` must remain untracked and must never be
replaced by `git pull`.

## Updating the VPS

Run this from the VPS after pushing tested changes to `main`:

```bash
cd /var/www/awe
sudo -u www-data git config --local --add safe.directory /var/www/awe
sudo bash deploy/update.sh
```

The script refuses to continue when local tracked or staged changes exist,
uses fast-forward-only updates, runs migrations, rebuilds Laravel caches,
preserves `.env`, and reloads Apache/PHP-FPM. If a deployment fails, inspect
`storage/logs/laravel.log`, fix the source, push a new commit, and run it again.
The update script detects the application root from its own location; set
`APP_DIR` only when deploying from a different checkout path.

Server checks are dispatched every 30 seconds; an open page refreshes the API
state every 15 seconds. Run exactly one scheduler driver. The simplest option
is cron (`crontab -e`):

```cron
* * * * * cd /var/www/awe && php artisan schedule:run >> /dev/null 2>&1
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

The admin server page reports scheduler/worker heartbeats, recent failed jobs,
stale polls, each server's last successful query, and its latest A2S error.

## Check live server polling

Confirm the scheduler and worker use this same app directory. To test polling
immediately, dispatch checks and drain the current queue:

```bash
cd /var/www/awe
php artisan schedule:list
sudo -u www-data php artisan speedmn:poll
sudo -u www-data php artisan queue:work database --stop-when-empty
sudo -u www-data php artisan queue:failed
tail -f storage/logs/laravel.log
```

Each poll writes a new status row. If its timestamp updates but the server is
still offline, verify that outbound UDP queries to the configured game-server
ports are allowed and that the server's A2S query is enabled. If timestamps
remain stale, check that the cron entry and Supervisor worker are running and
point to `/var/www/awe`.

Normal deployment does not need Node.js or a Vite build: the main Speed.mn pages
serve CSS and JavaScript directly from `public/`. The Laravel welcome view uses
Vite; build assets only if that view is used or its Vite-managed files change.

For HTTPS:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d speed.mn -d www.speed.mn
```
