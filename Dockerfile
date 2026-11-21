# syntax=docker/dockerfile:1
# ============================================================
# WBPMS — Dockerfile
# PHP 8.5 + Apache (Debian Bookworm)
# ============================================================
FROM php:8.5-apache-bookworm

# ── System packages ─────────────────────────────────────────
# BuildKit retains downloaded .deb packages between builds, avoiding repeated
# downloads from the Debian mirror during a rebuild.
RUN --mount=type=cache,id=wbpms-apt-cache,target=/var/cache/apt,sharing=locked \
    apt-get -o Acquire::ForceIPv4=true -o Acquire::Retries=3 update \
    && apt-get -o Acquire::ForceIPv4=true -o Acquire::Retries=3 install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libxml2-dev \
        libonig-dev \
        zip \
        unzip \
        curl \
        git \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ───────────────────────────────────────────
# PhpSpreadsheet requires: ctype, dom, fileinfo, gd, iconv, libxml, mbstring
# dompdf requires: gd, mbstring, xml (dom, libxml via xml)
# WBPMS requires: pdo_mysql, bcmath, opcache, intl, zip
# PHP 8.5's base image already provides DOM, Lexbor, XML, mbstring, fileinfo,
# PDO, and OPcache. Build only the extensions that need system libraries.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        zip \
        gd \
        bcmath \
        intl

# ── Apache: enable mod_rewrite, set document root ───────────
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides in document root
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# ── PHP config tweaks ───────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/wbpms.ini

# ── Composer ────────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Application code ────────────────────────────────────────
WORKDIR /var/www/html

# Install production dependencies in their own layer. This keeps application-code
# changes from needlessly downloading dependencies again and ensures the lock file
# determines the deployed dependency set.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Application code. .dockerignore excludes developer-only material and local
# credentials from this build context.
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

# ── Storage directory permissions ───────────────────────────
RUN mkdir -p storage/private \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# ── Entrypoint ───────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
