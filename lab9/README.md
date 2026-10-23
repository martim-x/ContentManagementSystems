# ЛР9 — Drupal: работа с публикациями и меню

Дисциплина «Системы управления контентом». Задание — `labs/Drupal/Drupal_2.pdf`
(внутренняя нумерация документа «ЛР №9»). Сайт основан на ЛР8 (Drupal 11,
«Город24»).

## Что сделано

- Стенд `lab9/`: собственный `Dockerfile`, `docker-compose.yml` (проект
  `cms-drupal-lab9`, контейнеры `db9`/`web9`/`adminer9`, порты 8106 сайт,
  8107 Adminer).
- Применены рецепты `page_content_type` и `article_content_type` (поле body).
- Модули: Admin Toolbar, Pathauto, Token, Metatag, Colorbox, Taxonomy Menu.
  Тема Bootstrap 5.
- База ЛР8: таксономия «Lenovo», тип «Ноутбук», каталог из 10 ноутбуков.
- Регион `sidebar_first` с двумя блоками.
- Статья «Пример статьи ЛР9» + пункт меню.
- Страницы: «История фирмы», «Галерея» (colorbox), «Прайс-лист» (таблица).
- Меню: пункты для страниц, «Каталог» с пустой ссылкой и подпунктами-терминами,
  дополнительное меню в футере.

## Адреса

| Что | Адрес | Логин |
|---|---|---|
| Сайт | http://localhost:8106 | — |
| Админка | http://localhost:8106/user/login | `admin` / `Gorod24!Admin2026` |
| Adminer | http://localhost:8107 | сервер `db`, пользователь `drupal` |

## Запуск

```sh
cd lab9
docker compose up -d --wait
sh setup-lab9.sh
```