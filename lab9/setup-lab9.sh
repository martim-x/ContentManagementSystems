#!/bin/sh
# Развёртывание стенда ЛР9: «Работа с публикациями и меню в Drupal»
# (labs/Drupal/Drupal_2.pdf). Скрипт идемпотентен.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8106
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


echo "== ЛР9: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты, ассеты и библиотеки в контейнер =="
docker compose cp scripts/. web:/opt/drupal/web/scripts/

echo "== Установка Drupal (если ещё не установлен) =="
if $J sh -c 'test -f /var/www/html/sites/default/settings.php' 2>/dev/null; then
    echo "Drupal уже установлен — пропускаем"
else
    echo "-- drush site:install --"
    $J sh -c 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=pgsql://drupal:drupal@db/drupal --site-name="Город24" --account-name=admin --account-pass="Gorod24!Admin2026" -y' 2>&1 | tail -6
fi

echo "== Установка модулей (composer) =="
$J sh -c 'cd /opt/drupal && composer require drupal/admin_toolbar drupal/pathauto drupal/token drupal/metatag drupal/bootstrap5 drupal/webform drupal/ctools drupal/colorbox --no-interaction 2>&1 | tail -3'

echo "== Включение модулей и темы =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush en admin_toolbar pathauto token metatag webform ctools colorbox -y' 2>&1 | tail -6
$J sh -c 'cd /opt/drupal && (vendor/bin/drush theme:enable bootstrap5 -y 2>&1 | tail -2); vendor/bin/drush config:set system.theme default bootstrap5 -y 2>&1 | tail -2'

echo "== Перезапуск PHP-FPM после обновления модулей =="
docker compose restart web >/dev/null
echo "== Ожидание готовности сайта =="
wait_ready && echo "сайт отвечает 200"

echo "== Библиотеки Colorbox и DOMPurify (лайтбокс галереи) =="
$J sh -c 'mkdir -p /opt/drupal/web/libraries/colorbox /opt/drupal/web/libraries/DOMPurify'
docker compose cp scripts/libraries/colorbox/. web:/opt/drupal/web/libraries/colorbox/
docker compose cp scripts/libraries/DOMPurify/. web:/opt/drupal/web/libraries/DOMPurify/
$J sh -c 'chmod -R a+r /opt/drupal/web/libraries/colorbox /opt/drupal/web/libraries/DOMPurify && ls /opt/drupal/web/libraries'

echo "== Конфигурация ЛР8 (база: таксономия, тип «Ноутбук», каталог) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab8-config.php' 2>&1 | tail -6

echo "== Конфигурация ЛР9 (блоки, статья, страницы, меню) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab9-config.php' 2>&1 | tail -25
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
check "блок «О портале»" "http://localhost:$PORT/" "О портале"
check "блок «Рубрики»" "http://localhost:$PORT/" "Рубрики"
check "страница «История фирмы»" "http://localhost:$PORT/stranicy/istoriya-firmy-lenovo" "История фирмы Lenovo"
check "галерея: миниатюры" "http://localhost:$PORT/stranicy/galereya-noutbukov-lenovo" "gallery/"
check "галерея: подключён Colorbox" "http://localhost:$PORT/stranicy/galereya-noutbukov-lenovo" "colorbox"
check "прайс-лист: таблица" "http://localhost:$PORT/stranicy/prays-list-noutbukov-lenovo" "<table"
check "статья «О редакции»" "http://localhost:$PORT/novosti/kak-rabotaet-redakciya-gorod24" "редакция"
check "каталог раздела «Техника»" "http://localhost:$PORT/noutbuki" "ThinkPad"
check "дополнительное меню в футере" "http://localhost:$PORT/" "Дополнительное меню"
if [ "$FAIL" = "0" ]; then
    echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
    echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Adminer:    http://localhost:8107 (сервер db, пользователь drupal)"
