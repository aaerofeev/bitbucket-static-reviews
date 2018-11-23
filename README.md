## Интеграция checkstyle-format в stash/bitbucket
### Checkstyle-format integration into stash / bitbucket

On English see below

Читает формат checkstyle и пишет комментарии к pull-request в stash/bitbucket

### Возможности

- Публикация комментариев на основе отчетов checkstyle
- Режимы работы инспектора только с измененным кодом или контекстом
- Группировка ошибок
- Ограничения по важности
- Ограничения по количеству комментариев: общий, на файл, на группу
- Реакция на исправленные ошибки (геймификация)
- Игнорирование по паттерну ошибки, или по файлу
- Отправка статистики в statsd

![Fixed](https://github.com/aaerofeev/bitbucket-static-reviews/blob/master/docs/Selection_006.png?raw=true)

![Group](https://github.com/aaerofeev/bitbucket-static-reviews/blob/master/docs/Selection_007.png?raw=true)

### Как работает

Данные которые требуются: проект, репозиторий, ветка.
Комментарии будут публиковаться в пулл-реквест со статусом OPEN, если такой имеется.

1. Передается результат git dff origin/master <BRANCH> или разница ревизий
2. Последовательно передаются результат работы статических анализаторов в формате checkstyle
3. Производится анализ и рассылка комментариев

### On English

Reads the checkstyle format and writes comments to the pull-request in stash / bitbucket.

### Features

- Post comments based on checkstyle reports
- Modes of work of the inspector only with a modified code or context
- Errors grouping
- Limitations on severity
- Limitations on the number of comments: total, per file, per group
- Reaction to fixed bugs (gamification)
- Ignoring by the pattern of the error, or by file
- Sending statistics to statsd

### How does it work

Data that is required: project, repository, branch.
Comments will be posted to pull-request with the status OPEN, if any.

1. The result is transmitted git dff origin/master <BRANCH> or the difference of revisions
2. The result of the work of static analyzers in the format of checkstyle is consistently transmitted.
3. Analyzing and sending comments

### API Stash

https://docs.atlassian.com/bitbucket-server/rest/5.15.0/bitbucket-rest.html?utm_source=%2Fstatic%2Frest%2Fbitbucket-server%2Flatest%2Fbitbucket-rest.html&utm_medium=301#idm45622371276656

https://developer.atlassian.com/server/bitbucket/reference/rest-api/

### Config

Default name - .config.php 

```php
<?php

use BitbucketReviews\Config;

/**
* @see \BitbucketReviews\Config
 */
return [
    // Stash API config
    // https://<hostname>/projects/<project>/repos/<repository>/browse
    'stash'    => [
        'url'         => 'https://bitbucket.org',
        // @see https://confluence.atlassian.com/bitbucketserver/personal-access-tokens-939515499.html
        'accessToken' => '<secret-token-read-perms>',
        'project'     => '<project>',
        'repository'  => '<repository>',
        'debug'       => false,
    ],
    'analyzer' => [
        'inspect'       => Config::INSPECT_CONTEXT,
        'ignoredText'   => [
            'eslint.rules.radix',
        ],
        'ignoredFiles'  => [
            'composer.json',
            'composer.lock',
        ],
        'limit'         => Config::NO_LIMIT,
        'limitPerFile'  => Config::NO_LIMIT,
        'limitPerGroup' => Config::NO_LIMIT,
    ],
    // Optional
    'statsd'   => [
        'host'      => '<statsd-host>',
        'port'      => '<statsd-port>',
        'namespace' => 'myApp.code-analyze',
    ],
];
```

### Example usage

```
/vendor/bin/bitbucket-reviews run refs/head/MY_BRANCH git-diff.txt \
    --config config.php \
    --checkstyle eslint.xml:/code/base \
    --checkstyle phan.xml \
    --checkstyle phpstan.xml
```

```
run [options] [--] <branch> <diff>

Arguments:
  branch                         Branch name, fully path refs/heads/master
  diff                           git diff output file path

Options:
      --diff-vsc[=DIFF-VSC]      git diff output file path [default: "git"]
  -c, --checkstyle[=CHECKSTYLE]  checkstyle file path <filename>:<root> (multiple values allowed)
  -k, --config[=CONFIG]          config file [default: ".config.php"]
```

### Usage in complex

```bash
#!/usr/bin/env bash

# Example: script.sh refs/head/BRANCH 10

set -x

function join { local IFS="$1"; shift; echo "$*"; }

BRANCH_NAME=$1
CONTEXT_LINES=$2
CODE_PATH=/code

### DIFF for analyzer - diff.txt

git diff -U${CONTEXT_LINES:-10} origin/master...${BRANCH_NAME} > diff.txt

### ESLINT - eslint.xml ###

JS_IMAGE=yarn:latest
ESLINT_FILES=$(git diff --name-only origin/master...${BRANCH_NAME} | grep -E "\.(js|vue)$")

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint=${CODE_PATH}/node_modules/.bin/eslint \
    --interactive \
    ${JS_IMAGE} \
    ${ESLINT_FILES} -o eslint.xml -f checkstyle

### PHAN - phan.xml ###

PHP_IMAGE=php7-cli:latest
PHAN_FILES=$(join , $(git diff --name-only origin/master...${BRANCH_NAME} | grep -E "\.php$"))

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint ${CODE_PATH}/vendor/bin/phan \
    --interactive \
    ${PHP_IMAGE} \
    -k ${CODE_PATH}/.phan.php -m checkstyle -o phan.xml --include-analysis-file-list ${PHAN_FILES}

### TO STASH ###

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint ${CODE_PATH}/vendor/bin/bitbucket-reviews \
    --interactive \
    ${PHP_IMAGE} \
    run refs/head/${BRANCH_NAME} diff.git -k .config.php -c phan.xml -c eslint.xml:/code
```
