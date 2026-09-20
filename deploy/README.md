# Speed.mn deployment (Debian 13/Ubuntu 24.04 + Apache2)

## First-time VPS setup

Install the runtime packages and clone the repository. Do not copy `.env` into
GitHub; create it only on the VPS.

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server git unzip php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath
sudo a2enmod rewrite headers ssl
sudo mkdir -p /var/www/speed.mn
sudo git clone https://github.com/awesomemng688/SpeedMNAdmin.git /var/www/speed.mn
cd /var/www/speed.mn
sudo cp .env.example .env
sudo chown -R "$USER":www-data /var/www/speed.mn
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

The existing `/var/www/speed.mn/.env` must remain untracked and must never be
replaced by `git pull`.

## Updating the VPS

Run this from the VPS after pushing tested changes to `main`:

```bash
cd /var/www/speed.mn
sudo -u www-data git config --local --add safe.directory /var/www/speed.mn
sudo bash deploy/update.sh
```

The script refuses to continue when local tracked or staged changes exist,
uses fast-forward-only updates, runs migrations, rebuilds Laravel caches,
preserves `.env`, and reloads Apache/PHP-FPM. If a deployment fails, inspect
`storage/logs/laravel.log`, fix the source, push a new commit, and run it again.

Add the scheduler to `crontab -e`:

```cron
* * * * * cd /var/www/speed.mn && php artisan schedule:run >> /dev/null 2>&1
```

Build assets on a machine with Node.js using `npm ci && npm run build`; deploy `public/build`. Node.js is not required at runtime. For HTTPS:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d speed.mn -d www.speed.mn
```
