# ЛР6 — Joomla: расширения

Дисциплина «Системы управления контентом». Задание — `labs/Joomla/Joomla_2.pdf`
(в нумерации практикума Joomla это ЛР2 «Расширения для Joomla»): на сайте из
прошлой лабораторной работы настроить резервное копирование, защиту от
несанкционированного доступа и спама, карту сайта, управление мета-данными,
кнопки шеринга социальных сетей, форму обратной связи, модуль поиска,
уведомление поисковых систем и код аналитики.

Стенд и сайт — те же, что в ЛР5 (Joomla 5.4.9, сайт «Город24», своя сборка
через Dockerfile и docker-compose), отдельный проект compose
`cms-joomla-lab6`, поэтому ЛР5 не затрагивается.

## Запуск

```sh
cd lab6
docker compose up -d --wait   # сборка своего Dockerfile + подъем стенда
sh setup-lab6.sh              # установка, контент, расширения, проверка
```

Контейнеры называются `db6`, `web6`, `phpmyadmin6` — благодаря постфиксам
лабу 6 можно держать поднятой одновременно с лабой 5 (`db5`/`web5`/
`phpmyadmin5`): порты и имена не пересекаются.
Данные живут в named volumes `cms-joomla-lab6_joomla_data` и `_db_data`,
переживают `down`/restart; полный сброс — `docker compose down -v`.

## Адреса

| Что | Адрес | Логин |
|---|---|---|
| Сайт | http://localhost:8099 | — |
| Админка | http://localhost:8099/administrator | `admin` / `Gorod24!Admin2026`, второй фактор (MFA): `246810` |
| phpMyAdmin | http://localhost:8101 | `joomla` / `joomla` |

## Что сделано по заданиям ( Joomla_2.pdf )

| Задание | Реализация | Как проверить |
|---|---|---|
| 1. Резервное копирование | Расширение **Akeeba Backup 9.8.4 Core** (zip в `packages/`), бэкап сайта | Компонент «Akeeba Backup» в админке → Manage Backups; архив `.jpa` (~40 МБ) в `administrator/components/com_akeebabackup/backup/` |
| 2. Защита от несанкционированного доступа | Сильный пароль админа, политика паролей (12+ символов, цифры, спецсимволы, заглавные), отключена саморегистрация, **многофакторная аутентификация** (метод «фиксированный код», код `246810`) | Попытка входа в админку: после пароля Joomla требует код MFA |
| 3. Карта сайта | XML-карта `/sitemap.xml` (генерируется `scripts/lab6-sitemap.php`: главная, разделы, все статьи с lastmod) + HTML-карта — статья «Карта сайта» и пункт меню | http://localhost:8099/sitemap.xml и http://localhost:8099/karta-sajta |
| 4. Защита от спама | Свой плагин капчи **`plg_captcha_g24math`** «сложение двух цифр» (`plugin/captcha/g24math/`): провайдер `CaptchaProviderInterface`, регистрация через событие `onCaptchaSetup` (в Joomla 5 ядро капч вынесено из поставки), глобальная капча в configuration.php | Форма на `/kontakty`: поле «Решите пример: a + b = ?»; при вводе неверного ответа отправка отклоняется |
| 5. Управление мета-данными | Глобальные description/robots в configuration.php, description и keywords у всех статей (заполняются скриптом, если пусто) | Исходник страницы: `<meta name="description">`; админка → Материалы: метаданные в статьях |
| 6. Кнопки шеринга | Свой модуль **`mod_gorod24share`** (`module/mod_gorod24share/`): Telegram, ВКонтакте, X (Twitter), WhatsApp — только штатные share-адреса, без внешних скриптов соцсетей; CSS в шаблоне | Блок «Поделиться новостью» в сайдбаре всех страниц |
| 7. Форма обратной связи | Штатный **com_contact**: контакт «Редакция Город24» (адрес, телефон, e-mail), форма с капчей g24math, пункт меню «Контакты» | http://localhost:8099/kontakty |
| 8. Модуль поиска | Штатный **Smart Search** (com_finder + mod_finder), индексация через CLI `php cli/joomla.php finder:index` | Поле «Поиск по сайту» в сайдбаре; поиск находит статьи |
| 9. Уведомление поисковых систем | `ping-search-engines.sh`: ping Bing; строка `Sitemap:` в robots.txt. Ping Google закрыт самой Google с 2023 (возвращает 404/410) — штатно уведомление делается через Search Console и robots.txt | `sh ping-search-engines.sh` |
| 10. Код аналитики | Google Analytics через вставку в `<head>` шаблона cassiopeia (маркер `g24-analytics`), тестовый идентификатор `G-GOROD24LAB` | Исходник любой страницы: `googletagmanager.com/gtag/js?id=G-GOROD24LAB` |
| 11. Nicepage | Автоматизация невозможна: у Nicepage нет публичного zip для Joomla — пакет ставится через их десктоп-приложение и аккаунт nicepage.com | Ставится вручную: nicepage.com/joomla → установить zip через админку («Система → Установить расширения») → создать страницу, изменить меню |

## Структура

```
lab6/
├── Dockerfile                 # свой образ: joomla:5 + лимиты PHP + скрипты
├── docker-compose.yml         # web:8099, db (mariadb), phpmyadmin:8101, named volumes
├── .dockerignore
├── setup-lab6.sh              # полный идемпотентный пайплайн развертывания
├── make-ru-language.sh        # установка русского языка
├── ping-search-engines.sh     # ping поисковых систем о sitemap
├── packages/                  # дистрибутивы расширений
│   ├── akeeba-backup-9.8.4-core.zip
│   ├── plg_g24math.zip        # капча (собирается из plugin/)
│   └── mod_gorod24share.zip   # шеринг (собирается из module/)
├── plugin/captcha/g24math/    # исходники капчи
├── module/mod_gorod24share/   # исходники модуля шеринга
├── scripts/
│   ├── lab6-install.php       # установка Joomla без браузера
│   ├── lab6-content.php       # контент сайта «Город24» (перенос из ЛР5)
│   ├── lab6-menu-tree.php     # пересборка Nested-деревьев меню и категорий
│   ├── lab6-extensions.php    # включение плагина, публикация модулей, CSS
│   ├── lab6-security.php      # пароль, политика, MFA, глобальная капча
│   ├── lab6-contact.php       # com_contact + пункт меню
│   ├── lab6-search.php        # mod_finder
│   ├── lab6-meta.php          # метаданные
│   ├── lab6-sitemap.php       # sitemap.xml + robots.txt + карта сайта
│   └── lab6-analytics.php     # GA в шаблон
└── report/                    # скрины контрольных страниц
```

Установка расширений выполняется штатным CLI Joomla без браузера:
`php cli/joomla.php extension:install --path=/tmp/<пакет>.zip`.

## Заметки к защите

- В Joomla 5.4 капча-плагины вынесены из ядра: провайдер добавляется плагином,
  реализующим `CaptchaProviderInterface`, и регистрируется в реестре капч
  событием `onCaptchaSetup` (см. `CaptchaRegistry::initRegistry()`).
- Поле капчи в формах берёт провайдера из глобальной настройки
  (`configuration.php`, `public $captcha`), а параметр контакта включает
  отображение формы — поэтому `g24math` проставлен и глобально, и в контакте.
- Письма формы обратной связи не отправляются: SMTP на стенде не настроен —
  форма проходит валидацию и капчу, для демонстрации анти-спама этого
  достаточно.
- Бэкап Akeeba делается через админку (JS-движок «Backup Now»); предупреждение
  «Default output directory in use» некритично — это рекомендация вынести
  каталог архива за пределы web-корня.
