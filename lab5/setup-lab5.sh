#!/bin/sh
# Развёртывание стенда ЛР5: «Создание лент новостей и ссылок.
# Написание своего модуля для Joomla».
#
# Запуск из каталога lab5:
#   sh setup-lab5.sh
#
# Скрипт идемпотентен: повторный запуск не ломает готовый сайт.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

J="docker compose exec -T web"
PORT=8098

echo "== ЛР5: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

# ---------------------------------------------------------------------------
# 1. Скрипты лабы и файлы модуля — в том joomla_data.
#
# На живом томе содержимое нового образа уже не применяется (том
# заполняется только при первом старте), поэтому копируем явно.
# ---------------------------------------------------------------------------
echo "== Задание: установка Joomla 5 =="

echo "-- скрипты лабы в дистрибутив --"
docker compose cp scripts/. web:/var/www/html/scripts/

echo "-- файлы модуля mod_gorod24contact --"
$J mkdir -p /var/www/html/modules/mod_gorod24contact
docker compose cp module/mod_gorod24contact/. web:/var/www/html/modules/mod_gorod24contact/

# Дубликаты скриптов в корне CMS — следы прошлых запусков, они не нужны:
# оригиналы лежат в scripts/.
$J sh -c 'cd /var/www/html && rm -f lab5-*.php scripts-lab5-*.php && echo "  корень CMS: дубликаты удалены"'

# ---------------------------------------------------------------------------
# 2. Установка Joomla.
#
# Официального joomla-cli для автоматической установки нет (ссылка
# downloads.joomla.org/cli отдаёт 500), поэтому движок ставится напрямую:
#   1) в базу импортируется штатная схема installation/sql/mysql;
#   2) скриптом lab5-install.php создаются configuration.php,
#      пользователь admin и корневой пункт меню.
#
# mysql-клиента в образе joomla нет, поэтому файлы схемы копируются
# в контейнер db.
# ---------------------------------------------------------------------------
if $J sh -c 'test -f /var/www/html/configuration.php' 2>/dev/null; then

	echo "Joomla уже установлена — установка пропущена"
else
	echo "-- импорт схемы --"
	# base.sql содержит CREATE TABLE, extensions.sql — данные расширений,
	# supports.sql — служебные таблицы поддержки. Импортируются в этом
	# порядке, иначе внешние ключи не создадутся.
	for f in base extensions supports; do
		# Файлы схемы берём прямо из контейнера web, где распакован
		# дистрибутив Joomla, и заливаем в контейнер db.
		docker compose cp "web:/var/www/html/installation/sql/mysql/$f.sql" "/tmp/lab5-$f.sql" >/dev/null 2>&1
		docker compose exec -T db sh -c "mariadb -ujoomla -pjoomla joomla" < "/tmp/lab5-$f.sql" \
			&& echo "  $f.sql: импортирован" \
			|| echo "  $f.sql: пропущен (уже в базе)"
	done

	echo "-- конфигурация, пользователь, меню --"
	$J php /var/www/html/scripts/lab5-install.php 2>&1 | tail -25
fi

# ---------------------------------------------------------------------------
# 3. Наполнение сайта и настройка модуля (идемпотентные скрипты).
# ---------------------------------------------------------------------------
echo "== Задание 1-2: категория, статьи, лента новостей, ссылка на материал =="
$J php /var/www/html/scripts/lab5-content.php 2>&1 | tail -12

echo "== Задание 3-4: модуль формы контакта с капчей =="
$J php /var/www/html/scripts/lab5-module.php 2>&1 | tail -8

echo "== Доводка: язык, главная страница, оформление модуля =="
$J php /var/www/html/scripts/lab5-finish.php 2>&1 | tail -12

echo "== Пересборка дерева меню =="
$J php /var/www/html/scripts/lab5-menu-tree.php 2>&1 | tail -4

echo "== Русский языковой пакет ru-RU =="
sh make-ru-language.sh 2>&1 | tail -4

# ---------------------------------------------------------------------------
# 4. Проверка стенда curl-ом.
# ---------------------------------------------------------------------------
echo "== Проверка =="
check() {
	# check "<имя проверки>" "<url>" "<подстрока, которая обязана быть в ответе>"
	if curl -s "$2" | grep -q "$3"; then
		echo "OK    $1  $2"
	else
		echo "FAIL  $1  $2"
		FAIL=1
	fi
}
FAIL=0
check "сайт отвечает, имя сайта в HTML"  "http://localhost:$PORT/" "Город24"
check "категория «Новости»"              "http://localhost:$PORT/novosti" "Новости"
check "статья со ссылкой на материал"    "http://localhost:$PORT/novosti/novytramvay-v-minske" "Читайте также"
check "страница ленты новостей"          "http://localhost:$PORT/rss-lenta" "RSS"
check "RSS-лента статей"                 "http://localhost:$PORT/index.php?format=feed&type=rss" "<rss"
check "админка"                          "http://localhost:$PORT/administrator/" "Joomla"
check "модуль формы на главной"          "http://localhost:$PORT/" "g24contact"
if [ "$FAIL" = "0" ]; then
	echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
	echo "ЕСТЬ ПРОВАЛЫ — смотри сообщения FAIL выше"
	exit 1
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / admin)"
echo "Админка:    http://localhost:$PORT/administrator (admin / admin)"
echo "phpMyAdmin: http://localhost:8100 (joomla / joomla)"
echo "== ЛР5 ГОТОВО =="
