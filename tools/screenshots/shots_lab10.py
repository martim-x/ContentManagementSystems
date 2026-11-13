"""Скриншоты ЛР10 (стенд 8108) по пунктам методички labs/Drupal/Drupal_3.pdf.

Сайт — городской новостной портал «Город24». Кадры снимаются headless Chrome
через CDP; перед кадром статистики счётчик модуля Statistics получает реальные
просмотры (POST на его собственный endpoint).

Запуск (macOS, из корня репозитория):
    tools/screenshots/.venv/bin/python tools/screenshots/shots_lab10.py
"""
import os
import re
import sys
import time
import urllib.parse
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from cdp import Chrome  # noqa: E402
from common import BASE_LAB, nid_by_alias, one_time_login  # noqa: E402

LAB = "lab10"
OUT = os.path.join(BASE_LAB, LAB, "report")
BASE = "http://localhost:8108"
os.makedirs(OUT, exist_ok=True)

c = Chrome("/tmp/chrome-lab10-cdp", width=1440, height=1100, scale=2)
# Профиль Chrome переживает запуски: без сброса cookies «гостевые» кадры
# снимаются уже залогиненным администратором.
c.send("Network.clearBrowserCookies")
shots = []


def shot(name, url=None, wait=2.0, full=False, scroll=None, scroll_elem=None, click=None,
         trusted_click=None, scroll_js=None):
    if url:
        c.goto(url, wait=wait)
    if click:
        c.click(click)
    if trusted_click:
        c.trusted_click(trusted_click, wait=6)
    if scroll_elem:
        c.js("(function(){var e=document.querySelector(%r); if(e){e.scrollIntoView({block:'center'});}})()" % scroll_elem)
        time.sleep(0.4)
    if scroll_js:
        c.js(scroll_js)
        time.sleep(0.4)
    if scroll is not None:
        c.scroll_to(scroll)
    path = os.path.join(OUT, name)
    c.screenshot(path, full_page=full)
    size = os.path.getsize(path)
    title = c.js("document.title") or ""
    shots.append((name, size, title))
    print(f"{name}\t{size}б\t{title}")
    return path


def record_views(nid, times=3):
    """Просмотры через endpoint модуля Statistics (как это делает его JS)."""
    url = BASE + "/modules/contrib/statistics/statistics.php"
    for _ in range(times):
        data = urllib.parse.urlencode({"nid": nid}).encode()
        try:
            urllib.request.urlopen(url, data=data, timeout=10).read()
        except Exception as exc:  # noqa: BLE001
            print("не удалось записать просмотр", nid, exc)


news_alias = "/novosti/minsk-perevodit-eshchyo-40-avtobusov-na-elektroprivod"
news_nid = nid_by_alias(LAB, news_alias)

# --- Гость: контактная форма и CAPTCHA --------------------------------------
shot("task2a_контактная_форма_на_сайте.png", BASE + "/contact", wait=2.0, full=True)
shot("task3d_антиспам_captcha_на_форме.png", BASE + "/contact", wait=2.0,
     scroll_elem=".captcha")

# --- Реальное письмо в Mailpit: доказательство, что форма доставляет ---------
c.goto(BASE + "/contact", wait=1.5)
captcha_label = c.js("(function(){var l=document.querySelector('label[for=edit-captcha-response]'); return l?l.innerText.trim():'';})()") or ""
match = re.search(r'\((\d+)\s*\+\s*(\d+)', captcha_label)
if match:
    answer = int(match.group(1)) + int(match.group(2))
    c.js(
        "(function(){"
        "var set=function(id,v){var e=document.querySelector(id); if(e){e.value=v;}};"
        "set('#edit-name','Тестовый Читатель');"
        "set('#edit-mail','reader@example.com');"
        "set('#edit-subject-0-value','Проверка доставки через Mailpit');"
        "set('#edit-message-0-value','Сообщение отправлено контактной формой лабораторной работы.');"
        "set('#edit-captcha-response','%d');"
        "})()" % answer
    )
    time.sleep(6)
    c.js("document.querySelector('#edit-submit').click()")
    time.sleep(3)
c.goto("http://localhost:8026", wait=2.0)
shot("task2c_письмо_в_mailpit.png")

# --- Статистика: реальные просмотры материалов ------------------------------
for alias in [
    news_alias,
    "/noutbuki/lenovo-thinkpad-t490",
    "/noutbuki/lenovo-ideapad-flex-14",
    "/noutbuki/lenovo-yoga-3-pro",
]:
    try:
        record_views(nid_by_alias(LAB, alias), times=3)
    except RuntimeError as exc:
        print("пропуск просмотров:", exc)

# --- Администратор -----------------------------------------------------------
c.goto(one_time_login(LAB, BASE), wait=3.0)

shot("task1a_пользователи_список_редактор.png", BASE + "/admin/people")
shot("task1b_роль_редактор_права.png", BASE + "/admin/people/permissions/redaktor",
     scroll_js="(function(){var b=document.querySelector('input[type=checkbox]:checked');"
               "if(b){b.closest('tr').scrollIntoView({block:'center'});}})()")

shot("task2b_контактная_форма_настройки.png", BASE + "/admin/structure/contact/manage/feedback")

shot("task3a_карта_сайта_xml.png", BASE + "/sitemap.xml", full=True)
shot("task3b_карта_сайта_настройки.png", BASE + "/admin/config/search/simplesitemap/entities")
shot("task3e_антиспам_captcha_настройки.png", BASE + "/admin/config/people/captcha/captcha-points")

# Микроразметка: исходный код страницы материала с JSON-LD.
c.goto("view-source:" + BASE + news_alias, wait=2.0)
c.js(
    "(function(){var lines=(document.body.innerText||'').split('\\n');"
    "var i=lines.findIndex(function(l){return l.indexOf('application/ld+json')>=0;});"
    "var d=document.documentElement;"
    "var y=i<0?0:Math.max(0,(i/Math.max(1,lines.length))*d.scrollHeight-120);"
    "window.scrollTo(0,y); return i;})()"
)
time.sleep(0.6)
shot("task3c_микроразметка_jsonld.png")

# Резервное копирование: создаём копию через штатную форму, затем список.
c.goto(BASE + "/admin/config/development/backup_migrate", wait=2.0)
c.js(
    "(function(){var s=document.querySelector('#edit-source-id');"
    "var d=document.querySelector('#edit-destination-id');"
    "if(s){s.value='default_db';} if(d){d.value='private_files';} return 1;})()"
)
c.trusted_click("#edit-submit", wait=15)
shot("task4b_резервная_копия_создана.png", BASE + "/admin/config/development/backup_migrate/backups", wait=2.0)
shot("task4a_резервное_копирование_настройки.png", BASE + "/admin/config/development/backup_migrate/schedule/edit/daily_schedule", wait=2.5)
shot("task4c_расписание_копирования.png", BASE + "/admin/config/development/backup_migrate/schedule")

shot("task4d_статистика_посещаемости.png", BASE + "/admin/reports/poseshchaemost", wait=2.5)
shot("task5a_кэширование_настройки.png", BASE + "/admin/config/development/performance")

c.close()
print("\nвсего кадров:", len(shots))
bad = [s for s in shots if s[1] < 30000]
print("подозрительно мелкие:", bad if bad else "нет")
