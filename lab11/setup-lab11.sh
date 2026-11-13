#!/bin/sh
# Развёртывание стенда ЛР11: собственные модули и Views
# (labs/Drupal/Drupal_4.pdf). Скрипт идемпотентен.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8110
J="docker compose exec -T web"

# Готовность сайта: после обновления contrib-модулей PHP-FPM держит старый
# скомпилированный контейнер, из-за чего страницы отдают 500.
wait_ready() {
    i=0
    while [ "$i" -lt 60 ]; do
        if [ "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/")" = "200" ]; then
            return 0
        fi
        i=$((i + 1))
        sleep 2
    done
    echo "сайт не поднялся за 120 с"
    return 1
}

echo "== ЛР11: поднимаем стенд =="
docker compose up -d --build --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты, ассеты и модули в контейнер =="
docker compose cp scripts/. web:/opt/drupal/web/scripts/
$J sh -c 'mkdir -p /opt/drupal/web/modules/custom'
docker compose cp scripts/modules/. web:/opt/drupal/web/modules/custom/

echo "== Установка Drupal (если ещё не установлен) =="
if $J sh -c 'test -f /var/www/html/sites/default/settings.php' 2>/dev/null; then
    echo "Drupal уже установлен — пропускаем"
else
    echo "-- drush site:install --"
    $J sh -c 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=pgsql://drupal:drupal@db/drupal --site-name="Город24" --account-name=admin --account-pass="Gorod24!Admin2026" -y' 2>&1 | tail -8
fi

echo "== Установка модулей (composer) =="
$J sh -c 'cd /opt/drupal && composer require drupal/admin_toolbar drupal/pathauto drupal/token drupal/metatag drupal/bootstrap5 drupal/webform drupal/ctools drupal/computed_field --no-interaction 2>&1 | tail -4'

echo "== Включение модулей и темы =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush en admin_toolbar pathauto token metatag webform ctools computed_field gorod24_registry gorod24_slogan gorod24_price news -y' 2>&1 | tail -10
$J sh -c 'cd /opt/drupal && (vendor/bin/drush theme:enable bootstrap5 -y 2>&1 | tail -2); vendor/bin/drush config:set system.theme default bootstrap5 -y 2>&1 | tail -2'

echo "== Перезапуск PHP-FPM после обновления модулей =="
docker compose restart web >/dev/null
echo "== Ожидание готовности сайта =="
wait_ready && echo "сайт отвечает 200"

echo "== Конфигурация ЛР8 (база: тема, таксономия, тип «Ноутбук», каталог) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab8-config.php' 2>&1 | tail -6

echo "== Конфигурация ЛР11 (статьи, цены, блоки, View) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab11-config.php' 2>&1 | tail -30
$J sh -c 'cd /opt/drupal && vendor/bin/drush cr >/dev/null 2>&1'

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
check "слоган из модуля" "http://localhost:$PORT/" "Город24: новости Минска каждый день"
check "каталог раздела «Техника»" "http://localhost:$PORT/noutbuki" "ThinkPad"
check "страница со всеми статьями" "http://localhost:$PORT/articles" "Статьи портала"
check "цена в карточке ноутбука" "http://localhost:$PORT/noutbuki/lenovo-thinkpad-t490" "Цена"
if [ "$FAIL" = "0" ]; then
    echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
    echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Adminer:    http://localhost:8111 (сервер db, пользователь drupal)"
