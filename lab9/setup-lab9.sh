#!/bin/sh
# Развёртывание стенда ЛР9: «Работа с публикациями и меню в Drupal».
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8106

echo "== ЛР9: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты лабы в контейнер =="
docker compose cp scripts/. web:/opt/drupal/web/scripts/

J="docker compose exec -T web"

echo "== Установка Drupal (если ещё не установлен) =="
if $J sh -c 'test -f /var/www/html/sites/default/settings.php' 2>/dev/null; then
    echo "Drupal уже установлен — пропускаем"
else
    echo "-- drush site:install --"
    $J sh -c 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=pgsql://drupal:drupal@db/drupal --site-name="Город24" --account-name=admin --account-pass="Gorod24!Admin2026" -y' 2>&1 | tail -6
fi

echo "== Установка модулей (composer) =="
$J sh -c 'cd /opt/drupal && composer require drupal/admin_toolbar drupal/pathauto drupal/token drupal/metatag drupal/bootstrap5 drupal/colorbox drupal/taxonomy_menu --no-interaction 2>&1 | tail -3'

echo "== Включение модулей и темы =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush en admin_toolbar pathauto token metatag colorbox taxonomy_menu -y' 2>&1 | tail -6
$J sh -c 'cd /opt/drupal && (vendor/bin/drush theme:enable bootstrap5 -y 2>&1 | tail -2); vendor/bin/drush config:set system.theme default bootstrap5 -y 2>&1 | tail -2'

echo "== Рецепты типов материалов (body field) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush recipe:apply core/recipes/page_content_type -y 2>&1 | tail -2'
$J sh -c 'cd /opt/drupal && vendor/bin/drush recipe:apply core/recipes/article_content_type -y 2>&1 | tail -2'

echo "== Конфигурация ЛР8 (база: таксономия, каталог) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab8-config.php' 2>&1 | tail -8

echo "== Конфигурация ЛР9 (блоки, статьи, страницы, меню) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab9-config.php' 2>&1 | tail -30

echo "== Проверка =="
FAIL=0
check() {
    if curl -s "$2" | grep -q "$3"; then
        echo "OK    $1"
    else
        echo "FAIL  $1  $2"
        FAIL=1
    fi
}
check "сайт отвечает" "http://localhost:$PORT/" "Город24"
check "страница входа" "http://localhost:$PORT/user/login" "Log in"
check "карточка ноутбука" "http://localhost:$PORT/node/1" "Lenovo IdeaPad"
if [ "$FAIL" = "0" ]; then
    echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
    echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Adminer:    http://localhost:8107 (сервер db, пользователь drupal)"