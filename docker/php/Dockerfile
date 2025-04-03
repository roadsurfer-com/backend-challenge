FROM php:8.2-fpm-alpine3.18

ARG USER=www-data
ARG GROUP=www-data

ARG APP_ENV
ENV APP_ENV=${APP_ENV}

COPY /docker/php/conf.d/ $PHP_INI_DIR/conf.d/
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN if [ "${APP_ENV}" = "dev" ] || [ "${APP_ENV}" = "test" ]; then \
        ln -sf $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini; \
        ln -sf $PHP_INI_DIR/conf.d/custom.ini-development $PHP_INI_DIR/conf.d/custom.ini; \
    else \
        ln -sf $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini; \
        ln -sf $PHP_INI_DIR/conf.d/custom.ini-production $PHP_INI_DIR/conf.d/custom.ini; \
    fi; \
    install-php-extensions opcache @composer pdo_mysql; \
    if [ "${APP_ENV}" = "dev" ]; then \
        install-php-extensions xdebug; \
    fi; \
    rm /usr/local/bin/install-php-extensions;

WORKDIR /app

RUN chown ${USER}:${GROUP} /app

USER ${USER}

COPY --chown=${USER} ./bin /app/bin
COPY --chown=${USER} ./config /app/config
COPY --chown=${USER} ./public /app/public
COPY --chown=${USER} ./tests /app/tests
COPY --chown=${USER} ./src /app/src
COPY --chown=${USER} ./.env /app/.env
COPY --chown=${USER} ./.env.staging /app/.env.staging
COPY --chown=${USER} ./.env.prod /app/.env.prod
COPY --chown=${USER} ./composer.json /app/composer.json
COPY --chown=${USER} ./composer.lock /app/composer.lock
COPY --chown=${USER} ./symfony.lock /app/symfony.lock
COPY --chown=${USER} ./phpcs.xml.dist /app/phpcs.xml.dist
COPY --chown=${USER} ./phpunit.xml.dist /app/phpunit.xml.dist

RUN mkdir -p var/cache var/log \
    && chown -R ${USER}:${GROUP} var

RUN if [ "${APP_ENV}" = "prod" ]; then \
	composer install --prefer-dist --no-dev --no-progress --optimize-autoloader; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	else \
    composer install --prefer-dist --no-progress; \
    composer dump-autoload --classmap-authoritative; \
    fi;


