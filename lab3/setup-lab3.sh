#!/bin/sh
# Развёртывание стенда ЛР3: WordPress + тема «Город24» + контент + Elementor.
#
# Запуск из каталога lab3:
#   sh setup-lab3.sh
#
# Скрипт идемпотентен: повторный запуск не ломает готовый сайт.
set -e

# У wpcli включён профиль tools, поэтому compose run его поднимает сам:
# отдельный флаг --profile у команды run не поддерживается.
WP="docker compose run --rm -T wpcli wp --path=/var/www/html"
PORT=8090

echo "== ЛР3: поднимаем стенд =="
docker compose up -d --wait db wordpress phpmyadmin

echo
echo "== Задание 1: установка WordPress =="
if $WP core is-installed 2>/dev/null; then
	echo "WordPress уже установлен, пропускаем"
else
	$WP core install --url=http://localhost:$PORT --title="Город24" \
		--admin_user=admin --admin_password=admin123 \
		--admin_email=admin@example.com --skip-email
fi

echo
echo "== Задание 5: плагины =="
for plugin in updraftplus contact-form-7 really-simple-captcha elementor; do
	$WP plugin is-installed "$plugin" || $WP plugin install "$plugin" --activate
done

echo
echo "== Тема «Город24» (вёрстка из ЛР1) =="
docker compose cp theme/gorod24 wordpress:/tmp/gorod24 >/dev/null
docker compose exec -T wordpress sh -c \
	'rm -rf /var/www/html/wp-content/themes/gorod24 && cp -r /tmp/gorod24 /var/www/html/wp-content/themes/gorod24'
$WP theme activate gorod24

echo
echo "== Задания 2, 6, 7, 8, 9, 10: контент и настройки =="
$WP eval-file - < scripts/setup-content.php

echo
echo "== Задание 4: виджеты =="
$WP widget add search sidebar-1 --order=1 2>/dev/null || true
$WP widget add recent-posts sidebar-1 --order=2 --number=5 2>/dev/null || true
$WP widget add categories sidebar-1 --order=3 --count=true 2>/dev/null || true
$WP widget add tag_cloud sidebar-1 --order=4 2>/dev/null || true

echo
echo "== Задания 1 и 4 ЛР3: лендинг, шаблон, блоки, плагины блоков =="
$WP plugin is-installed kadence-blocks || $WP plugin install kadence-blocks --activate
$WP plugin is-installed stackable-ultimate-gutenberg-blocks || \
	$WP plugin install stackable-ultimate-gutenberg-blocks --activate
$WP eval-file - < scripts/setup-lab3.php

echo
echo "== Итог =="
$WP theme list --status=active --fields=name,status
$WP plugin list --status=active --fields=name,status --format=table
echo
echo "Сайт:      http://localhost:$PORT"
echo "Админка:   http://localhost:$PORT/wp-admin (admin / admin123)"
echo "phpMyAdmin: http://localhost:8092 (wp / wppass)"
echo "== ЛР3 READY =="