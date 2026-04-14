# DevOps Guide

## Environments

| Environment | URL | Purpose | Branch |
|---|---|---|---|
| Local | `http://localhost` | Development | any |
| Staging | `https://staging.yourdomain.com` | QA, demo | `develop` |
| Production | `https://yourdomain.com` | Live users | `main` |

---

## Local Development (Docker / Laravel Sail)

```bash
# First-time setup
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link

# Frontend
npm install
npm run dev

# Queue worker (Horizon in a separate terminal)
./vendor/bin/sail artisan horizon

# Useful commands
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan telescope          # http://localhost/telescope
./vendor/bin/sail artisan horizon            # http://localhost/horizon
```

### `docker-compose.yml` Services
- `laravel.test` — PHP 8.3 + Nginx
- `mysql` — MySQL 8.0
- `redis` — Redis 7
- `mailpit` — Email testing UI at `http://localhost:8025`
- `meilisearch` — Search (Phase 2)

---

## Server Setup (Production — Ubuntu 22.04)

### Required Software
```bash
# Nginx
sudo apt install nginx

# PHP 8.3
sudo add-apt-repository ppa:ondrej/php
sudo apt install php8.3-fpm php8.3-mysql php8.3-redis php8.3-gd \
  php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs

# Redis
sudo apt install redis-server

# Supervisor
sudo apt install supervisor
```

### Nginx Configuration
```nginx
# /etc/nginx/sites-available/visionaryai

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    root /var/www/visionaryai/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Security headers (see Security.md)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht { deny all; }
    location = /favicon.ico { log_not_found off; }
    location = /robots.txt { log_not_found off; }
}
```

### Supervisor Configuration
```ini
# /etc/supervisor/conf.d/visionaryai-worker.conf

[program:visionaryai-horizon]
process_name=%(program_name)s
command=php /var/www/visionaryai/current/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/horizon.log

[program:visionaryai-scheduler]
process_name=%(program_name)s
command=php /var/www/visionaryai/current/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/scheduler.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/ci.yml

name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        options: --health-cmd="mysqladmin ping" --health-interval=10s
      redis:
        image: redis:7
        options: --health-cmd="redis-cli ping"

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, mysql, redis
          coverage: xdebug

      - name: Install PHP dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Install Node dependencies
        run: npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Copy .env
        run: cp .env.testing .env

      - name: Generate key
        run: php artisan key:generate

      - name: Run PHP linting (Pint)
        run: ./vendor/bin/pint --test

      - name: Run tests
        run: php artisan test --parallel --coverage-min=70
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: ${{ job.services.mysql.ports[3306] }}
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

  deploy-staging:
    needs: test
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to staging
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/visionaryai-staging
            git pull origin develop
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            npm ci && npm run build
            sudo supervisorctl restart visionaryai-horizon

  deploy-production:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production   # Requires manual approval in GitHub
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to production
        # Same as staging but targets production server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/visionaryai
            php artisan down --retry=60
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            npm ci && npm run build
            php artisan queue:restart
            php artisan up
```

---

## Required GitHub Secrets

```
STAGING_HOST, STAGING_USER, STAGING_SSH_KEY
PROD_HOST, PROD_USER, PROD_SSH_KEY
```

---

## Monitoring

### Laravel Horizon
- URL: `yourdomain.com/horizon` (admin-protected)
- Monitor: queue throughput, failed jobs, wait time per queue

### Sentry
```php
// config/sentry.php
'dsn' => env('SENTRY_LARAVEL_DSN'),
'traces_sample_rate' => 0.1,  // 10% of requests traced
'profiles_sample_rate' => 0.1,
```

### Health Check Endpoint
```php
// Route: GET /health
// Returns 200 if all systems operational
{
  "status": "ok",
  "database": "ok",
  "redis": "ok",
  "queue": "ok",
  "storage": "ok"
}
```

### Uptime Monitoring
- UptimeRobot: ping `GET /health` every 5 minutes
- Alert via email + Slack on downtime

---

## Backup Strategy

```bash
# Database backup (daily, via cron)
mysqldump -u root -p visionaryai | gzip > /backups/db-$(date +%Y%m%d).sql.gz

# Retain: 7 daily, 4 weekly, 3 monthly
# Upload to S3 backup bucket
aws s3 cp /backups/db-$(date +%Y%m%d).sql.gz s3://visionaryai-backups/
```

S3 generated images are already durable (11 nines). Enable S3 versioning for the media bucket.

---

## SSL Certificate Renewal

```bash
# Auto-renews via certbot cron
sudo certbot renew --quiet
# Runs every 12 hours, renews when < 30 days remaining
```
