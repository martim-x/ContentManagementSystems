# Сценарии скриншотов для отчётов

Скрипты снимают экраны стендов ЛР8/ЛР9 по пунктам методичек и кладут их в `labN/report/`.
Имена файлов соответствуют пунктам задания (`task5a_…`, `task3c_…`), поэтому скриншот
всегда можно сопоставить с требованием.

## Что нужно

- поднятые стенды: `sh lab8/setup-lab8.sh`, `sh lab9/setup-lab9.sh`;
- Google Chrome (`/Applications/Google Chrome.app`);
- Python 3 и `pip install websocket-client`;
- (для админских экранов) доступ к `drush uli` — скрипт сам получает одноразовую ссылку
  и входит администратором.

## Запуск

```sh
python3 tools/screenshots/shots_lab8.py    # 17 кадров ЛР8
python3 tools/screenshots/shots_lab9.py    # 12 кадров ЛР9
```

## Как это работает

- `cdp.py` — минимальный драйвер Chrome по DevTools Protocol: навигация, клик, наведение,
  раскрытие `<details>`, полноэкранный скриншот. Chrome запускается headless с
  `--remote-debugging-port`, профиль в `/tmp/chrome-labN-cdp`.
- `common.py` — `one_time_login()` (через `drush uli`) и `nid_by_alias()`: node id ищется
  по алиасу, поэтому сценарии не зависят от автоинкремента материалов.
- Размер кадра: окно 1440×1100 при `deviceScaleFactor = 2` → PNG 2880×2200.

Скрипты идемпотентны: каждый прогон перезаписывает файлы в `report/`.
