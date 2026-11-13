# ЛР10 — Drupal: права пользователей, карта сайта, безопасность и эффективность

Дисциплина «Системы управления контентом». Задание — `labs/Drupal/Drupal_3.pdf`
(в документе «Лабораторная работа № 10»). Сайт — городской новостной портал
«Город24», продолжение ЛР8; каталог ноутбуков Lenovo — раздел портала «Техника».

## Стенд

- Собственный `Dockerfile` (образ `drupal:11` + drush + `mysqli`), `docker-compose.yml`:
  проект `cms-drupal-lab10`, контейнеры `db10` / `web10` / `adminer10` / `mailpit10` / `cron10`.
- Drupal 11.4, тема **Bootstrap 5**. Порты: сайт **8108**, Adminer **8109**,
  Mailpit UI **8026** (SMTP `mailpit:1025`, с хоста `localhost:1026`).
- БД — **MariaDB 11**, а не PostgreSQL: Backup and Migrate 5.1 делает дамп базы
  собственным драйвером на `mysqli` и поддерживает только MySQL-совместимые СУБД.
  Этот же драйвер требует расширение `mysqli`, которого нет в образе `drupal:11` —
  оно ставится в `Dockerfile`.
- `cron10` каждые 5 минут выполняет `drush cron`: без него расписание
  Backup and Migrate «daily» не запускается само.

| Что | Адрес | Логин |
|---|---|---|
| Сайт | http://localhost:8108 | — |
| Админка | http://localhost:8108/user/login | `admin` / `Gorod24!Admin2026` |
| Редактор | http://localhost:8108/user/login | `redaktor` / `Redaktor!2026` |
| Adminer | http://localhost:8109 | сервер `db`, пользователь `drupal` |
| Mailpit | http://localhost:8026 | без авторизации, только локально |

## Запуск

```sh
cd lab10
docker compose up -d --wait
sh setup-lab10.sh      # идемпотентен: база ЛР8, затем конфиг ЛР10
```

Модули: Admin Toolbar, Pathauto, Token, Metatag, Webform, Chaos tool suite,
Simple XML Sitemap, Honeypot, CAPTCHA, Backup and Migrate, Statistics, Contact,
Schema.org Metatag (`schema_article`, `schema_organization`, `schema_web_site`).

## Что сделано по заданиям

### Задание 1 — пользователь и роль

- Роль **«Редактор»** (`redaktor`): просмотр материалов и очереди содержимого,
  создание/редактирование/удаление **своих** новостей, доступ к toolbar и
  `/admin/content`, формат `basic_html`. Прав на конфигурацию сайта и `/admin/people`
  роль не получает.
- Пользователь **`redaktor`** с этой ролью.
- Скрины: `report/task1a_пользователи_список_редактор.png`,
  `report/task1b_роль_редактор_права.png`.

### Задание 2 — контактная форма администрации

- Форма ядра Contact **«Обратная связь»** (`/contact`), получатель
  `redakciya@gorod24.local`, пункт главного меню «Обратная связь».
- Доступ к форме выдан ролям anonymous и authenticated — иначе гость получает
  отказ, и форма не видна.
- Отправка идёт через **SMTP в Mailpit** (`mailpit:1025`), а не через отсутствующий
  в образе `sendmail`: письмо реально появляется в Mailpit, отправитель —
  `redakciya@gorod24.local`. Проверка: `http://localhost:8026`.
- Скрины: `report/task2a_контактная_форма_на_сайте.png`,
  `report/task2b_контактная_форма_настройки.png`,
  `report/task2c_письмо_в_mailpit.png`.

### Задание 3 — карта сайта, микроразметка, защита от спама

| Что | Реализация | Скрин |
|---|---|---|
| Карта сайта | `simple_sitemap`: включены материалы (`news`, `noutbuk`), словари (`rubriki`, `lenovo`) и пункты меню; `/sitemap.xml` содержит 34 URL | `report/task3a_карта_сайта_xml.png`, `report/task3b_карта_сайта_настройки.png` |
| Микроразметка | `schema_metatag`: JSON-LD `Article` (автор — объект `Person`, издатель — `Organization`) и `Organization` для сайта | `report/task3c_микроразметка_jsonld.png` |
| Антиспам | Honeypot на всех формах (скрытое поле `url` + метка времени) и математическая CAPTCHA на `/contact` | `report/task3d_антиспам_captcha_на_форме.png`, `report/task3e_антиспам_captcha_настройки.png` |

