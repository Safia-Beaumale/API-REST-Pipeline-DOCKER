FROM composer:2.8 AS builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.2-fpm-alpine AS production

RUN addgroup -S appgroup && adduser -S appuser -G appgroup

RUN apk add --no-cache \
        libpq \
        icu-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpq-dev \
        icu-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql intl opcache \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=builder --chown=appuser:appgroup /app/vendor      ./vendor
COPY --from=builder --chown=appuser:appgroup /app/src         ./src
COPY --from=builder --chown=appuser:appgroup /app/config      ./config
COPY --from=builder --chown=appuser:appgroup /app/public      ./public
COPY --from=builder --chown=appuser:appgroup /app/migrations  ./migrations
COPY --from=builder --chown=appuser:appgroup /app/bin         ./bin
COPY --from=builder --chown=appuser:appgroup /app/composer.json \
                                             /app/composer.lock \
                                             /app/symfony.lock ./

RUN mkdir -p var/cache var/log \
    && chown -R appuser:appgroup var

USER appuser

EXPOSE 9000

ENV APP_ENV=prod

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm"]
