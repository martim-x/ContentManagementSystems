#!/bin/sh
# Развёртывание стенда ЛР8: «Знакомство с Drupal» (labs/Drupal/Drupal_1.pdf).
# Скрипт идемпотентен: повторный запуск не создаёт дубликатов и не ломает сайт.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8104
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


echo "== ЛР8: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты, ассеты и библиотеки в контейнер =="
docker compose cp scripts/. web:/opt/drupal/web/scripts/
mkdir -p web-libraries 2>/dev/null || true

echo "== Установка Drupal (если ещё не установлен) =="
if $J sh -c 'test -f /var/www/html/sites/default/settings.php' 2>/dev/null; then
    echo "Drupal уже установлен — пропускаем"
else
    echo "-- drush site:install --"
    $J sh -c 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=pgsql://drupal:drupal@db/drupal --site-name="Город24" --account-name=admin --account-pass="Gorod24!Admin2026" -y' 2>&1 | tail -8
fi

echo "== Установка модулей (composer) =="
$J sh -c 'cd /opt/drupal && composer require drupal/admin_toolbar drupal/pathauto drupal/token drupal/metatag drupal/bootstrap5 drupal/webform drupal/ctools drupal/yoast_seo --no-interaction 2>&1 | tail -3'

echo "== Включение модулей и темы =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush en admin_toolbar pathauto token metatag webform ctools yoast_seo -y' 2>&1 | tail -6
$J sh -c 'cd /opt/drupal && (vendor/bin/drush theme:enable bootstrap5 -y 2>&1 | tail -2); vendor/bin/drush config:set system.theme default bootstrap5 -y 2>&1 | tail -2'

echo "== Перезапуск PHP-FPM после обновления модулей =="
docker compose restart web >/dev/null
echo "== Ожидание готовности сайта =="
wait_ready && echo "сайт отвечает 200"

echo "== Библиотека SEO-анализа (rtseo.js) =="
# yoast_seo подключает /libraries/rtseo.js/dist/rtseo.js; без неё виджет SEO остаётся без анализа.
$J sh -c 'mkdir -p /opt/drupal/web/libraries/rtseo.js'
docker compose cp scripts/libraries/rtseo.js/dist web:/opt/drupal/web/libraries/rtseo.js/
$J sh -c 'chmod -R a+r /opt/drupal/web/libraries/rtseo.js && ls /opt/drupal/web/libraries/rtseo.js/dist'

echo "== Конфигурация: тема, таксономия, тип материала, каталог, меню, веб-форма =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab8-config.php' 2>&1 | tail -25
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
check "лента новостей на главной" "http://localhost:$PORT/" "field--name-field-lead"
check "иллюстрация новости" "http://localhost:$PORT/" "news/news"
check "метатег description в коде страницы" "http://localhost:$PORT/" 'name="description"'
check "каталог раздела «Техника»" "http://localhost:$PORT/noutbuki" "ThinkPad"
check "страница рубрики" "http://localhost:$PORT/rubrika/gorod" "Город"
check "форма «Прислать новость»" "http://localhost:$PORT/form/prislat-novost" "Прислать новость"
check "страница входа" "http://localhost:$PORT/user/login" "Log in"
if [ "$FAIL" = "0" ]; then
    echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
    echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Adminer:    http://localhost:8105 (сервер db, пользователь drupal)"
