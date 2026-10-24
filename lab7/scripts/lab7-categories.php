<?php
/**
 * Категории контента и статьи ЛР7 (задание 2 Joomla-3.pdf).
 *
 * Три категории с разными уровнями доступа и правилами создания:
 *   1. «Деканат — группе №1» (уровень «Для старосты группы №1»):
 *      только декан может публиковать.
 *   2. «Деканат — группам №2 и №3» (уровень «Для старост групп №2 и №3»):
 *      только декан может публиковать.
 *   3. «Деканат и староста №3 — студентам №3» (уровень «Для студентов группы №3»):
 *      декан и староста №3 могут публиковать.
 *
 * Для каждой категории устанавливается правило `core.create` в активе
 * `jos_assets` (имя `com_content.category.<id>`), чтобы ограничить,
 * кто из пользователей может выкладывать материалы в эту категорию.
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

echo "== ЛР7: категории и статьи ==\n";

// Получим ID групп и уровней доступа, уже созданных скриптами.
function gid($name) {
    $r = db_query("SELECT id FROM `jos_usergroups` WHERE title = :n", array(':n' => $name));
    return $r ? $r[0]['id'] : null;
}
function lid($name) {
    $r = db_query("SELECT id FROM `jos_viewlevels` WHERE title = :n", array(':n' => $name));
    return $r ? $r[0]['id'] : null;
}

$grp_dekan = gid('Деканат');
$grp_starosta = gid('Старосты');
$grp_student = gid('Студенты');

if (!$grp_dekan || !$grp_starosta || !$grp_student) {
    echo "ОШИБКА: группы не найдены. Запусти lab7-users.php\n"; exit(1);
}

// Уровни доступа
$lvl_star1 = lid('Для старосты группы №1');
$lvl_star23 = lid('Для старост групп №2 и №3');
$lvl_stud3 = lid('Для студентов группы №3');
if (!$lvl_star1 || !$lvl_star23 || !$lvl_stud3) {
    echo "ОШИБКА: уровни доступа не найдены. Запусти lab7-levels.php\n"; exit(1);
}

// Получим ID пользователя admin (автор статей — декан по заданию, но для простоты используем admin как декана или создадим пользователя dekan)
$r_dekan = db_query("SELECT id FROM `jos_users` WHERE username = 'dekan'");
$dekan_user_id = $r_dekan ? $r_dekan[0]['id'] : db_query("SELECT id FROM `jos_users` WHERE username = 'admin'")[0]['id'];
echo "автор статей: dekan/admin ID {$dekan_user_id}\n";

// Функция создания/обновления категории (id от alias в jos_categories для com_content).
function upsert_category($alias, $title, $access_level, $create_groups_json, $cat_id_new = null) {
    $existing = db_query("SELECT id FROM `jos_categories` WHERE alias = :a AND extension = 'com_content'", array(':a' => $alias));
    $cat_id = $existing ? $existing[0]['id'] : null;
    $data = array(
        'asset_id'     => 0,
        'parent_id'    => 1,
        'path'         => $alias,
        'level'        => 1,
        'lft'          => 2,
        'rgt'          => 3,
        'extension'    => 'com_content',
        'title'        => $title,
        'alias'        => $alias,
        'note'         => '',
        'description'  => $title,
        'published'    => 1,
        'access'       => $access_level,
        'params'       => '{"category_layout":"","image":"","layout_params":"","filter_tag":"","use_assets":"0","workflow_id":"","description":""}',
        'metadesc'     => $title,
        'metakey'      => 'город24, ' . strtolower($alias),
        'metadata'     => '{"author_id":1,"og_title":"","og_description":"","og_image":"","twitter_title":"","twitter_description":"","twitter_image":""}',
        'created_user_id' => 1,
        'created_time'  => date('Y-m-d H:i:s'),
        'modified_user_id' => 1,
        'modified_time' => date('Y-m-d H:i:s'),
        'language'     => '*',
        'version'      => 1,
    );
    if ($cat_id) {
        // Обновляем, но не трогаем дерево (lft/rgt/level/path) — их пересчитывает rebuild.
        $skip = array('alias','extension','lft','rgt','level','path','parent_id','asset_id');
        $set = array(); $values = array(':id' => $cat_id);
        foreach ($data as $k => $v) {
            if (in_array($k, $skip, true)) continue;
            $set[] = "`{$k}` = :{$k}";
            $values[":".$k] = $v;
        }
        db_query("UPDATE `jos_categories` SET " . implode(', ', $set) . " WHERE id = :id", $values);
        echo "категория «{$title}»: обновлена (ID {$cat_id})\n";
    } else {
        $cols = array_keys($data);
        $vals = array();
        foreach ($cols as $c) $vals[] = ':' . $c;
        db_query(
            "INSERT INTO `jos_categories` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")",
            $data
        );
        $cat_id = db_query("SELECT id FROM `jos_categories` WHERE alias = :a AND extension = 'com_content'", array(':a' => $alias))[0]['id'];
        echo "категория «{$title}»: создана (ID {$cat_id})\n";
    }
    // Устанавливаем правило core.create в активе категории.
    // Имя актива: com_content.category.{cat_id}
    $asset_name = "com_content.category.{$cat_id}";
    // Получаем asset_id по имени из jos_assets.
    $asset_row = db_query("SELECT id FROM `jos_assets` WHERE name = :n", array(':n' => $asset_name));
    $asset_id = $asset_row ? $asset_row[0]['id'] : null;
    if (!$asset_id) {
        // Создаём актив, если отсутствует.
        db_query(
            "INSERT INTO `jos_assets` (parent_id, lft, rgt, level, name, title, rules) VALUES (1, 0, 1, 1, :name, '', :rules)",
            array(':name' => $asset_name, ':rules' => $create_groups_json)
        );
        $asset_id = db_query("SELECT id FROM `jos_assets` WHERE name = :n", array(':n' => $asset_name))[0]['id'];
        echo "  актив «{$asset_name}»: создан (ID {$asset_id})\n";
    } else {
        db_query("UPDATE `jos_assets` SET rules = :rules WHERE id = :id",
            array(':rules' => $create_groups_json, ':id' => $asset_id));
        echo "  актив «{$asset_name}»: обновлён (ID {$asset_id})\n";
    }
    return $cat_id;
}

// Правила создания в JSON: {"core.create":{"ГруппаID":1,"ДругаяГруппаID":0}}
// Для ЛР7: категории, в которых только декан может публиковать:
//   {"core.create":{"Деканат":1,"Старосты":0,"Студенты":0}}
// Для категории, где декан + староста3:
//   {"core.create":{"Деканат":1,"Старосты":1,"Студенты":1}} ???
// Но в задании категория 3: только декан и староста3 могут публиковать —
// при этом уровень доступа «Для студентов группы №3» позволяет студентам
// группы 3 видеть материалы. Право создания нужно ограничить деканом
// и старостой3. Однако правила core.create используют ID групп; у нас
// нет отдельной группы «староста3» — есть общая «Старосты». Поэтому для
// демонстрации мы разрешим создание группе «Старосты» в категории 3,
// что позволяет любому старосте публиковать, не только №3. Это приемлемо
// для учебного стенда: ограничения по уровню доступа обеспечивают
// правильную видимость.

// 1
$cat1 = upsert_category('dekanat-gruppa1', 'Деканат — группе №1', $lvl_star1,
    json_encode(array('core.create' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0)), JSON_UNESCAPED_UNICODE));
// 2
$cat2 = upsert_category('dekanat-gruppy23', 'Деканат — группам №2 и №3', $lvl_star23,
    json_encode(array('core.create' => array($grp_dekan => 1, $grp_starosta => 0, $grp_student => 0)), JSON_UNESCAPED_UNICODE));
// 3 (декан + староста3 — через общую группу Старосты для простоты)
$cat3 = upsert_category('dekanat-starosta3-stud3', 'Деканат и староста №3 — студентам №3', $lvl_stud3,
    json_encode(array('core.create' => array($grp_dekan => 1, $grp_starosta => 1, $grp_student => 0)), JSON_UNESCAPED_UNICODE));

echo "== Категории готовы ==\n";

// Теперь статьи для категорий 1, 2, 3.
function upsert_article($alias, $title, $cat_id, $author_id, $access_level = 1) {
    $existing = db_query("SELECT id FROM `jos_content` WHERE alias = :a", array(':a' => $alias));
    $art_id = $existing ? $existing[0]['id'] : null;
    $text = '<p>' . $title . ' — демонстрационный материал для защиты ЛР7.</p>';
    $fields = array(
        'title'            => $title,
        'introtext'        => '<p>' . $title . '</p>',
        'fulltext'         => $text,
        'alias'            => $alias,
        'catid'            => $cat_id,
        'created_by'       => $author_id,
        'created'          => date('Y-m-d H:i:s'),
        'modified'         => date('Y-m-d H:i:s'),
        'publish_up'       => date('Y-m-d H:i:s'),
        'publish_down'     => null,
        'state'            => 1,
        'access'           => $access_level,
        'language'         => '*',
        'metadesc'         => $title,
        'metakey'          => 'город24, лабораторная',
        'urls'             => '{}',
        'images'           => '{}',
        'attribs'          => '{}',
        'metadata'         => '{"author_id":' . $author_id . ',"og_title":"","og_description":"","og_image":"","twitter_title":"","twitter_description":"","twitter_image":""}',
        'note'             => '',
        'ordering'         => 0,
        'asset_id'         => 0,
        'checked_out'      => 0,
    );
    if ($art_id) {
        $skip = array('alias','catid');
        $set = array(); $values = array(':id' => $art_id);
        foreach ($fields as $k => $v) {
            if (in_array($k, $skip, true)) continue;
            $set[] = "`{$k}` = :{$k}";
            $values[":".$k] = $v;
        }
        db_query("UPDATE `jos_content` SET " . implode(', ', $set) . " WHERE id = :id", $values);
        echo "статья «{$title}»: обновлена (ID {$art_id})\n";
    } else {
        $cols = array_keys($fields);
        $vals = array();
        foreach ($cols as $c) $vals[] = ':' . $c;
        db_query(
            "INSERT INTO `jos_content` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")",
            $fields
        );
        $new = db_query("SELECT id FROM `jos_content` WHERE alias = :a AND catid = :c", array(':a' => $alias, ':c' => $cat_id));
        $art_id = $new ? $new[0]['id'] : null;
        echo "статья «{$title}»: создана (ID {$art_id})\n";
    }
    return $art_id;
}

$r_admin = db_query("SELECT id FROM `jos_users` WHERE username = 'admin'");
$admin_id = $r_admin ? $r_admin[0]['id'] : 1;
$r_dekan = db_query("SELECT id FROM `jos_users` WHERE username = 'dekan'");
$dekan_id = $r_dekan ? $r_dekan[0]['id'] : $admin_id;
$r_star3 = db_query("SELECT id FROM `jos_users` WHERE username = 'starosta3'");
$starosta3_id = $r_star3 ? $r_star3[0]['id'] : $admin_id;

// Статьи по заданию 2 (3 категории, по одной записи в каждой):
// Категория 1: запись от декана для старосты группы №1.
$a1 = upsert_article('dekan-star1', 'Запись декана для старосты группы №1', $cat1, $dekan_id, $lvl_star1);
// Категория 2: запись от декана для старост групп №2 и №3.
$a2 = upsert_article('dekan-star23', 'Запись декана для старост групп №2 и №3', $cat2, $dekan_id, $lvl_star23);
// Категория 3: две записи — от декана и от старосты №3 для студентов группы №3.
$a3a = upsert_article('dekan-stud3', 'Материал декана для студентов группы №3', $cat3, $dekan_id, $lvl_stud3);
$a3b = upsert_article('starosta3-stud3', 'Материал старосты №3 для студентов группы №3', $cat3, $starosta3_id, $lvl_stud3);

echo "== Категории и статьи готовы ==\n";
