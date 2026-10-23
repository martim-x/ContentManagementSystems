# ЛР8 — Drupal: знакомство с Drupal

Дисциплина «Системы управления контентом». Задание — `labs/Drupal/Drupal_1.pdf`
(внутренняя нумерация документа «ЛР №8»).

## Что сделано

- Стенд `lab8/`: собственный `Dockerfile` (образ `drupal:11` + drush + лимиты PHP),
  `docker-compose.yml` (проект `cms-drupal-lab8`, контейнеры `db8`/`web8`/`adminer8`,
  порты 8104 сайт, 8105 Adminer).
- Установка Drupal 11 через drush (`site:install standard`).
- Модули: Admin Toolbar, Pathauto, Token, Metatag. Тема Bootstrap 5 (по умолчанию).
- Таксономия «Lenovo» с терминами «серия G/N/V».
- Тип материала «Ноутбук» с полями «Серия» и «Фотография».
- Каталог из 10 ноутбуков (Lenovo IdeaPad).

## Адреса

| Что | Адрес | Логин |
|---|---|---|
| Сайт | http://localhost:8104 | — |
| Админка | http://localhost:8104/user/login | `admin` / `Gorod24!Admin2026` |
| Adminer | http://localhost:8105 | сервер `db`, пользователь `drupal` |

## Запуск

```sh
cd lab8
docker compose up -d --wait
sh setup-lab8.sh
```

## Проверка

Скрипт `setup-lab8.sh` прогоняет curl-проверки главной страницы, страницы входа и карточки ноутбука.