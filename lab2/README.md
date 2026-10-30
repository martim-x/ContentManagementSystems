# ЛР2 — WordPress: знакомство с CMS, сайт «Город24»

> Стек Docker живёт в этой папке (`lab2/`): `docker-compose.yml`, `setup-gorod24.sh`, тема `theme/gorod24`, автонаполнение `scripts/setup-content.php`, отчёт в `report/`.

Новостной портал Минска на базе вёрстки из ЛР1 (вёрстка ЛР1 (`../lab1/site`)).
Тема сайта: `theme/gorod24`, автонаполнение: `scripts/setup-content.php`.

## Как запустить
```bash
docker compose up -d                 # поднять стек
sh setup-gorod24.sh                  # (первый раз) WP + плагины + тема gorod24 + контент
```

## Доступ
- Сайт: http://localhost:8080
- Админка: http://localhost:8080/wp-admin — `admin` / `admin123`
- phpMyAdmin (база): http://localhost:8082 — `wp` / `wppass`

## Что внутри
| Контейнер | Роль | Аналог в OpenServer |
|---|---|---|
| `db` (MariaDB 11) | база данных WordPress | MySQL из OpenServer |
| `web` | веб-сервер + PHP + движок WP | Apache + PHP из OpenServer |
| `wpcli` | WP-CLI — командная строка WP (автоматизация) | — |
| `phpmyadmin` | веб-интерфейс к базе | phpMyAdmin из OpenServer |

Проект compose называется `cms-wordpress-group` (`name:` в `docker-compose.yml`), но имена
контейнеров короткие — у каждого сервиса свой `container_name`, поэтому в `docker ps` видно
`db`, `web`, `wpcli`, `phpmyadmin` без префикса и суффикса `-1`. Тома: `cms-wordpress-db-data`,
`cms-wordpress-wp-data`.

**Почему MariaDB:** MySQL-совместимая БД без лицензионных ограничений Oracle; WordPress работает с ней без изменений. **Зачем OpenServer в ТЗ:** методичка под Windows — OpenServer это «Apache+MySQL+PHP в одном установщике»; Docker заменяет его тем же набором контейнеров.

## Соответствие заданиям
1. Локальный сервер + WordPress + БД — `docker-compose.yml` (WordPress + MariaDB 11 + phpMyAdmin).
2. Изменение темы — тема **gorod24**: шапка с логотипом и меню, фон страницы, палитра из ЛР1 (+ custom CSS).
3. Каталог тем — установлены Twenty Twenty-One/Four/Five + активна gorod24.
4. Виджеты (сайдбар): Поиск, Свежие записи, Рубрики со счётчиком, Облако меток.
5. Плагины (активны): UpdraftPlus (бэкап), Contact Form 7 (форма на /kontakty), Really Simple CAPTCHA (капча), Smart Slider 3 (слайдер), Elementor.
   Резервная копия: дамп БД `report/gorod24-2026-09-25.sql` + UpdraftPlus.
6. Часовой пояс Europe/Minsk, формат «11 September 2026, 14:30»; комментирование: только зарегистрированные, премодерация, ветки, авто-закрытие через 30 дней.
7. Рубрика «Новости Минска» → подрубрики Политика / Экономика / Спорт / Культура / Город.
8. 10 записей по подрубрикам с метками и миниатюрами (GD-заглушки).
9. Запись «Тестируем HTML-редактор» — ручные стили (выравнивание, фон, цвет, отступы, цитата). Приватность: «Личное» (только админ) + запись по паролю `lab2026`.
10. Меню из ЛР1: Главная / Новости / О нас / Город / Контакты (+ подвал). Front-page = лендинг с hero-новостью (закреплённая «Немига-2»).

## Что фотографировать для отчёта
1. `docker compose ps` — сервер запущен (з.1)
2. Главная http://localhost:8080 — лендинг: логотип, меню, hero, карточки, футер
3. Страницы /o-nas, /gorod-stranica, /kontakty (форма CF7 + капча)
4. Запись «Тестируем HTML-редактор» (з.9)
5. Тема gorod24 в админке + файлы темы (з.2–3)
6. Виджеты (з.4); Плагины + вкладки UpdraftPlus / Smart Slider 3 / Elementor (з.5)
7. Настройки → Общие и → Обсуждение (з.6)
8. Рубрики-иерархия, записи с метками (з.7–8)
9. Страница записи по паролю — форма ввода пароля (з.9)
10. Внешний вид → Меню (з.10)
11. phpMyAdmin http://localhost:8082 — таблицы WP (wp_posts, wp_terms и т.д.)
12. Дамп `report/gorod24-2026-09-25.sql` / UpdraftPlus — резервная копия (з.5)

## Персистентность данных
Данные WordPress (файлы) и БД лежат в томах `cms-wordpress-wp-data` и `cms-wordpress-db-data`:
- `docker compose restart` / `down` — **всё сохраняется**;
- только `docker compose down -v` удаляет тома (полный сброс).

## Полезные команды
```bash
docker compose up -d --wait                          # запустить
docker compose down                                  # остановить (данные остаются)
docker compose run --rm wpcli wp <команда>           # WP-CLI
docker compose cp <файл> wordpress:/var/www/html/…   # положить файл в WP
```

## ЛР3 — отдельная работа

Лендинг Elementor, шаблон и демонстрация блоков вынесены в каталог [`../lab3`](../lab3):
там свой стенд (порты 8090/8092), скрипты и скриншоты.
