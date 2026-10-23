#!/bin/sh
# Развёртывание стенда ЛР7: «Управление пользователями и уровни доступа» (Joomla).
#
# Запуск из каталога lab7:
#   sh setup-lab7.sh
#
# Скрипт идемпотентен: повторный запуск не ломает готовый сайт.
# Требуется, чтобы в томах уже был перенесённый из ЛР6 сайт «Город24»
# (дамп /tmp/lab7-from-lab6.sql + файлы cms-joomla-lab6_joomla_data).
set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$LAB_DIR"

PORT=8102

echo "== ЛР7: поднимаем стенд =="
docker compose up -d --wait
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "== Копируем скрипты лабы в том =="
docker compose cp scripts/. web:/var/www/html/scripts/

J="docker compose exec -T web"

echo "== Подготовка сайта (порт, MFA, пароли, регистрация) =="
$J php /var/www/html/scripts/lab7-prepare.php 2>&1 | tail -8

echo "== Пользователи и группы =="
$J php /var/www/html/scripts/lab7-users.php 2>&1 | tail -8

echo "== Уровни доступа =="
$J php /var/www/html/scripts/lab7-levels.php 2>&1 | tail -8

echo "== Категории и материалы =="
$J php /var/www/html/scripts/lab7-categories.php 2>&1 | tail -8

echo "== Права групп (Общие настройки) =="
$J php /var/www/html/scripts/lab7-permissions.php 2>&1 | tail -8

echo "== Пункты меню =="
$J php /var/www/html/scripts/lab7-menu.php 2>&1 | tail -8

echo "== Проверка (на хосте) =="
FAIL=0
check() {
	if curl -s "$2" | grep -q "$3"; then
		echo "OK    $1"
	else
		echo "FAIL  $1  $2"
		FAIL=1
	fi
}
check "сайт отвечает, имя сайта в HTML" "http://localhost:$PORT/" "Город24"
check "админка" "http://localhost:$PORT/administrator/" "Joomla"
check "регистрация" "http://localhost:$PORT/registraciya" "Регистрация"
check "вход" "http://localhost:$PORT/vhod" "Вход"
for u in sozdat-material-dekan sozdat-material-starosta; do
	code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:$PORT/$u")
	if [ "$code" = "303" ] || [ "$code" = "200" ]; then
		echo "OK    пункт меню $u (HTTP $code — доступ ограничен, как и задумано)"
	else
		echo "FAIL  пункт меню $u (HTTP $code)"
		FAIL=1
	fi
done
if [ "$FAIL" = "0" ]; then
	echo "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ"
else
	echo "ЕСТЬ ПРОВАЛЫ — смотри выше"
	exit 1
fi

echo
echo "Сайт:       http://localhost:$PORT (admin / Gorod24!Admin2026)"
echo "Админка:    http://localhost:$PORT/administrator"
echo "phpMyAdmin: http://localhost:8103 (joomla / joomla)"