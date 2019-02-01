FROM php:7.2-cli-alpine

COPY src /code/src
COPY vendor /code/vendor
COPY bitbucket-reviews /code

ENTRYPOINT ["/code/bitbucket-reviews"]
