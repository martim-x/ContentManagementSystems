#!/bin/sh
# Развёртывание стенда ЛР10: права пользователей, контактная форма, карта сайта,
# микроразметка, антиспам, резервное копирование, статистика и кэширование
# (labs/Drupal/Drupal_3.pdf). Скрипт идемпотентен.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8108
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

echo "== ЛР10: поднимаем стенд =="
docker compose up -d --build --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты и ассеты в контейнер =="
docker compose cp scripts/. web:/opt/drupal/web/scripts/
$J sh -c 'mkdir -p /opt/drupal/web/modules/custom'
docker compose cp scripts/modules/. web:/opt/drupal/web/modules/custom/

echo "== Установка Drupal (если ещё не установлен) =="
if $J sh -c 'test -f /var/www/html/sites/default/settings.php' 2>/dev/null; then
    echo "Drupal уже установлен — пропускаем"
else
    echo "-- drush site:install --"
    $J sh -c 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=mysql://drupal:drupal@db/drupal --site-name="Город24" --account-name=admin --account-pass="Gorod24!Admin2026" -y' 2>&1 | tail -8
fi

echo "== Приватные файлы для резервных копий =="
$J sh -c 'grep -q "sites/default/private" /var/www/html/sites/default/settings.php || cat /opt/drupal/web/scripts/settings-private.txt >> /var/www/html/sites/default/settings.php; mkdir -p /var/www/html/sites/default/private/backup_migrate; chmod -R 777 /var/www/html/sites/default/private'

echo "== Установка модулей (composer) =="
$J sh -c 'cd /opt/drupal && composer require drupal/admin_toolbar drupal/pathauto drupal/token drupal/metatag drupal/bootstrap5 drupal/webform drupal/ctools drupal/simple_sitemap drupal/honeypot drupal/captcha drupal/backup_migrate drupal/statistics drupal/schema_metatag --no-interaction 2>&1 | tail -4'

echo "== Включение модулей и темы =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush en admin_toolbar pathauto token metatag webform ctools simple_sitemap honeypot captcha backup_migrate statistics contact schema_article schema_organization schema_web_site gorod24_analytics -y' 2>&1 | tail -8
$J sh -c 'cd /opt/drupal && (vendor/bin/drush theme:enable bootstrap5 -y 2>&1 | tail -2); vendor/bin/drush config:set system.theme default bootstrap5 -y 2>&1 | tail -2'

echo "== Перезапуск PHP-FPM после обновления модулей =="
docker compose restart web >/dev/null
echo "== Ожидание готовности сайта =="
wait_ready && echo "сайт отвечает 200"

echo "== Конфигурация ЛР8 (база: тема, таксономия, тип «Ноутбук», каталог) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab8-config.php' 2>&1 | tail -8

echo "== Конфигурация ЛР10 (пользователь, контактная форма, sitemap, микроразметка, кэш) =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush scr /opt/drupal/web/scripts/lab10-config.php' 2>&1 | tail -30

echo "== Генерация карты сайта =="
$J sh -c 'cd /opt/drupal && vendor/bin/drush simple-sitemap:rebuild-queue 2>&1 | tail -3' || true
for i in 1 2 3 4 5; do
    $J sh -c 'cd /opt/drupal && vendor/bin/drush simple-sitemap:generate 2>&1 | tail -1' || true
done
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
check "контактная форма" "http://localhost:$PORT/contact" "Обратная связь"
check "карта сайта XML" "http://localhost:$PORT/sitemap.xml" "<urlset"
check "микроразметка JSON-LD" "http://localhost:$PORT/" "application/ld+json"
check "каталог раздела «Техника»" "http://localhost:$PORT/noutbuki" "ThinkPad"
if [ "$FAIL" = "0" ]; then
    echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
    echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Редактор:   redaktor / Redaktor!2026"
echo "Adminer:    http://localhost:8109 (сервер db, пользователь drupal)"