Проверка разметки — `curl` страницы новости: в `<head>` приходит
`<script type="application/ld+json">` с `@type: Article` (заголовок, описание,
даты, автор, издатель, URL) и `@type: Organization`.

### Задание 4 — резервное копирование и мониторинг посещаемости

- **Backup and Migrate**: расписание `daily_schedule` включено (раз в сутки,
  хранить 5 копий), источник `default_db`, приёмник `private_files`
  (`private://backup_migrate/`). Ручная копия создаётся кнопкой «Backup now»;
  список копий — `report/task4b_резервная_копия_создана.png`.
  Скрины настроек и расписания: `report/task4a_резервное_копирование_настройки.png`
  (форма расписания с источником/приёмником/keep),
  `report/task4c_расписание_копирования.png`.
- **Cron**: контейнер `cron10` каждые 5 минут выполняет `drush cron`, поэтому
  расписание `daily_schedule` действительно запускается, а не ждёт ручного
  `drush cron`.
- **Мониторинг посещаемости**: модуль Statistics вынесен из ядра Drupal 11 в
  contrib (`drupal/statistics` 1.0.1) и не содержит отчётов ядра. Счётчик
  просмотров включён, а страница отчёта собрана модулем `gorod24_analytics`:
  `/admin/reports/poseshchaemost` — таблица «материал → всего просмотров →
  последний просмотр» по таблице `node_counter`.
  Скрин: `report/task4d_статистика_посещаемости.png`.

### Задание 5 — кэширование

- Страничный кэш для анонимных: `cache.page.max_age = 3600`.
- Агрегация CSS и JS включена (`css.preprocess`, `js.preprocess`).
- Проверка: повторный запрос главной от анонимного пользователя отдаёт
  `X-Drupal-Cache: HIT` и `Cache-Control: max-age=3600, public`.
- Блок веб-формы «Прислать новость» из ЛР8 отключён: форма с CSRF-токеном
  делает весь ответ `UNCACHEABLE (response policy)`, и страничный кэш не работает.
- Скрин: `report/task5a_кэширование_настройки.png`.

## Конфигурация кодом

`scripts/lab10-config.php` (идемпотентен) — роль и пользователь, контактная форма,
SMTP-настройки Mailpit, права anonymous/authenticated, настройки simple_sitemap
и Schema.org, Honeypot и CAPTCHA, расписание Backup and Migrate, статистика, кэш,
отключение блока веб-формы.

`scripts/modules/gorod24_analytics/` — модуль отчёта посещаемости: маршрут, меню
в разделе Reports и контроллер `VisitReportController`, который читает
`node_counter` и выводит таблицу.

## Проверка

`setup-lab10.sh` в конце выполняет curl-проверки: главная, лента новостей,
контактная форма, `/sitemap.xml`, JSON-LD, каталог `/noutbuki`.
Дополнительно: `docker compose exec -T web vendor/bin/drush scr /opt/drupal/web/scripts/audit-lab10.php`
печатает фактическое состояние — роли, формы, настройки, расписание, статистику,
метатеги.

## Скриншоты

`report/` — 15 кадров (headless Chrome, deviceScaleFactor 2): 14 по заданиям
и `task2c_письмо_в_mailpit.png` — доказательство доставки письма.
Сценарий — `tools/screenshots/shots_lab10.py`; он же создаёт резервную копию
через штатную форму и записывает реальные просмотры через endpoint Statistics.

## Известные ограничения

- Ручное резервное копирование выполняется только из UI: drush-команд у
  Backup and Migrate 5.1 нет.
- Дамп БД привязан к MySQL-совместимой СУБД (см. стенд).
- Почта проверяется локально в Mailpit; наружу письма не отправляются.
- Отчётов `/admin/reports/pages|visitors|hits` в contrib-модуле нет, поэтому
  страница посещаемости реализована отдельным модулем.
