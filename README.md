## Интеграция checkstyle-format в stash/bitbucket
### Checkstyle-format integration into stash / bitbucket

[See English version below](#in-english)

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

#### Автоматическая пометка "Исправлено" / "Fixed" mark added automatically

![Fixed](https://github.com/aaerofeev/bitbucket-static-reviews/blob/master/docs/Selection_006.png?raw=true)


#### Группировка ошибок / Error grouping

![Group](https://github.com/aaerofeev/bitbucket-static-reviews/blob/master/docs/Selection_007.png?raw=true)

### Как работает

Данные которые требуются: проект, репозиторий, ветка.
Комментарии будут публиковаться в пулл-реквест со статусом OPEN, если такой имеется.

1. Передается результат `git diff origin/master <BRANCH>` или разница ревизий
2. Последовательно передаются результаты работы статических анализаторов в формате checkstyle
3. Производится анализ и рассылка комментариев

### In English

This project reads checkstyle format reports and writes comments to stash / bitbucket pull-requests.

### Features

- Posts comments based on checkstyle reports
- Can be configured to check the context or modified code only
- Error grouping
- Severity limitation
- Limit the number of comments: total, per file, or per group
- Reaction to fixed bugs (gamification)
- Ignoring by error patterns and by filenames
- Sending statistics to statsd

### How does it work

Required options: project, repository, and branch names.
Comments will be posted to a pull-request with an OPEN status, if any.

1. The results of `git diff origin/master <BRANCH>` or the revisions difference is transmitted to analyzers
2. Static analyzers' reports are collected
3. The comments are analyzed and sent to Bitbucket API

### Bitbucket API

https://docs.atlassian.com/bitbucket-server/rest/5.15.0/bitbucket-rest.html?utm_source=%2Fstatic%2Frest%2Fbitbucket-server%2Flatest%2Fbitbucket-rest.html&utm_medium=301#idm45622371276656

https://developer.atlassian.com/server/bitbucket/reference/rest-api/

### Config

Default configuration filename is `.config.php`

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

### Usage example

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
  branch                         Branch name, full path like `refs/heads/master`
  diff                           git diff output file path

Options:
      --diff-vsc[=DIFF-VSC]      git diff output file path [default: "git"]
  -c, --checkstyle[=CHECKSTYLE]  checkstyle file path <filename>:<root> (multiple values allowed)
  -k, --config[=CONFIG]          config file [default: ".config.php"]
```

### Advanced usage

```bash
#!/usr/bin/env bash

# Example: script.sh refs/head/BRANCH 10

set -x

function join { local IFS="$1"; shift; echo "$*"; }

BRANCH_NAME=$1
CONTEXT_LINES=$2
CODE_PATH=/code

### Saving DIFF for analyzer - diff.txt

git diff -U${CONTEXT_LINES:-10} origin/master...${BRANCH_NAME} > diff.txt

### Collecting ESLINT report - eslint.xml ###

JS_IMAGE=yarn:latest
ESLINT_FILES=$(git diff --name-only origin/master...${BRANCH_NAME} | grep -E "\.(js|vue)$")

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint=${CODE_PATH}/node_modules/.bin/eslint \
    --interactive \
    ${JS_IMAGE} \
    ${ESLINT_FILES} -o eslint.xml -f checkstyle

### Collecting PHAN report - phan.xml ###

PHP_IMAGE=php7-cli:latest
PHAN_FILES=$(join , $(git diff --name-only origin/master...${BRANCH_NAME} | grep -E "\.php$"))

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint ${CODE_PATH}/vendor/bin/phan \
    --interactive \
    ${PHP_IMAGE} \
    -k ${CODE_PATH}/.phan.php -m checkstyle -o phan.xml --include-analysis-file-list ${PHAN_FILES}

### Sending results to STASH / BITBUCKET ###

docker run --rm \
    --volume $(pwd):${CODE_PATH} \
    --workdir ${CODE_PATH} \
    --entrypoint ${CODE_PATH}/vendor/bin/bitbucket-reviews \
    --interactive \
    ${PHP_IMAGE} \
    run refs/head/${BRANCH_NAME} diff.git -k .config.php -c phan.xml -c eslint.xml:/code
```
