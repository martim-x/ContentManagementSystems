#!/bin/sh
# Уведомление поисковых систем о карте сайта (задание 9 ЛР6).
# Sitemap уже указан в robots.txt; здесь выполняется прямой ping.
# Google закрыл ping-endpoint в 2023 году (возвращает 410) — это штатно:
# актуальные способы уведомления - Search Console и Sitemap в robots.txt.
set -e
SITEMAP="http://localhost:8099/sitemap.xml"
ENC=$(python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1], safe=''))" "$SITEMAP")
echo "== Ping поисковых систем о sitemap =="
echo "Google:  $(curl -s -o /dev/null -w '%{http_code}' "https://www.google.com/ping?sitemap=$ENC") (ping закрыт Google с 2023, ожидается 404/410)"
echo "Bing:    $(curl -s -o /dev/null -w '%{http_code}' "https://www.bing.com/ping?sitemap=$ENC")"
echo "Sitemap URL: $SITEMAP"
