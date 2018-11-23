## Интеграция checkstyle-format в stash/bitbucket

Читает формат checkstyle и пишет комментарии к pull-request

В папке rest примеры запросов в stash

### Как работает

Данные которые требуются: проект, репозиторий, ветка.
Комментарии будут публиковаться в пулл-реквест со статусом OPEN, если такой имеется.

1. Передается результат git dff origin/master <BRANCH> или разница ревизий
2. Если работа уже была, то передается прошлый результат работы в формате checkstyle
3. Последовательно передаются результат работы статических анализаторов
4. Производится анализ и рассылка комментариев
5. Результат работы сохраняется для следующего запуска

### Настройка

1. Количество линий кода рядом с изменениями которые будут анализироваться
2. Лимит комментариев на один раз
3. Минимальное количество строчек редактированное в файле чтобы указывать на ошибки

### Получение изменений

Для бамбу удобно получать разницу между ревизиями при помощи git

##### Список файлов

git diff --name-only ${bamboo_planRepository_revision}...${bamboo_planRepository_previousRevision}

##### Дифф

git diff --unified=<context> ${bamboo_planRepository_revision}...${bamboo_planRepository_previousRevision}

context - предпочтительнее передавать 10, т.к больше стеш не дает показать

### Информация по API Stash

https://docs.atlassian.com/bitbucket-server/rest/5.15.0/bitbucket-rest.html?utm_source=%2Fstatic%2Frest%2Fbitbucket-server%2Flatest%2Fbitbucket-rest.html&utm_medium=301#idm45622371276656

https://developer.atlassian.com/server/bitbucket/reference/rest-api/
