#!/bin/sh
# Русский языковой пакет ru-RU для Joomla 5.
#
# Официальные пакеты Joomla раздаются с downloads.joomla.org, но в
# момент сборки стенда сервер отдавал по ссылке 500, поэтому пакет
# собирается здесь: каталоги en-GB копируются в ru-RU, после чего
# строки, видимые на сайте лабораторной, заменяются русскими.
#
# Строки хранятся в обычных .ini-файлах Joomla - это штатный
# механизм локализации, такой же, как у поставляемого пакета.
#
# Запуск: sh lab5/make-ru-language.sh
#
# Скрипт идемпотентен: повторный запуск перезаписывает файлы.

set -e

LAB_DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="docker compose -f ${LAB_DIR}/docker-compose.yml"

echo "== Сборка языкового пакета ru-RU =="

# ---------------------------------------------------------------------------
# 1. Копируем структуру en-GB в ru-RU
# ---------------------------------------------------------------------------
# Нужны оба каталога: language/ для компонентов сайта и
# administrator/language/ для строк админки. Они живут внутри
# контейнера, поэтому копирование идёт через docker compose exec.
$COMPOSE exec -T web sh -c '
	set -e
	for pair in "language" "administrator/language"; do
		src="/var/www/html/${pair}/en-GB"
		dst="/var/www/html/${pair}/ru-RU"

		if [ ! -d "$src" ]; then
			echo "  ${pair}/en-GB: нет исходника, пропуск"
			continue
		fi

		rm -rf "$dst"
		cp -r "$src" "$dst"
		echo "  ${pair}: en-GB скопирован в ru-RU"
	done
'

# ---------------------------------------------------------------------------
# 2. Русские строки сайта
# ---------------------------------------------------------------------------
# Ниже только те ключи, которые видны на страницах лабораторной
# (интерфейс сайта, модуль формы контакта, хлебные крошки, логин).
# Остальные строки остаются английскими - для выполнения заданий
# лабораторной этого достаточно, а выдумывать перевод всего Joomla
# было бы недостоверно.
echo "  применяем русские строки..."

$COMPOSE exec -T web sh -c '
	set -e

	LANG_DIR=/var/www/html/language/ru-RU

	# --- Общие строки интерфейса -----------------------------------
	cat > "$LANG_DIR/joomla.ini" <<EOF
; Русские строки общего интерфейса Joomla для лабораторной работы №5.
ERROR="Ошибка"
INFO="Информация"
MESSAGE="Сообщение"
NOTICE="Уведомление"
SUCCESS="Успешно"
WARNING="Предупреждение"
EOF

	# Строки сайта и хлебных крошек.
	cat > "$LANG_DIR/tpl_cassiopeia.ini" <<EOF
; Русские строки темы cassiopeia.
MENU="Меню"
BREADCRUMBS="Хлебные крошки"
EOF

	# Наш модуль формы контакта.
	cat > "$LANG_DIR/mod_gorod24contact.ini" <<EOF
; Русские строки модуля формы контакта.
MOD_GOROD24CONTACT_FIELD_NAME="Имя пользователя"
MOD_GOROD24CONTACT_FIELD_EMAIL_LBL="Электронная почта"
MOD_GOROD24CONTACT_FIELD_TEXT="Текст обращения"
MOD_GOROD24CONTACT_FIELD_CAPTCHA="Решите пример, чтобы доказать, что вы человек"
MOD_GOROD24CONTACT_BUTTON_SEND="Отправить обращение"
MOD_GOROD24CONTACT_HINT_EMAIL="Письма приходят на %s"
MOD_GOROD24CONTACT_MAIL_SUBJECT="Обращение с сайта Город24"
MOD_GOROD24CONTACT_MAIL_BODY="Обращение с сайта.\n\nИмя: %1\$s\nEmail: %2\$s\n\nТекст обращения:\n%3\$s"
MOD_GOROD24CONTACT_MSG_OK="Спасибо! Обращение отправлено, мы ответим на указанный email."
MOD_GOROD24CONTACT_MSG_BADTOKEN="Сессия устарела. Попробуйте отправить обращение ещё раз."
MOD_GOROD24CONTACT_MSG_NEED_NAME="Укажите, как вас зовут."
MOD_GOROD24CONTACT_MSG_BAD_EMAIL="Укажите корректный email."
MOD_GOROD24CONTACT_MSG_NEED_TEXT="Напишите текст обращения."
MOD_GOROD24CONTACT_MSG_BAD_CAPTCHA="Ответ на пример неверный. Попробуйте новый пример."
MOD_GOROD24CONTACT_MSG_SEND_FAIL="Не удалось отправить письмо. Попробуйте позже."
MOD_GOROD24CONTACT_MSG_SAVED="Обращение принято и сохранено. На стенде письма не отправляются: SMTP не настроен, текст обращения записан в administrator/logs/g24contact.log."
EOF

	# Метаданные языка: тег берётся из install.xml, поэтому копия
	# en-GB объявляла бы себя английским языком, и Joomla продолжала
	# бы загружать переводы из en-GB вместо ru-RU.
	sed -i \
		-e "s|<name>English (en-GB)</name>|<name>Russian (ru-RU)</name>|" \
		-e "s|<tag>en-GB</tag>|<tag>ru-RU</tag>|" \
		-e "s|<description>en-GB site language</description>|<description>ru-RU site language</description>|" \
		"$LANG_DIR/install.xml"

	if [ -f /var/www/html/administrator/language/ru-RU/install.xml ]; then
		sed -i \
			-e "s|<name>English (en-GB)</name>|<name>Russian (ru-RU)</name>|" \
			-e "s|<tag>en-GB</tag>|<tag>ru-RU</tag>|" \
			-e "s|<description>en-GB administrator language</description>|<description>ru-RU administrator language</description>|" \
			/var/www/html/administrator/language/ru-RU/install.xml
	fi

	# localise.php из en-GB объявляет класс En_GBLocalise. Под тегом
	# ru-RU такой класс конфликтует с уже загруженным en-GB, и Joomla
	# падает с «Cannot declare class En_GBLocalise». Имя класса должно
	# соответствовать языку каталога, поэтому переименовываем его.
	sed -i "s/En_GBLocalise/Ru_RULocalise/g" "$LANG_DIR/localise.php"

	echo "  метаданные install.xml и localise.php переведены на ru-RU"
	echo "  строки записаны"
'

echo "== Пакет ru-RU готов =="

# ---------------------------------------------------------------------------
# 3. Проверка
# ---------------------------------------------------------------------------
$COMPOSE exec -T web sh -c '
	set -e
	for f in /var/www/html/language/ru-RU/joomla.ini \
	         /var/www/html/language/ru-RU/mod_gorod24contact.ini \
	         /var/www/html/language/ru-RU/tpl_cassiopeia.ini; do
		if [ -f "$f" ]; then
			echo "  есть $f"
		else
			echo "  ОТСУТСТВУЕТ $f"
			exit 1
		fi
	done
'