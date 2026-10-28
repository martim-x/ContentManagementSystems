"""Скриншоты ЛР8 (стенд 8104) по пунктам методички labs/Drupal/Drupal_1.pdf.

Запуск (macOS, из корня репозитория):
    python3 tools/screenshots/shots_lab8.py
Нужны: поднятый стенд lab8 (setup-lab8.sh), Chrome в /Applications и
`pip install websocket-client` в активном окружении.
"""
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from cdp import Chrome  # noqa: E402
from common import BASE_LAB, nid_by_alias, one_time_login  # noqa: E402

LAB = "lab8"
OUT = os.path.join(BASE_LAB, LAB, "report")
BASE = "http://localhost:8104"
os.makedirs(OUT, exist_ok=True)

c = Chrome("/tmp/chrome-lab8-cdp", width=1440, height=1100, scale=2)
shots = []


def shot(name, url=None, wait=2.0, full=False, scroll=None, scroll_elem=None, click=None, hover=None,
         open_details=None):
    if url:
        c.goto(url, wait=wait)
    if hover:
        c.hover(hover)
    if open_details:
        c.js(
            "(function(ids){ids.forEach(function(id){var e=document.getElementById(id);"
            "if(e){e.open=true;}});return 1;})(%s)" % str(open_details).replace("'", '"')
        )
    if click:
        c.click(click)
    if scroll_elem:
        c.js("document.getElementById('%s').scrollIntoView({block:'start'})" % scroll_elem)
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


x1_carbon = nid_by_alias(LAB, "/noutbuki/lenovo-thinkpad-x1-carbon")

# --- Гость: каталог, карточка, веб-форма -------------------------------------
shot("task5a_каталог_10_ноутбуков_главная.png", BASE + "/", wait=2.5, full=True)
shot("task5b_карточка_ноутбука_фото_серия_модель.png", BASE + "/noutbuki/lenovo-thinkpad-t490")
shot("task3e_вебформа_обратная_связь.png", BASE + "/form/obratnaya-svyaz")

# --- Администратор -----------------------------------------------------------
c.goto(one_time_login(LAB, BASE), wait=3.0)
print("вход выполнен:", c.js("document.body.innerText.includes('admin')"))

# Задание 1 — установка Drupal.
shot("task1a_установка_версия_и_состояние_сайта.png", BASE + "/admin/reports/status")

# Задание 2 — нестандартная тема, логотип, название, фавикон, слоган.
shot("task2a_тема_установлена_и_по_умолчанию.png", BASE + "/admin/appearance")
shot("task2b_настройки_темы_логотип_фавикон_слоган.png", BASE + "/admin/appearance/settings/bootstrap5")

# Задание 3 — минимум 5 модулей и показ их работы.
shot("task3a_модули_включенные.png", BASE + "/admin/modules")
shot(
    "task3b_admin_toolbar_раскрывающееся_меню.png",
    BASE + "/admin/content",
    hover=".toolbar-icon-admin-toolbar-tools-help, .toolbar-menu__item--level-1, #toolbar-item-administration",
)
shot("task3c_форма_редактирования_вебформы.png", BASE + "/admin/structure/webform/manage/obratnaya_svyaz")

# Задание 4 — Pathauto (+ Chaos tool suite, Token), Metatag + Real-time SEO.
shot("task4a_паттерны_алиасов_pathauto.png", BASE + "/admin/config/search/path/patterns")
shot("task4b_список_алиасов.png", BASE + "/admin/config/search/path")
shot("task4c_метатеги_по_умолчанию.png", BASE + "/admin/config/search/metatag")
# Блок «Метатеги» на форме материала раскрываем, иначе видны только заголовки групп.
shot(
    "task4d_метатеги_и_seo_в_форме_материала.png",
    BASE + f"/node/{x1_carbon}/edit",
    wait=3.5,
    open_details=["edit-field-metatag-0", "edit-field-metatag-0-basic"],
    scroll_elem="edit-field-metatag-0",
)
shot("task4e_seo_анализ_yoast.png", BASE + f"/node/{x1_carbon}/edit", wait=3.5,
     open_details=["edit-field-yoast-seo-0"], scroll_elem="edit-field-yoast-seo-0")

# Задание 5 — каталог, тип материала с полем термина, словарь.
shot("task5c_поля_типа_материала_ноутбук.png", BASE + "/admin/structure/types/manage/noutbuk/fields")
shot("task5d_словарь_lenovo_термины.png", BASE + "/admin/structure/taxonomy/manage/lenovo/overview")
shot("task5e_форма_создания_ноутбука_обязательные_поля.png", BASE + "/node/add/noutbuk", wait=2.5)

c.close()
print("\nвсего кадров:", len(shots))
bad = [s for s in shots if s[1] < 30000]
print("подозрительно мелкие:", bad if bad else "нет")
