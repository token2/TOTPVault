# ─────────────────────────────────────────────────────────────────────────────
# TOTPVault — Production Dockerfile
# Base: Official PHP 8.2 with Apache on Debian Bookworm
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-apache-bookworm

LABEL maintainer="TOTPVault" \
      description="Self-hosted TOTP manager with AES-256-GCM encryption"

# ── 1. Enable Apache modules ────────────────────────────────────────────────
# mod_rewrite is required for .htaccess URL routing to index.php
RUN a2enmod rewrite headers

# ── 2. Install PHP extensions ────────────────────────────────────────────────
# pdo_mysql  — database access via PDO
# mysqli     — fallback MySQL driver (some libraries expect it)
# openssl    — bundled with php:8.2, but we verify it's present
# curl       — required by sendMail() for MailerSend API calls
RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && php -m | grep -qi openssl \
    && php -m | grep -qi curl

# ── 3. Set Apache DocumentRoot to the project directory ─────────────────────
# The app expects index.php at the web root, with .htaccess rewriting.
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri "s|/var/www/html|${APACHE_DOCUMENT_ROOT}|g" \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf

# ── 4. Copy custom Apache vhost configuration ───────────────────────────────
# Ensures AllowOverride All so .htaccess files work, and denies direct
# browsing of protected directories as a defense-in-depth layer.
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# ── 5. Copy application files ───────────────────────────────────────────────
COPY . /var/www/html/

# ── 6. Create sessions directory with proper permissions ─────────────────────
# www-data (Apache user) needs write access for PHP session files.
# The .htaccess inside sessions/ blocks direct HTTP access.
RUN mkdir -p /var/www/html/sessions \
    && chown www-data:www-data /var/www/html/sessions \
    && chmod 750 /var/www/html/sessions

# ── 7. Set secure permissions on config directory ────────────────────────────
# config/ contains secrets at runtime; restrict access to www-data only.
RUN chown -R www-data:www-data /var/www/html/config \
    && chmod 750 /var/www/html/config

# ── 8. Ensure all .htaccess files are present and readable ──────────────────
RUN chmod 644 /var/www/html/.htaccess \
    /var/www/html/config/.htaccess \
    /var/www/html/src/.htaccess \
    /var/www/html/sessions/.htaccess \
    /var/www/html/templates/.htaccess

# ── 9. Configure PHP for production ─────────────────────────────────────────
# Disable display_errors in the container (log them instead).
# Use the sessions/ directory for session files.
RUN { \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /dev/stderr'; \
        echo 'session.save_path = /var/www/html/sessions'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = Lax'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/totpvault.ini

EXPOSE 80

# Apache runs in the foreground by default via the base image entrypoint.
