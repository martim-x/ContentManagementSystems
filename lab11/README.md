# ЛР11 — Drupal: расширение функциональности, модули и Views

Дисциплина «Системы управления контентом». Задание — `labs/Drupal/Drupal_4.pdf`
(в документе «Лабораторная работа № 11»). Сайт — городской новостной портал
«Город24», продолжение ЛР8; каталог ноутбуков Lenovo — раздел «Техника».

## Стенд

- Собственный `Dockerfile` (образ `drupal:11` + drush), `docker-compose.yml`:
  проект `cms-drupal-lab11`, контейнеры `db11` / `web11` / `adminer11`.
- Drupal 11.4, тема **Bootstrap 5**. Порты: сайт **8110**, Adminer **8111**.
- PostgreSQL 16 (как в ЛР8/ЛР9).

| Что | Адрес | Логин |
|---|---|---|
| Сайт | http://localhost:8110 | — |
| Админка | http://localhost:8110/user/login | `admin` / `Gorod24!Admin2026` |
| Adminer | http://localhost:8111 | PostgreSQL, сервер `db`, `drupal` / `drupal` |

## Запуск

```sh
cd lab11
docker compose up -d --wait
sh setup-lab11.sh      # идемпотентен: база ЛР8, затем конфиг ЛР11
```

Модули: Admin Toolbar, Pathauto, Token, Metatag, Webform, Chaos tool suite,
**Computed Field**, а также собственные `gorod24_registry`, `gorod24_slogan`,
`gorod24_price`, `news`.

## Что сделано по заданиям

### Задание 1 — модуль, создающий таблицу в БД

Модуль `scripts/modules/gorod24_registry`:

- `hook_schema()` объявляет таблицу `gorod24_registry` (`id` serial, `model`,
  `series`, `created`), ядро создаёт её при установке модуля;
- `hook_install()` заполняет таблицу пятью моделями;
- страница `/admin/reports/gorod24-registry` (контроллер `RegistryController`)
  выводит содержимое таблицы и доступна только роли с правом
  `access administration pages`.

Скрины: `report/task1a_реестр_моделей_страница.png` (страница отчёта),
`report/task1b_реестр_таблица_в_adminer.png` (та же таблица в Adminer: структура
и 5 строк).

### Задание 2 — программный вывод слогана и смена конфигурации

Модуль `scripts/modules/gorod24_slogan`:

- `hook_install()` меняет конфигурацию `system.site` — устанавливает слоган
  «Город24: новости Минска каждый день»;
- блок `gorod24_slogan_block` читает слоган из конфигурации и выводит его
  программно (не через поле темы);
- форма `/admin/config/system/slogan` позволяет изменить слоган и сразу увидеть
  результат в блоке.

Скрины: `report/task2a_слоган_блок_на_сайте.png`,
`report/task2b_слоган_форма_изменения.png`.

### Задание 3 — модуль news

Модуль `scripts/modules/news`:

- пункт «News» с описанием в блоке **Web services** на `/admin/config`
  (`news.links.menu.yml`, родитель `system.admin_config_services`);
- маршрут `/admin/config/services/news` → `NewsController::page()` — пустая
  страница с заголовком **News**;
- блок `news_block` выводит строку «This is a news block».

Блок размещён в регионе **Main content** и через UI ограничен: страницы —
`<front>`, роли — `authenticated`. Настройки видны на скринах:
`report/task3a_ссылка_news_в_admin_config.png`, `report/task3b_страница_news.png`,
`report/task3c_блок_this_is_a_news_block_на_главной.png`,
`report/task3d_видимость_блока_страницы_front.png`,
`report/task3e_видимость_блока_роль_authenticated.png`.

### Задание 4 — вычисляемое поле

- В тип материала **«Ноутбук»** добавлено поле `field_price` (decimal) и цены
  для всех 10 карточек.
- Модуль `scripts/modules/gorod24_price` объявляет computed-field плагин
  `gorod24_price_discount` (`field_type: decimal`), который прикрепляет к
  `node/noutbuk` поле `field_price_discount` со значением **90 % от цены**.
- Поле выведено в отображениях Default и Teaser: цена и цена со скидкой.

Скрины: `report/task4a_поле_цены_в_типе_ноутбук.png`,
`report/task4b_вычисляемое_поле_в_отображении.png`,
`report/task4c_карточка_ноутбука_с_вычисленной_ценой.png` (54 990 → 49 491).

### Задание 5 — View статей таблицей с фильтром по дате

Представление `articles_table` (страница `/articles`):

- таблица с колонками **Заголовок**, **Автор**, **Дата публикации**;
- фильтры: опубликовано, тип «Статья», дата публикации **≥ −30 дней**;
- сортировка по дате публикации по убыванию.

Созданы три статьи (автор — `admin`); статья «Архив: старый Минск в фотографиях 1970-х»
старше 30 дней и в таблицу не попадает — фильтр проверен на реальных данных.

Скрины: `report/task5a_view_таблица_настройки.png`,
`report/task5b_view_фильтр_по_дате.png`,
`report/task5c_страница_статей_таблица.png`.

## Конфигурация кодом

`scripts/lab11-config.php` (идемпотентен) — слоган и блоки, тип «Статья» с
отображениями, поле цены и цены 10 ноутбуков, вычисляемое поле в отображениях,
три статьи с датами публикации, View `articles_table`.

## Проверка

`setup-lab11.sh` в конце выполняет curl-проверки: главная, слоган из модуля,
каталог, `/articles`, цена в карточке ноутбука.

## Скриншоты

`report/` — 15 кадров 2880×2200 (headless Chrome, deviceScaleFactor 2).
Сценарий — `tools/screenshots/shots_lab11.py`; Adminer-кадр снимается после
входа в БД (PostgreSQL, `drupal`/`drupal`).

## Известные ограничения

- Computed Field 4 создаёт поля только через плагины: произвольный PHP-код в UI
  больше не поддерживается, поэтому вычисление оформлено плагином модуля
  `gorod24_price`. UI-субмодуль `computed_field_ui` не устанавливался — поле
  объявлено плагином автоматически.
- Вкладка «Computed fields» в списке полей типа «Ноутбук» появится только с
  `computed_field_ui`; в текущем стенде поле видно в Manage display и на карточке.
