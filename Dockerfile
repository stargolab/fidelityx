# imagem de producao do fidelityx: apache + php 8.3, document root em public/.
# a configuracao vem toda de variaveis de ambiente (nao ha .env dentro da imagem). ver docs/operacao.md

# etapa 1: dependencias de producao (sem phpunit), nas versoes do composer.lock
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

# etapa 2: a aplicacao
FROM php:8.3-apache

# pdo_mysql para a aplicacao; cliente do mysql para o backup (bin/backup.php usa o mysqldump)
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql

# php.ini de producao (erro nunca aparece na tela, vai pro log) + ajustes do projeto
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/fidelityx.ini"

# apache servindo so a pasta public/ e sem anunciar versao
COPY docker/apache.conf /etc/apache2/conf-available/fidelityx.conf
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enconf fidelityx \
    && mkdir -p /var/lib/php/sessions /var/backups/fidelityx \
    && chown www-data:www-data /var/lib/php/sessions \
    && chmod 700 /var/lib/php/sessions /var/backups/fidelityx

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY bin ./bin
COPY database ./database
COPY public ./public
COPY src ./src
COPY views ./views

# a pagina de privacidade so responde 200 com o banco no ar (o index.php conecta antes de rotear)
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/index.php?url=privacy") === false ? 1 : 0);'

EXPOSE 80
