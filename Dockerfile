FROM composer:latest as build_stage

WORKDIR /code

COPY composer.json /code
COPY composer.lock /code

RUN /usr/bin/composer install

FROM php:7.2-cli-alpine

COPY --from=build_stage /code/vendor /code/vendor
COPY src /code/src
COPY bitbucket-reviews /code

ENTRYPOINT ["/code/bitbucket-reviews"]
