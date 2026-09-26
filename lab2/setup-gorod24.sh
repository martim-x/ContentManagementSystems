#!/bin/sh
# Полный накат ЛР2 «Город24» (тема из lab2 курса CMS, контент из setup-content.php)
set -e

# Задание 1: установка WordPress
wp core install --url=http://localhost:8080 --title="Город24" \
  --admin_user=admin --admin_password=admin123 --admin_email=admin@example.com --skip-email

# Задание 5: плагины
wp plugin install updraftplus --activate
wp plugin install contact-form-7 --activate
wp plugin install really-simple-captcha --activate
wp plugin install smart-slider-3 --activate || echo "smart-slider-3 пропущен"
wp plugin install elementor --activate

# Тема из вёрстки ЛР1 (новостной портал «Город24»)
wp theme install /tmp/gorod24.zip --activate

# Контент и настройки: задания 2, 6, 7, 8, 9, 10
wp eval-file /tmp/setup-content.php

# Задание 4: виджеты (минимум 3)
wp widget add search sidebar-1 --order=1
wp widget add recent-posts sidebar-1 --order=2 --number=5
wp widget add categories sidebar-1 --order=3 --count=true
wp widget add tag_cloud sidebar-1 --order=4
wp widget list sidebar-1

wp plugin list --status=active
echo "=== GOROD24 READY ==="
