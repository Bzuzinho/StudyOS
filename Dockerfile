FROM node:22-alpine AS math-recognition
RUN apk add --no-cache curl
WORKDIR /build
COPY ink-recognition/package.json ./
RUN npm install --no-audit --no-fund
COPY ink-recognition/src.js ./
RUN npm run build
RUN mkdir -p dist/onnx dist/ink-models \
 && cp node_modules/onnxruntime-web/dist/*.wasm dist/onnx/ \
 && for file in encoder_int8.onnx decoder_int8.onnx vocab.json; do \
      curl -fL --retry 3 --silent --show-error \
      "https://github.com/kimseungdae/ink-on/releases/download/v0.1.0/$file" \
      -o "dist/ink-models/$file" || exit 1; \
    done

FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev libxml2-dev \
    && docker-php-ext-install pdo_pgsql intl pcntl zip dom \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts
COPY . .
COPY --from=math-recognition /build/dist/ /app/public/vendor/
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && printf "upload_max_filesize=25M\npost_max_size=30M\nmemory_limit=512M\nmax_execution_time=120\n" > /usr/local/etc/php/conf.d/studyos.ini \
    && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev
ENV APP_ENV=production
ENV APP_DEBUG=false
CMD sh -c "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
