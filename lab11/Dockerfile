# Собственный образ Drupal 11 для ЛР11 «Расширение функциональности:
# модули и Views».
FROM drupal:11

# Лимиты PHP
RUN printf 'memory_limit = 256M\nupload_max_filesize = 32M\npost_max_size = 32M\nmax_execution_time = 120\n' \
    > /usr/local/etc/php/conf.d/lab11.ini

# Drush для автоматизации установки и настройки
RUN cd /opt/drupal && composer require drush/drush --no-interaction 2>&1 | tail -3

# Скрипты лабы
COPY scripts/ /opt/drupal/web/scripts/
RUN mkdir -p /opt/drupal/web/sites/default/files && chown -R www-data:www-data /opt/drupal/web
