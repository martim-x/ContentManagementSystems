#!/bin/sh
# Развёртывание стенда ЛР6: «Создание лент новостей и ссылок.
# Написание своего модуля для Joomla».
#
# Запуск из каталога lab5:
#   sh setup-lab6.sh
#
# Скрипт идемпотентен: повторный запуск не ломает готовый сайт.
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

J="docker compose exec -T web"
PORT=8099

echo "== ЛР6: поднимаем стенд =="
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
$J sh -c 'cd /var/www/html && rm -f lab6-*.php scripts-lab6-*.php && echo "  корень CMS: дубликаты удалены"'

# ---------------------------------------------------------------------------
# 2. Установка Joomla.
#
# Официального joomla-cli для автоматической установки нет (ссылка
# downloads.joomla.org/cli отдаёт 500), поэтому движок ставится напрямую:
#   1) в базу импортируется штатная схема installation/sql/mysql;
#   2) скриптом lab6-install.php создаются configuration.php,
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
		docker compose cp "web:/var/www/html/installation/sql/mysql/$f.sql" "/tmp/lab6-$f.sql" >/dev/null 2>&1
		# #__ в схеме — заполнитель префикса таблиц: официальный
		# инсталлятор подставляет реальный префикс при установке.
		# Прямой импорт оставляет «#__» в именах таблиц, поэтому
		# заменяем на jos_ при заливке.
		sed "s/#__/jos_/g" "/tmp/lab6-$f.sql" | docker compose exec -T db sh -c "mariadb -ujoomla -pjoomla joomla" \
			&& echo "  $f.sql: импортирован" \
			|| echo "  $f.sql: пропущен (уже в базе)"
	done

	echo "-- конфигурация, пользователь, меню --"
	$J php /var/www/html/scripts/lab6-install.php 2>&1 | tail -25
fi

# ---------------------------------------------------------------------------
# 3. Наполнение сайта и настройка модуля (идемпотентные скрипты).
# ---------------------------------------------------------------------------
echo "== Задание 1-2: категория, статьи, лента новостей, ссылка на материал =="
$J php /var/www/html/scripts/lab6-content.php 2>&1 | tail -12

echo "== Задание 3-4: модуль формы контакта с капчей =="
$J php /var/www/html/scripts/lab6-module.php 2>&1 | tail -8

echo "== Доводка: язык, главная страница, оформление модуля =="
$J php /var/www/html/scripts/lab6-finish.php 2>&1 | tail -12

echo "== Пересборка дерева меню =="
$J php /var/www/html/scripts/lab6-menu-tree.php 2>&1 | tail -4

echo "== Русский языковой пакет ru-RU =="
sh make-ru-language.sh 2>&1 | tail -4

# ---------------------------------------------------------------------------
# 5. Расширения ЛР6 (штатная CLI-установка Joomla) и их настройка.
#
# php cli/joomla.php extension:install --path=... — штатная точка входа
# Joomla 5 для установки расширений без браузера.
# ---------------------------------------------------------------------------
echo "== Установка расширений (CLI Joomla) =="
CLI="$J php /var/www/html/cli/joomla.php"

for pkgfile in plg_g24math.zip mod_gorod24share.zip akeeba-backup-9.8.4-core.zip; do
	docker compose cp "packages/$pkgfile" "web:/tmp/$pkgfile" >/dev/null
	if $CLI extension:install --path="/tmp/$pkgfile" 2>&1 | grep -qiE "installed|success"; then
		echo "  $pkgfile: установлен"
	else
		echo "  $pkgfile: смотри вывод выше (возможно, уже установлен)"
	fi
done

# CLI-установка создаёт файлы от root: Joomla (www-data) должна уметь писать
# в каталоги расширений (Akeeba создаёт backups, плагины обновляются).
$J sh -c 'chown -R www-data:www-data /var/www/html'

echo "== Настройка расширений =="
$J php /var/www/html/scripts/lab6-extensions.php 2>&1 | tail -8

echo "== Безопасность (задание 2) =="
$J php /var/www/html/scripts/lab6-security.php 2>&1 | tail -8

echo "== Форма обратной связи (задание 7) =="
$J php /var/www/html/scripts/lab6-contact.php 2>&1 | tail -6

echo "== Поиск (задание 8) =="
$J php /var/www/html/scripts/lab6-search.php 2>&1 | tail -5

echo "== Индексация Smart Search =="
$CLI finder:index 2>&1 | tail -3

echo "== Мета-данные (задание 5) =="
$J php /var/www/html/scripts/lab6-meta.php 2>&1 | tail -6

echo "== Карта сайта (задание 3) =="
$J php /var/www/html/scripts/lab6-sitemap.php 2>&1 | tail -8

echo "== Аналитика (задание 10) =="
$J php /var/www/html/scripts/lab6-analytics.php 2>&1 | tail -3

echo "== Пересборка деревьев (после новых пунктов меню) =="
$J php /var/www/html/scripts/lab6-menu-tree.php 2>&1 | tail -6

# ---------------------------------------------------------------------------
# 6. Проверка стенда curl-ом.
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
check "модуль шеринга на главной"        "http://localhost:$PORT/" "g24share"
check "карта сайта (HTML)"               "http://localhost:$PORT/karta-sajta" "Карта сайта"
check "sitemap.xml"                      "http://localhost:$PORT/sitemap.xml" "<urlset"
check "robots.txt со строкой Sitemap"    "http://localhost:$PORT/robots.txt" "Sitemap:"
check "форма обратной связи с капчей"    "http://localhost:$PORT/kontakty" "g24math"
check "поиск на сайте"                   "http://localhost:$PORT/" "mod-finder"
check "код аналитики в head"             "http://localhost:$PORT/" "G-GOROD24LAB"
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
echo "Сайт:        http://localhost:$PORT"
echo "Админка:     http://localhost:$PORT/administrator (admin / Gorod24!Admin2026, код MFA: 246810)"
echo "phpMyAdmin:  http://localhost:8101 (joomla / joomla)"
echo "Бэкап:       Akeeba Backup — архив в administrator/components/com_akeebabackup/backup/"
echo "ЛР6 ГОТОВО — все задания Joomla_2.pdf (см. README.md)"
