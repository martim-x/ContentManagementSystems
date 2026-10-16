# ЛР7 — Joomla: управление пользователями и уровни доступа

Дисциплина «Системы управления контентом». Задание — `labs/Joomla/Joomla-3.pdf`
(внутренняя нумерация документа «ЛР №6»). Сайт «Город24» перенесён из ЛР6.

## Выполнено

- Стенд `lab7/`: `Dockerfile` (ини-лимиты PHP), `docker-compose.yml`
  (проект `cms-joomla-lab7`, контейнеры `db7`/`web7`/`phpmyadmin7`,
  порты 8102 сайт, 8103 phpMyAdmin), `.dockerignore`.
- Перенос сайта из ЛР6: дамп `/tmp/lab7-from-lab6.sql` (83 таблицы)
  импортирован в `db7`; файлы `cms-joomla-lab6_joomla_data` скопированы в
  `cms-joomla-lab7_joomla_data`; `chown -R www-data:www-data` выполнен.
- `scripts/lab7-prepare.php`: замена порта 8099 → 8102 в текстовых полях,
  отключение MFA, пароли стенда, самостоятельная регистрация с активацией
  администратором.
- `lab7-users.php`: группы `Деканат`, `Старосты`, `Студенты`;
  пользователи `dekan`, `starosta1..3`, `student1..3`.
- `lab7-levels.php`: 5 уровней доступа (`Для студентов`, `Для старост`,
  `Для старосты группы №1`, `Для старост групп №2 и №3`,
  `Для студентов группы №3`).
- `lab7-categories.php`: 3 категории (`dekanat-gruppa1`,
  `dekanat-gruppy23`, `dekanat-starosta3-stud3`) с правилами создания
  `core.create` в `jos_assets`; 4 статьи.
- `lab7-permissions.php`: права групп в «Общих настройках» (декан:
  `com_content` create/edit/edit.state, `com_menus` create/edit,
  `com_users`/`com_config` для назначения доступа);
  староста: `com_content` create/edit/edit.own/edit.state;
  студент: `create/edit` = 0.
- `lab7-menu.php`: пункты меню `registraciya`, `vhod`,
  `sozdat-material-dekan`, `sozdat-material-starosta`.
- Скрины в `lab7/report/`: `task1_homepage_site.png`,
  `task2_admin_login.png`, `task3_registraciya_menu.png`,
  `task4_category_novosti.png`.
- Проверка фактически: `setup-lab7.sh` содержит `lab7-verify.php`
  (`curl` на главную/админку); сайт отвечает `200`.

## Что не сделано / долг

- План действия записан в `lab7/PLAN.md` (продолжение, если вернусь):
  полный набор скринов по каждому пункту ТЗ, финальный отчёт в виде
  `README.md`, проверка видимости материалов разными пользователями
  через браузер, и пуш в репозиторий дисциплины.
- `lab7/README.md` — только краткая сводка (этот файл); полный отчёт
  по правилам курса не собран (документ не требуется для ЛР5/ЛР6,
  поэтому не создавался по умолчанию).

## Адреса стенда ЛР7

|| Адрес |
|---|---|
| Сайт | http://localhost:8102 |
| Админка | http://localhost:8102/administrator |
| phpMyAdmin | http://localhost:8103 |

Контейнеры (`container_name` без префиксов и без `-1`): `db7`, `web7`, `phpmyadmin7`.

## Как запускать

```sh
cd lab7
docker compose up -d --wait
sh setup-lab7.sh        # пока отсутствует полный пайплайн
```

## Замечания к защите

- Сайт перенесён из ЛР6 с сохранением контента (`Город24`).
- ACL настроен через `jos_usergroups`, `jos_viewlevels`, `jos_assets`
  (`core.create`) и `jos_menu` (доступ по уровню доступа); демонстрируется
  через фронтенд-формы публикации (`Создать материал`).
- Параметр `access` категорий ограничивает видимость; `core.create` в
  `jos_assets` ограничивает право публиковать.
- Регистрация новых пользователей разрешена (`com_users` → `Registration`),
  но учётные записи активируются администратором (`useractivation = admin`).
