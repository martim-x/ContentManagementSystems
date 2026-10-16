# Собственный образ Joomla 5 для ЛР7 «Управление пользователями и создание
# уровней доступа».
#
# База — официальный joomla:5: в нём уже включён mod_rewrite, для /var/www
# стоит AllowOverride All (SEF-ссылки Joomla работают сразу) и установлены
# расширения PHP, которых Joomla требует (gd, intl, zip, mysqli, pdo_mysql).
# Сюда добавляется только то, чего в базе нет:
#
#   1. Увеличенные лимиты PHP. У базы upload_max_filesize = 2M,
#      post_max_size = 8M — слишком мало, чтобы загружать архивы расширений
#      через админку (в ЛР7 расширения не ставятся, но лимиты нужны для
#      работы редактора статей из-под старосты и декана).
#   2. Скрипты автоматизации лабы внутри дистрибутива. При первом старте
#      том joomla_data пуст, и Docker копирует в него всё содержимое
#      /var/www/html вместе с каталогом scripts/.
#
# Сборка: docker compose build (из каталога lab7)
FROM joomla:5

# Лимиты PHP: память — под работу CMS, размеры загрузок и время выполнения —
# под работу с материалами из фронтенда.
RUN printf 'memory_limit = 256M\nupload_max_filesize = 32M\npost_max_size = 32M\nmax_execution_time = 120\nmax_input_time = 120\n' \
    > /usr/local/etc/php/conf.d/lab7.ini

# Скрипты лабы кладутся в дистрибутив CMS. На живом стенде их обновляет
# setup-lab7.sh (docker compose cp), эта строка важна для первого старта
# с пустым томом: содержимое образа копируется в новый named volume.
COPY scripts/ /var/www/html/scripts/
