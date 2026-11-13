"""Скриншоты ЛР11 (стенд 8110) по пунктам методички labs/Drupal/Drupal_4.pdf.

Сайт — городской новостной портал «Город24». Кадры снимаются headless Chrome
через CDP; часть кадров требует входа администратором (drush uli).

Запуск (macOS, из корня репозитория):
    tools/screenshots/.venv/bin/python tools/screenshots/shots_lab11.py
"""
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from cdp import Chrome  # noqa: E402
from common import BASE_LAB, nid_by_alias, one_time_login  # noqa: E402

LAB = "lab11"
OUT = os.path.join(BASE_LAB, LAB, "report")
BASE = "http://localhost:8110"
ADMINER = "http://localhost:8111"
os.makedirs(OUT, exist_ok=True)

c = Chrome("/tmp/chrome-lab11-cdp", width=1440, height=1100, scale=2)
# Профиль Chrome переживает запуски: без сброса cookies «гостевые» кадры
# снимаются уже залогиненным администратором.
c.send("Network.clearBrowserCookies")
shots = []


def shot(name, url=None, wait=2.0, full=False, scroll=None, scroll_elem=None,
         scroll_text=None, click=None, trusted_click=None):
    if url:
        c.goto(url, wait=wait)
    if click:
        c.click(click)
    if trusted_click:
        c.trusted_click(trusted_click, wait=1.2)
    if scroll_elem:
        c.js("(function(){var e=document.querySelector(%r); if(e){e.scrollIntoView({block:'start'});}})()" % scroll_elem)
        time.sleep(0.4)
    if scroll_text:
        c.js(
            "(function(){var t=%r; var all=Array.from(document.querySelectorAll('h2,h3,h4,legend,summary,a,td,th,label'));"
            "var e=all.find(function(x){return x.innerText.trim().indexOf(t)>=0;});"
            "if(e){e.scrollIntoView({block:'center'});} return !!e;})()" % scroll_text
        )
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


# --- Гость: слоган и страница статей ---------------------------------------
shot("task2a_слоган_блок_на_сайте.png", BASE + "/", wait=2.5)
shot("task5c_страница_статей_таблица.png", BASE + "/articles", wait=2.5)

# --- Администратор ---------------------------------------------------------
c.goto(one_time_login(LAB, BASE), wait=3.0)

shot("task1a_реестр_моделей_страница.png", BASE + "/admin/reports/gorod24-registry", wait=2.0)
shot("task2b_слоган_форма_изменения.png", BASE + "/admin/config/system/slogan", wait=2.0)
shot("task3a_ссылка_news_в_admin_config.png", BASE + "/admin/config", wait=2.5,
     scroll_text="News")
shot("task3b_страница_news.png", BASE + "/admin/config/services/news", wait=2.0)
shot("task3c_блок_this_is_a_news_block_на_главной.png", BASE + "/", wait=2.5)
shot("task3d_видимость_блока_страницы_front.png",
     BASE + "/admin/structure/block/manage/news_news_block", wait=2.5,
     trusted_click='a[href="#edit-visibility-request-path"]',
     scroll_elem="#edit-visibility-request-path-pages")
shot("task3e_видимость_блока_роль_authenticated.png",
     BASE + "/admin/structure/block/manage/news_news_block", wait=2.5,
     trusted_click='a[href="#edit-visibility-user-role"]',
     scroll_elem="#edit-visibility-user-role-roles-authenticated")
shot("task4a_поле_цены_в_типе_ноутбук.png",
     BASE + "/admin/structure/types/manage/noutbuk/fields", wait=2.5,
     scroll_text="Цена, руб.")
shot("task4b_вычисляемое_поле_в_отображении.png",
     BASE + "/admin/structure/types/manage/noutbuk/display/default", wait=2.5,
     scroll_text="Цена со скидкой")
shot("task4c_карточка_ноутбука_с_вычисленной_ценой.png",
     BASE + "/noutbuki/lenovo-thinkpad-t490", wait=2.0)
shot("task5a_view_таблица_настройки.png",
     BASE + "/admin/structure/views/view/articles_table", wait=2.5)
shot("task5b_view_фильтр_по_дате.png",
     BASE + "/admin/structure/views/view/articles_table", wait=2.5,
     scroll_text="Authored on (>= -30 days)")

# --- Adminer: таблица, созданная модулем ------------------------------------
c.goto(ADMINER + "/", wait=2.0)
c.js("""(() => {
  const set = (sel, val) => { const e = document.querySelector(sel); if (e) { e.value = val; } };
  set('select[name="auth[driver]"]', 'pgsql');
  set('input[name="auth[username]"]', 'drupal');
  set('input[name="auth[password]"]', 'drupal');
  set('input[name="auth[db]"]', 'drupal');
  const submit = Array.from(document.querySelectorAll('input[type=submit]')).find(x => x.value === 'Войти');
  if (submit) submit.click();
  return 1;
})()""")
time.sleep(4)
print("adminer:", c.js("document.title"))
c.js("""(() => {
  const a = Array.from(document.querySelectorAll('a')).find(x => x.textContent.trim() === 'gorod24_registry');
  if (a) a.click();
  return !!a;
})()""")
time.sleep(2.5)
c.js("""(() => {
  const a = Array.from(document.querySelectorAll('a')).find(x => x.textContent.trim() === 'Выбрать');
  if (a) a.click();
  return !!a;
})()""")
time.sleep(2.5)
shot("task1b_реестр_таблица_в_adminer.png")

c.close()
print("\nвсего кадров:", len(shots))
bad = [s for s in shots if s[1] < 30000]
print("подозрительно мелкие:", bad if bad else "нет")
