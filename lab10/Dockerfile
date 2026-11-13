# Собственный образ Drupal 11 для ЛР10 «Права пользователей, карта сайта,
# безопасность и эффективность».
FROM drupal:11

# Лимиты PHP
RUN printf 'memory_limit = 256M\nupload_max_filesize = 32M\npost_max_size = 32M\nmax_execution_time = 120\n' \
    > /usr/local/etc/php/conf.d/lab10.ini

# Backup and Migrate делает дамп БД через собственный драйвер на mysqli,
# в образе drupal:11 есть только pdo_mysql.
RUN docker-php-ext-install mysqli

# Drush для автоматизации установки и настройки
RUN cd /opt/drupal && composer require drush/drush --no-interaction 2>&1 | tail -3

# Скрипты лабы
COPY scripts/ /opt/drupal/web/scripts/
RUN mkdir -p /opt/drupal/web/sites/default/files && chown -R www-data:www-data /opt/drupal/web
