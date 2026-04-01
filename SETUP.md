# Cyber Score — Setup Guide

## 1 — System dependencies

Run as **root** (or with `sudo`):

```bash
apt update && apt install -y \
  git curl unzip sqlite3 \
  php8.4 php8.4-cli php8.4-fpm php8.4-sqlite3 \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  nginx

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

## 2 — Create a dedicated app user

```bash
# Create user and home directory
useradd -m -s /bin/bash cyberapp

# Allow the user to write to the web root
mkdir -p /var/www/cyber-score
chown cyberapp:cyberapp /var/www/cyber-score

# Allow www-data (nginx/php-fpm) to read app files
usermod -aG cyberapp www-data

# Set a password for the user (required for Github Actions to work)
passwd cyberapp
```

## 3 — Clone and configure

**Switch to the app user for all remaining steps:**

```bash
su - cyberapp
```

```bash
git clone https://github.com/tony1661/cyber-score /var/www/cyber-score
cd /var/www/cyber-score

cp .env.example .env
```

Edit `.env` and fill in:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

XPOSEDORNOT_API_KEY=your-key-here

MAIL_HOST=mail.smtp2go.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS="assessment@yourdomain.com"

SALES_REP_EMAIL=sales@yourdomain.com
DISCOVERY_CALL_URL=https://yourdomain.com/discovery-call/
```

## 4 — Install & build

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

npm ci
npm run build
```

## 5 — Storage permissions

```bash
# Still as cyberapp — own the storage dirs
chmod -R 775 /var/www/cyber-score/storage \
              /var/www/cyber-score/bootstrap/cache \
              /var/www/cyber-score/database

# php-fpm runs as www-data, which was added to the cyberapp group above
# so it can read/write storage without needing root
```

Exit back to root:

```bash
exit
```

## 6 — PHP-FPM pool

Create a dedicated pool so php-fpm runs as `cyberapp` instead of `www-data`:

```ini
# /etc/php/8.4/fpm/pool.d/cyberapp.conf
[cyberapp]
user  = cyberapp
group = cyberapp
listen = /run/php/php8.4-cyberapp.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

```bash
systemctl restart php8.4-fpm
```

## 7 — Nginx config

```nginx
# /etc/nginx/sites-available/cyber-score
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/cyber-score/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Use the cyberapp pool socket if you created one (step 6),
        # otherwise fall back to the default www-data socket
        fastcgi_pass unix:/run/php/php8.4-cyberapp.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/cyber-score /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 8 — Cache for production

```bash
su - cyberapp -c "cd /var/www/cyber-score && php artisan config:cache && php artisan route:cache && php artisan view:cache"
```

## 9 — HTTPS with Let's Encrypt

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d yourdomain.com
```

---

## 10 — Auto-deploy with GitHub Actions

Every push to `main` will automatically deploy to your server via the included `.github/workflows/deploy.yml`.

### Step 1 — Generate a deploy key on the server

```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N ""
cat ~/.ssh/deploy_key.pub >> ~/.ssh/authorized_keys
cat ~/.ssh/deploy_key   # copy this into the SSH_KEY secret below
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

### Step 2 — Add secrets to your GitHub repo

Go to your repo → **Settings → Secrets and variables → Actions → New repository secret** and add:

| Secret | Value |
|---|---|
| `SSH_HOST` | your server IP or domain |
| `SSH_USER` | `cyberapp` |
| `SSH_KEY` | contents of `~/.ssh/deploy_key` (the private key) |
| `SSH_PORT` | `22` (or your custom port) |

### Step 3 — Push to trigger a deploy

```bash
git push origin main
```

Watch the run under the **Actions** tab in your GitHub repo.

---

## 11 — Manual Deploy / Updating the app

```bash
su - cyberapp
cd /var/www/cyber-score

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
