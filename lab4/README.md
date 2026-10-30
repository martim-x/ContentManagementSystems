# ЛР4 — WordPress: перенос вёрстки «Город24»

Перенос вёрстки новостного портала «Город24» из лабораторной №1 на движок
WordPress: собственная тема, динамическое меню, динамический заголовок и
вывод содержимого из редактора консоли.

Каталог самодостаточный: compose, скрипты, тема и скриншоты. Ничего не берётся
из соседних лаб.

## Быстрый старт

```sh
cd ~/Documents/VisualStudioCodeRepos/ContentManagementSystems/lab4
sh setup-lab4.sh
```

Скрипт поднимает стенд, ставит WordPress, скачивает скелет темы с
underscores.me, заливает собственную тему, создаёт страницы, меню и
настройки. Повторный запуск ничего не ломает и не создаёт дублей.

| Что | Адрес | Доступ |
|---|---|---|
| Сайт | http://localhost:8094 | — |
| Админка | http://localhost:8094/wp-admin | `admin` / `admin123` |
| phpMyAdmin | http://localhost:8096 | `wp` / `wppass` |

Остановить стенд (данные сохраняются):

```sh
docker compose down
```

Полностью снести вместе с данными:

```sh
docker compose down -v
```

## Контейнеры

| Контейнер | Роль |
|---|---|
| `lab4-db` | MariaDB 11, база WordPress |
| `lab4-web` | WordPress + PHP, порт 8094 |
| `lab4-phpmyadmin` | веб-интерфейс к базе, порт 8096 |
| `lab4-wpcli` | WP-CLI, одноразовый, под профилем `tools` |

## Задания и где смотреть

| № | Задание по ТЗ | Статус | Где посмотреть |
|---|---|---|---|
| 1 | Создать тему (скелет _s + доработка) | сделано | Внешний вид → Темы, активна «Город24» |
| 2 | Перенести сайт из ЛР1 на WordPress | сделано | http://localhost:8094/ |
| 3 | Скачать пустую тему underscores.me, активировать | сделано | тема `_s-master` установлена в список |
| 4 | Настройки → Чтение: статическая страница | сделано | Настройки → Чтение, главная ID 4, записи ID 5 |
| 5 | Перенести папки assets, css, js в папку темы | сделано | `theme/gorod24-theme/{css,js,assets}` |
| 6 | Содержимое index.html перенести в page.php | сделано | `theme/gorod24-theme/page.php` |
| 7 | Исправить пути до картинок через get_template_directory_uri() | сделано | `theme/gorod24-theme/functions.php` |
| 8 | Прописать пути до css и js в functions.php | сделано | `theme/gorod24-theme/functions.php` |
| 9 | Футер и хедер в отдельные файлы, get_header/get_footer, wp_head/wp_footer | сделано | `header.php`, `footer.php` |
| 10 | Динамическое меню через wp_nav_menu | сделано | Внешний вид → Меню, 2 меню |
| 11 | Динамический заголовок через bloginfo | сделано | Настройки → Общие, меняется мгновенно |
| 12 | Страница через консоль, the_post и the_content | сделано | Страницы → Главная, редактор |

## Как проверить каждое задание самому

```sh
cd ~/Documents/VisualStudioCodeRepos/ContentManagementSystems/lab4
```

ЗАДАНИЕ 1, 2, 3 — темы:

```sh
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html theme list --format=table
```

Ожидаю: `gorod24-theme` со статусом `active`, рядом `_s-master` — скелет с
underscores.me из задания 3.

ЗАДАНИЕ 4 — настройки чтения:

```sh
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html eval '
echo "show_on_front: " . get_option("show_on_front") . "\n";
echo "главная: " . get_the_title(get_option("page_on_front")) . "\n";
echo "записи:  " . get_the_title(get_option("page_for_posts")) . "\n";'
```

Ожидаю: `page`, «Новости Минска — каждый день», «Новости». В консоле это
Настройки → Чтение → Статическая страница.

ЗАДАНИЕ 5 — папки в теме:

```sh
docker compose exec -T wordpress ls /var/www/html/wp-content/themes/gorod24-theme/
```

Ожидаю: `assets`, `css`, `js` плюс PHP-файлы шаблонов.

ЗАДАНИЕ 7, 8 — пути собираются функцией, а не прописаны руками:

