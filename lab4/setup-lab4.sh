#!/bin/sh
# Развёртывание стенда ЛР4: «Перенос вёрстки на WordPress».
#
# Запуск из каталога lab4:
#   sh setup-lab4.sh
#
# Скрипт идемпотентен: повторный запуск не ломает готовый сайт.
set -e

# У wpcli включён профиль tools, поэтому compose run его поднимает сам:
# отдельный флаг --profile у команды run не поддерживается.
WP="docker compose run --rm -T -e HTTP_HOST=localhost:8094 -e REQUEST_URI=/ wpcli wp --path=/var/www/html"
PORT=8094

echo "== ЛР4: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

# ---------------------------------------------------------------------------
echo "== Задание 1, 2: установка WordPress =="
# - --allow-root: wpcli в контейнере работает от пользователя www-data (uid 33).
# - wp core install пропускается, если WordPress уже установлен.
$WP core is-installed 2>/dev/null || $WP core install \
	--url="http://localhost:$PORT" \
	--title="Город24" \
	--admin_user="admin" \
	--admin_password="admin123" \
	--admin_email="admin@gorod24.by" \
	--skip-email

$WP option update home "http://localhost:$PORT"
$WP option update siteurl "http://localhost:$PORT"

# ---------------------------------------------------------------------------
echo "== Задание 3: пустая тема с underscores.me =="
# ТЗ требует скачать тему-скелет с underscores.me и активировать её.
# Загрузка идёт через официальный ZIP-архив репозитория _s.
if ! $WP theme is-installed gorod24-skeleton 2>/dev/null; then
	curl -sL "https://github.com/Automattic/_s/archive/refs/heads/master.zip" \
		-o /tmp/_s-master.zip
	if [ -s /tmp/_s-master.zip ]; then
		docker compose cp /tmp/_s-master.zip wordpress:/tmp/_s.zip
		docker compose exec -T wordpress sh -c 'cp /tmp/_s.zip /var/www/html/_s.zip'
		$WP theme install /var/www/html/_s.zip --activate --force >/dev/null
		docker compose exec -T wordpress sh -c 'rm -f /var/www/html/_s.zip'
		echo "тема-скелет _s установлена и активирована"
	else
		echo "ВНИМАНИЕ: не удалось скачать _s, продолжаю без неё"
	fi
fi

# ---------------------------------------------------------------------------
echo "== Задания 1, 2: собственная тема на основе вёрстки ЛР1 =="
# Тема лежит в theme/gorod24-theme/. Копируем её в wp-content/themes
# и активируем — это и есть перенос вёрстки на движок WordPress.
docker compose cp theme/gorod24-theme/. wordpress:/tmp/gorod24-theme
docker compose exec -T wordpress sh -c \
	'rm -rf /var/www/html/wp-content/themes/gorod24-theme && cp -r /tmp/gorod24-theme /var/www/html/wp-content/themes/gorod24-theme'
$WP theme activate gorod24-theme
echo "тема: $($WP theme list --field=name --status=active)"

# ---------------------------------------------------------------------------
echo "== Задания 4, 10, 11, 12: страницы, меню, настройки =="
$WP eval-file - < scripts/setup-lab4.php

# ---------------------------------------------------------------------------
echo "== Проверка: меню на местах =="
$WP menu location list 2>/dev/null || $WP eval '
foreach ( get_nav_menu_locations() as $loc => $id ) {
	echo "  {$loc} -> " . wp_get_nav_menu_object( $id )->name . " (" . count( wp_get_nav_menu_items( $id ) ) . " пунктов)\n";
}'

# ---------------------------------------------------------------------------
echo "== Итог =="
$WP theme list --format=table 2>/dev/null
$WP eval '
echo "название: " . get_bloginfo("name") . "\n";
echo "описание: " . get_bloginfo("description") . "\n";
echo "страница на главной: " . get_option("page_on_front") . "\n";
echo "страница записей: " . get_option("page_for_posts") . "\n";
echo "главная новость: " . get_bloginfo("name") . "\n";'

echo
echo "Сайт:      http://localhost:$PORT"
echo "Админка:   http://localhost:$PORT/wp-admin (admin / admin123)"
echo "phpMyAdmin: http://localhost:8096 (wp / wppass)"
echo "== ЛР4 READY =="