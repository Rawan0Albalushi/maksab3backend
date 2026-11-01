FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

FROM node:22-bookworm-slim AS assets

WORKDIR /app

ENV NODE_ENV=development

COPY package.json package-lock.json ./
RUN npm ci --include=dev

COPY . .
# The storefront entry imports the Builder add-on. That module is not part of
# this repository, so give Vite a small i18n stand-in when it is absent.
RUN if [ ! -f Modules/Builder/resources/js/i18n.js ] && [ ! -f Modules/Builder/resources/js/i18n.jsx ]; then \
      mkdir -p Modules/Builder/resources/js && \
      printf '%s\n' \
        "import i18n from 'i18next';" \
        "import { initReactI18next } from 'react-i18next';" \
        "i18n.use(initReactI18next).init({ lng: 'en', fallbackLng: 'en', resources: { en: { translation: {} } }, interpolation: { escapeValue: false } });" \
        "export default i18n;" \
        > Modules/Builder/resources/js/i18n.js; \
    fi \
    && npx vite build \
    && rm -f build \
    && ln -s public/build build

FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        default-mysql-client \
        unzip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/site.conf.template
COPY docker/start.sh /start.sh

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chmod +x /start.sh \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8080

CMD ["/start.sh"]