```sh
docker compose exec -T wordpress grep -rn "get_template_directory_uri" /var/www/html/wp-content/themes/gorod24-theme/
curl -s http://localhost:8094/ | grep -o 'src="[^"]*main.js[^"]*"'
```

Ожидаю: пути вида `http://localhost:8094/wp-content/themes/gorod24-theme/js/main.js?ver=1.0.0`.

ЗАДАНИЕ 9 — вызовы функций по файлам:

```sh
grep -c "wp_head" theme/gorod24-theme/header.php
grep -c "wp_footer" theme/gorod24-theme/footer.php
grep -l "get_header" theme/gorod24-theme/*.php
```

Ожидаю: `wp_head` и `wp_footer` ровно по одному разу, `get_header` в пяти
шаблонах (page, index, single, search, 404).

ЗАДАНИЕ 10 — меню:

```sh
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html menu location list
curl -s http://localhost:8094/ | grep -c "menu-item-"
```

Ожидаю: `primary` → «Верхнее меню», `footer` → «Меню подвала», а в HTML
классы `menu-item` — доказательство, что пункты пришли из базы, а не вписаны
в header.php.

ЗАДАНИЕ 11 — динамический заголовок. Меняем название и смотрим, что оно
подхватилось:

```sh
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html option update blogname "Город24 Медиа"
curl -s http://localhost:8094/ | grep -o "Город24 Медиа" | head -2
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html option update blogname "Город24"
```

Ожидаю: после смены названия оно появляется в логотипе шапки, в заголовке
вкладки браузера и в подвале — без правки файлов темы.

ЗАДАНИЕ 12 — содержимое из редактора:

```sh
curl -s http://localhost:8094/ | grep -o "Что мы пишем"
docker compose run --rm -T -e HTTP_HOST=localhost:8094 wpcli wp --path=/var/www/html post get 4 --field=post_content
```

Ожидаю: текст «Что мы пишем» присутствует и в HTML сайта, и в записи
страницы — значит вывод идёт через `the_content()`, а не из файла.

## Структура темы

```
theme/gorod24-theme/
├── style.css          заголовок темы + @import перенесённой вёрстки
├── css/style.css      стили из вёрстки ЛР1 (558 строк)
├── js/main.js         скрипт темы
├── assets/            папка под изображения (задание 5)
├── functions.php      задания 7, 8: подключение css и js
├── header.php         задания 9, 11: шапка, wp_head(), bloginfo
├── footer.php         задание 9: подвал, wp_footer()
├── page.php           задания 6, 12: содержимое страницы, the_post/the_content
├── index.php          главный шаблон
├── single.php         шаблон записи
├── search.php         результаты поиска
└── 404.php            страница ошибки
```

Почему в корне два CSS. WordPress требует `style.css` в корне темы — из него
движок читает заголовок темы (Theme Name, Version, Author) и без него тема
просто не определяется в списке. Правила из вёрстки лежат в `css/style.css`,
а корневой файл подключает их директивой `@import`. Так выполняется и
требование WordPress, и требование задания 5 перенести папку `css` в папку
темы. Подключение стилей в `functions.php` идёт по корневому файлу, поэтому
дублей нет.

## Скриншоты

В `report/` — по одному снимку на задание, снято с работающего стенда.

## Что было исправлено при проверке

Проверка шла по отрендеренной странице, а не по выводу скрипта. Найдено и
исправлено семь дефектов:

1. **Тема не определялась.** Файл `style.css` лежал только в `css/`, а
   WordPress читает заголовок темы из корня. Без корневого файла тема не
   появлялась в списке и `wp theme activate` падал с ошибкой.
2. **Меню выпадало в столбик с точками.** В вёрстке навигация была сделана на
   `<a>`, а `wp_nav_menu()` печатает `<ul>`. Правил для списка в вёрстке не
   было — пункты шли маркерами вертикально.
3. **Пункт «Главная» выглядел пустым синим прямоугольником.** Правило
   `.main-nav a.active` из вёрстки перебивало серверную подсветку: класс
   `active` ставился вручную, а WordPress ставит `current-menu-item` на `<li>`.
4. **Дубли классов у меню.** `wp_nav_menu()` вешал класс `main-nav` и на
   `<nav>`, и на `<ul>` — селекторы срабатывали дважды.
