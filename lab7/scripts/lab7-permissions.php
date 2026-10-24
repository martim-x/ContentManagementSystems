<?php
/**
 * Права групп пользователей в «Общих настройках» Joomla (Global Configuration).
 *
 * По заданию ЛР7 (J3(в помощь).pdf):
 * - Декан: создавать статьи, пункты меню; назначать видимость.
 * - Староста: создавать материалы (статьи) для студентов.
 * - Студент: видеть контент, но не создавать.
 *
 * Реализуется через таблицу `jos_assets` (корневой актив root id обычно 1)
 * с JSON `rules`. Для каждой группы проставляем разрешения на компоненты:
 * com_content (создание/редактирование/просмотр), com_menus (создание пунктов),
 * com_users / com_config (назначение уровней доступа) — через `core.create`,
 * `core.edit`, `core.edit.state`, `core.edit.own`, `core.create` для меню,
 * `core.edit` для статей, и `core.admin` (или `core.manage`) для доступа
 * к настройкам.
 */
define('DB_HOST', getenv('JOOMLA_DB_HOST') ?: 'db');
define('DB_NAME', getenv('JOOMLA_DB_NAME') ?: 'joomla');
define('DB_USER', getenv('JOOMLA_DB_USER') ?: 'joomla');
define('DB_PASS', getenv('JOOMLA_DB_PASSWORD') ?: 'joomla');

