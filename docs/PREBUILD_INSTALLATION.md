# Prebuild Installation Guide

The prebuild package (`audinary-prebuild.zip`) contains a fully compiled version of Audinary. You do **not** need Node.js to run it.

## Requirements

- PHP 8.4+
- Composer
- **Nginx** (required — see note below)
- PostgreSQL 18
- FFmpeg and MediaInfo

> **Why Nginx?** Audinary relies on Nginx's `X-Accel-Redirect` header for efficient audio streaming. Nginx handles the file delivery directly from disk without loading it into PHP memory, which is essential for serving large audio files. Apache does not support this mechanism, so **only Nginx is supported**.

## Installation

### 1. Download

Download the latest `audinary-prebuild.zip` from the [Releases page](https://github.com/audinary-org/audinary/releases).

### 2. Extract

```bash
unzip audinary-prebuild.zip
cd audinary
```

### 3. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configure

```bash
cp src/config_sample.php var/config/config.php
```

Edit `var/config/config.php` and set your database credentials and JWT secret.

### 5. Set permissions

```bash
chown -R www-data:www-data var/
chmod -R 755 var/
```

### 6. Configure Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /path/to/audinary/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Internal location for X-Accel-Redirect audio streaming
    location /internal-music/ {
        internal;
        alias /path/to/your/music/;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 7. Restart Nginx

```bash
sudo systemctl restart nginx
```

## Comparison with Docker

| | Prebuild | Docker |
|---|---|---|
| Node.js required | No | No |
| Frontend | Pre-compiled | Pre-compiled |
| Database setup | Manual | Automatic |
| Web server | Manual | Included (Nginx) |
| Recommended for | Existing server setups | New installations |

For Docker installation, see the [README](../README.md).

## License

Audinary is licensed under [AGPL-3.0](https://www.gnu.org/licenses/agpl-3.0.html).