5. **Текст главной дублировался.** Hero выводил `get_the_excerpt()`, а следом
   `the_content()` печатал тот же текст из редактора.
6. **В ленту попадала служебная запись «Hello world!»** из стандартной
   установки WordPress.
7. **Карточки получали класс `thumb-uncategorized`** — запасная рубрика «Без
   рубрики» осталась в базе, хотя записям назначались конкретные рубрики.

## Ошибки в скриптах, найденные прогоном

- `docker compose run` не понимает флаг `--profile`, хотя сервис `wpcli`
  объявлен в профиле `tools` — compose поднимает его сам.
- `docker compose cp` требует существующий контейнер, а `wpcli` одноразовый:
  копирование идёт через постоянный контейнер `wordpress`.
- Без переменной `HTTP_HOST` WP-CLI падает с `Undefined array key "HTTP_HOST"`:
  WordPress читает `$_SERVER` при формировании канонического адреса.
- WP-CLI на Mac отсутствует, синтаксис PHP проверяется в контейнере
  `php:8.3-cli`.

## Вопросы, которые задаст преподаватель

**Зачем нужен `wp_head()`, если всё подключается вручную?**
Функция выводит то, что зарегистрировано в `functions.php` через
`wp_enqueue_style()` и `wp_enqueue_script()`, плюс служебные теги WordPress.
Без неё стили из очереди не попадут в документ, и страница останется без
оформления.

**Чем отличается `bloginfo('name')` от жёстко прописанного заголовка?**
`bloginfo()` читает значение из настроек сайта. Заголовок меняется в
консоли и тут же появляется на сайте, без правки файлов темы. Жёстко
прописанный заголовок пришлось бы менять в коде в четырёх местах — в шапке,
подвале и заголовке вкладки.

**Почему `get_template_directory_uri()`, а не просто `/wp-content/themes/...`?**
Путь к теме зависит от того, где установлен WordPress. Функция возвращает
абсолютный адрес каталога темы, поэтому сайт работает и в подпапке, и на
другом домене, и в контейнере с другим портом.

**Зачем выносить шапку в `header.php`, если можно вписать в `page.php`?**
Чтобы шапка и подвал были в одном месте. Шаблонов пять — page, index, single,
search, 404. Без отдельных файлов разметка шапки повторялась бы в каждом, и
любая правка вносилась бы в пять мест сразу.

**Что делает `the_post()`?**
В цикле `while ( have_posts() )` переводит состояние на одну запись
вперёд: после вызова `the_title()`, `the_content()`, `the_permalink()`
работают именно с текущей записью из базы, а не с массивом `$post`.

**Почему `wp_nav_menu()`, а не список `<a href>` в header.php?**
Список в файле нельзя изменить из консоли — пришлось бы править код. Меню в
WordPress хранится в базе, им управляют через Внешний вид → Меню, а
`wp_nav_menu()` лишь выводит его в нужном месте шаблона. Плюс WordPress сам
помечает активный пункт классом `current-menu-item`.

**Что такое `current-menu-item`?**
Класс, который `wp_nav_menu()` ставит на `<li>` того пункта, который
соответствует открытой странице. В вёрстке этот класс ставили руками, а в
теме его назначает движок — поэтому правила CSS пришлось переписать под него.

**Чем отличается `theme_mod` от `option`?**
`option` хранит настройки всего сайта, `theme_mod` — настройки конкретной
темы. Например, акция в шапке задаётся через `get_theme_mod( 'gorod24_promo' )`,
чтобы при смене темы она не уехала вместе с содержимым сайта.

**Зачем `assets/`, если в вёрстке картинок нет?**
Папка создана по заданию 5 — в неё переносят изображения, шрифты и иконки.
В вёрстке ЛР1 вместо картинок CSS-градиенты (классы `.thumb`, `.avatar`),
поэтому файлов там пока нет. Проверка наличия папки нужна преподавателю, а
пустая папка в git не хранится — рядом лежит `README.txt` с пояснением.

## Ссылки

- [Скелет темы _s с underscores.me](https://underscores.me/)
- [Кодекс WordPress — функции тем](https://developer.wordpress.org/themes/)
- [Кодекс WordPress — WP_Query](https://developer.wordpress.org/reference/classes/wp_query/)