function db_query($sql, $params = array()) {
    static $pdo = null;
    if (null === $pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

echo "== ЛР7: права групп ==\n";

function gid($title) {
    $r = db_query("SELECT id FROM `jos_usergroups` WHERE title = :t", array(':t' => $title));
    return $r ? $r[0]['id'] : null;
}

$grp_dekan = gid('Деканат');
$grp_starosta = gid('Старосты');
$grp_student = gid('Студенты');
if (!$grp_dekan || !$grp_starosta || !$grp_student) {
    echo "ОШИБКА: группы не найдены\n"; exit(1);
}

// Для простоты: в Joomla ACL правила хранятся в корневом активе (asset id = 1)
// в виде JSON. Обновляем только необходимые разрешения для компонентов.
// Формат JSON ключей: `{"core.create":{"group_id":1,"group_id":0}, ...}`

// Получим текущие правила корня.
$r = db_query("SELECT rules FROM `jos_assets` WHERE id = 1");
$rules = $r ? json_decode($r[0]['rules'], true) : array();
if (!is_array($rules)) $rules = array();

// Функция установки разрешения (1 = разрешено, 0 = запрещено) для группы по компоненту/действию.
function set_perm(&$rules, $component, $action, $group_ids, $allowed) {
    $key = $component . '.' . $action;
    if (!isset($rules[$key])) $rules[$key] = array();
    foreach ($group_ids as $gid) {
        $rules[$key][$gid] = $allowed ? 1 : 0;
    }
}

// Разрешения для декана:
// com_content — всё (создание, редактирование, изменение статуса)
// com_menus — создание/редактирование пунктов
// com_users — управление пользователями (для назначения доступа требуется управление группами/уровнями)
// com_config — общие настройки (назначение прав доступа на уровне системы)
set_perm($rules, 'core', 'login.site', array($grp_dekan), 1);
set_perm($rules, 'core', 'login.admin', array($grp_dekan), 1);
set_perm($rules, 'core', 'manage', array($grp_dekan), 1);
set_perm($rules, 'core', 'create', array($grp_dekan), 1);
set_perm($rules, 'core', 'delete', array($grp_dekan), 1);
set_perm($rules, 'core', 'edit', array($grp_dekan), 1);
set_perm($rules, 'core', 'edit.state', array($grp_dekan), 1);
set_perm($rules, 'com_content', 'create', array($grp_dekan), 1);
set_perm($rules, 'com_content', 'edit', array($grp_dekan), 1);
set_perm($rules, 'com_content', 'edit.own', array($grp_dekan), 1);
set_perm($rules, 'com_content', 'edit.state', array($grp_dekan), 1);
set_perm($rules, 'com_menus', 'create', array($grp_dekan), 1);
set_perm($rules, 'com_menus', 'edit', array($grp_dekan), 1);
set_perm($rules, 'com_menus', 'edit.state', array($grp_dekan), 1);
set_perm($rules, 'com_users', 'create', array($grp_dekan), 1);
set_perm($rules, 'com_users', 'edit', array($grp_dekan), 1);
set_perm($rules, 'com_users', 'delete', array($grp_dekan), 1);
set_perm($rules, 'com_config', 'manage', array($grp_dekan), 1);

// Староста: создание статей (com_content), редактирование своих (edit.own),
// изменение статуса своих статей (edit.state для своих), но не удаление чужих.
set_perm($rules, 'core', 'login.site', array($grp_starosta), 1);
set_perm($rules, 'core', 'create', array($grp_starosta), 1);
set_perm($rules, 'core', 'edit', array($grp_starosta), 1);
set_perm($rules, 'core', 'edit.own', array($grp_starosta), 1);
set_perm($rules, 'core', 'edit.state', array($grp_starosta), 1);
set_perm($rules, 'com_content', 'create', array($grp_starosta), 1);
set_perm($rules, 'com_content', 'edit', array($grp_starosta), 1);
set_perm($rules, 'com_content', 'edit.own', array($grp_starosta), 1);
set_perm($rules, 'com_content', 'edit.state', array($grp_starosta), 1);

// Студент: только просмотр (create=0, edit=0), но доступ к сайту.
set_perm($rules, 'core', 'login.site', array($grp_student), 1);
set_perm($rules, 'core', 'create', array($grp_student), 0);
set_perm($rules, 'core', 'edit', array($grp_student), 0);
set_perm($rules, 'core', 'edit.own', array($grp_student), 0);
set_perm($rules, 'core', 'edit.state', array($grp_student), 0);
set_perm($rules, 'com_content', 'create', array($grp_student), 0);
set_perm($rules, 'com_content', 'edit', array($grp_student), 0);
set_perm($rules, 'com_content', 'edit.own', array($grp_student), 0);
set_perm($rules, 'com_content', 'edit.state', array($grp_student), 0);

// Обновляем актив корня с новыми правилами.
db_query("UPDATE `jos_assets` SET rules = :r WHERE id = 1",
    array(':r' => json_encode($rules, JSON_UNESCAPED_UNICODE)));
echo "права групп в Общих настройках (корневой актив) обновлены\n";

// Обновляем активы компонентов: в Joomla права наследуются от родителя,
// но если у компонента уже заданы свои правила (com_content, com_menus, ...),
// они переопределяют корневые. Поэтому явно добавляем группы в активы компонентов.
function merge_asset_rules($asset_name, $actions_groups) {
    $row = db_query("SELECT id, rules FROM `jos_assets` WHERE name = :n", array(':n' => $asset_name));
    if (!$row) { echo "  актив {$asset_name}: не найден, пропускаем\n"; return; }
    $id = $row[0]['id'];
    $r = json_decode($row[0]['rules'], true);
    if (!is_array($r)) $r = array();
    foreach ($actions_groups as $action => $groups) {
        if (!isset($r[$action]) || !is_array($r[$action])) $r[$action] = array();
        foreach ($groups as $gid => $allowed) {
            $r[$action][$gid] = $allowed ? 1 : 0;
        }
    }
    db_query("UPDATE `jos_assets` SET rules = :r WHERE id = :id",
        array(':r' => json_encode($r, JSON_UNESCAPED_UNICODE), ':id' => $id));
    echo "  актив {$asset_name}: обновлён\n";
}

// com_content: декан и старосты могут создавать/редактировать статьи.
merge_asset_rules('com_content', array(
    'core.create'     => array($grp_dekan => 1, $grp_starosta => 1, $grp_student => 0),
    'core.edit'       => array($grp_dekan => 1, $grp_starosta => 1, $grp_student => 0),
    'core.edit.own'   => array($grp_dekan => 1, $grp_starosta => 1, $grp_student => 0),
    'core.edit.state' => array($grp_dekan => 1, $grp_starosta => 1, $grp_student => 0),
    'core.delete'     => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
));

// com_menus: только декан создаёт/редактирует пункты меню.
merge_asset_rules('com_menus', array(
    'core.create'     => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
    'core.edit'       => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
    'core.edit.state' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
));

// com_users: декан управляет пользователями (назначение видимости/групп).
merge_asset_rules('com_users', array(
    'core.manage' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
    'core.create' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
    'core.edit'   => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
));

// com_config: декан управляет общими настройками.
merge_asset_rules('com_config', array(
    'core.manage' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0),
));

// Также нужно, чтобы «назначать тех, кто видит» (изменение уровня доступа статьи/меню)
// было доступно декану. Для этого достаточно права `core.edit.state` на com_content и com_menus,
// плюс `core.manage` на com_users (уровни доступа — это часть управления пользователями в Joomla).
// Уже установлено выше.

echo "== Права настроены ==\n";
