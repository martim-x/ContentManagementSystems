"""Общие помощники для сценариев скриншотов: логин и поиск node id по алиасу."""
import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

BASE_LAB = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))


def drush(lab, *args):
    """Выполнить drush внутри контейнера web соответствующей лабы."""
    r = subprocess.run(
        ["docker", "compose", "exec", "-T", "web", "vendor/bin/drush", *args],
        cwd=os.path.join(BASE_LAB, lab),
        capture_output=True,
        text=True,
    )
    if r.returncode != 0:
        raise RuntimeError("drush " + " ".join(args) + " упал: " + r.stdout + r.stderr)
    return r.stdout


def one_time_login(lab, base_url):
    """Одноразовая ссылка входа администратором (drush uli)."""
    for line in drush(lab, "uli", "--uri=" + base_url).splitlines():
        if "/user/reset/" in line:
            return line.strip()
    raise RuntimeError("не получил одноразовую ссылку drush uli")


def nid_by_alias(lab, alias):
    """node id по внутреннему алиасу: node id проставляется кодом, а алиас стабилен."""
    code = (
        '$u = \\Drupal::service("path.validator")->getUrlIfValid(%s);'
        'echo $u ? ($u->getRouteParameters()["node"] ?? "") : "";' % json.dumps(alias)
    )
    out = drush(lab, "php:eval", code).strip()
    if not out.isdigit():
        raise RuntimeError("для алиаса %s не найден node id (ответ: %r)" % (alias, out))
    return int(out)
