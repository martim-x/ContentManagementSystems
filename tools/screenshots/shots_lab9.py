"""Скриншоты ЛР9 (стенд 8106) по пунктам методички labs/Drupal/Drupal_2.pdf.

Запуск (macOS, из корня репозитория):
    python3 tools/screenshots/shots_lab9.py
Нужны: поднятый стенд lab9 (setup-lab9.sh), Chrome в /Applications и
`pip install websocket-client` в активном окружении.
"""
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from cdp import Chrome  # noqa: E402
from common import BASE_LAB, nid_by_alias, one_time_login  # noqa: E402

LAB = "lab9"
OUT = os.path.join(BASE_LAB, LAB, "report")
BASE = "http://localhost:8106"
os.makedirs(OUT, exist_ok=True)

c = Chrome("/tmp/chrome-lab9-cdp", width=1440, height=1100, scale=2)
shots = []


def shot(name, url=None, wait=2.0, full=False, scroll=None, click=None, click_js=None, hover=None):
    if url:
        c.goto(url, wait=wait)
    if hover:
        c.hover(hover)
    if click_js:
        c.js(click_js)
        time.sleep(1.0)
    if click:
        c.click(click, wait=1.5)
    if scroll is not None:
        c.scroll_to(scroll)
    path = os.path.join(OUT, name)
    c.screenshot(path, full_page=full)
    size = os.path.getsize(path)
    title = c.js("document.title") or ""
    shots.append((name, size, title))
    print(f"{name}\t{size}б\t{title}")
    return path


article = nid_by_alias(LAB, "/novosti/obzor-noutbukov-lenovo-2026-goda")

# --- Гость: страницы, галерея, меню, футер ------------------------------------
shot("task1b_два_блока_в_боковой_колонке_на_странице.png", BASE + "/")
shot("task2b_статья_страница.png", BASE + "/novosti/obzor-noutbukov-lenovo-2026-goda")
shot("task3a_история_фирмы_текст_картинки_ссылки.png", BASE + "/stranicy/istoriya-firmy-lenovo", full=True)
shot("task3b_галерея_миниатюры_colorbox.png", BASE + "/stranicy/galereya-noutbukov-lenovo")
# Лайтбокс: клик по миниатюре открывает Colorbox поверх страницы.
shot("task3c_галерея_лайтбокс_colorbox_работает.png", BASE + "/stranicy/galereya-noutbukov-lenovo",
     click="a.colorbox", wait=2.5)
shot("task3d_прайс_лист_таблица.png", BASE + "/stranicy/prays-list-noutbukov-lenovo", full=True)
# Пункт «Каталог» — пустая ссылка, раскрывается на термины словаря (Bootstrap dropdown).
shot("task4d_меню_на_сайте_пункт_каталог_nolink.png", BASE + "/", wait=2.5,
     click_js="document.querySelector('.nav-item.dropdown .dropdown-toggle').click()")
shot("task4c_дополнительное_меню_в_футере.png", BASE + "/", wait=2.0, scroll=100000)

# --- Администратор ------------------------------------------------------------
c.goto(one_time_login(LAB, BASE), wait=3.0)
print("вход выполнен:", c.js("document.body.innerText.includes('admin')"))

shot("task1a_регион_два_блока_настройка_блоков.png", BASE + "/admin/structure/block", wait=2.5)
shot("task2a_статья_настройки_материала.png", BASE + f"/node/{article}/edit", wait=3.0, full=True)
shot("task4a_меню_структура_каталог_подпункты.png", BASE + "/admin/structure/menu/manage/main", wait=2.5)
shot("task4b_дополнительное_меню_настройка.png", BASE + "/admin/structure/menu/manage/footer", wait=2.5)

c.close()
print("\nвсего кадров:", len(shots))
bad = [s for s in shots if s[1] < 30000]
print("подозрительно мелкие:", bad if bad else "нет")
