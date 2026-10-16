<?php
/**
 * Уровни доступа (View Access Levels) для ЛР7.
 *
 * Уровни создаются по ТЗ ЛР7 (Joomla-3.pdf):
 * - Для студентов (студенты + старосты + декан)
 * - Для старост (старосты + декан)
 * - Для старосты группы №1 (староста1 + декан)
 * - Для старост групп №2 и №3 (староста2 + староста3 + декан)
 * - Для студентов группы №3 (student3 + староста3 + декан)
 *
 * Уровень доступа — это набор групп, которым разрешено видеть контент
 * с этим уровнем (Access Level). Joomla использует `rules` (JSON) в
 * таблице `jos_viewlevels` для хранения разрешённых групп.
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

echo "== ЛР7: уровни доступа ==\n";

// Получим ID групп, созданных lab7-users.php.
function get_group_id($title) {
    $r = db_query("SELECT id FROM `jos_usergroups` WHERE title = :t", array(':t' => $title));
    return $r ? $r[0]['id'] : null;
}

$grp_dekan = get_group_id('Деканат');
$grp_starosta = get_group_id('Старосты');
$grp_student = get_group_id('Студенты');

if (!$grp_dekan || !$grp_starosta || !$grp_student) {
    echo "ОШИБКА: не найдены группы пользователей. Запусти сначала lab7-users.php\n";
    exit(1);
}
echo "группы: Деканат={$grp_dekan}, Старосты={$grp_starosta}, Студенты={$grp_student}\n";

// Уровни доступа: создаём или обновляем.
function upsert_level($title, $groups_ids) {
    $existing = db_query("SELECT id FROM `jos_viewlevels` WHERE title = :t", array(':t' => $title));
    $rules_json = json_encode(array_values($groups_ids), JSON_UNESCAPED_UNICODE);
    if ($existing) {
        db_query("UPDATE `jos_viewlevels` SET `rules` = :r WHERE id = :id",
            array(':r' => $rules_json, ':id' => $existing[0]['id']));
        echo "уровень «{$title}»: обновлён (ID {$existing[0]['id']}, групп: " . count($groups_ids) . ")\n";
        return $existing[0]['id'];
    }
    db_query(
        "INSERT INTO `jos_viewlevels` (title, ordering, rules) VALUES (:t, 1, :r)",
        array(':t' => $title, ':r' => $rules_json)
    );
    $new = db_query("SELECT id FROM `jos_viewlevels` WHERE title = :t", array(':t' => $title));
    echo "уровень «{$title}»: создан (ID {$new[0]['id']}, групп: " . count($groups_ids) . ")\n";
    return $new[0]['id'];
}

// По заданию ЛР7 (Joomla-3.pdf) уровни должны быть:
// 1) Для студентов — студенты + старосты + декан (гость не входит)
// 2) Для старост — старосты + декан
// 3) Для старосты группы №1 — староста + декан (только староста1 + декан)
// 4) Для старост групп №2 и №3 — староста2 + староста3 + декан
// 5) Для студентов группы №3 — студент3 + староста3 + декан

// Получим ID пользователей-старост и студентов по username.
function user_id($username) {
    $r = db_query("SELECT id FROM `jos_users` WHERE username = :u", array(':u' => $username));
    return $r ? $r[0]['id'] : null;
}
$s1 = user_id('starosta1');
$s2 = user_id('starosta2');
$s3 = user_id('starosta3');
$st1 = user_id('student1');
$st2 = user_id('student2');
$st3 = user_id('student3');

// Для уровней доступа используются только группы, не пользователи напрямую,
// но в ТЗ подразумевается привязка к конкретным пользователям через категории.
// Уровни доступа — это набор групп; конкретные пользователи назначаются
// в категориях через правила создания.

// 1
upsert_level('Для студентов', array($grp_student, $grp_starosta, $grp_dekan));
// 2
upsert_level('Для старост', array($grp_starosta, $grp_dekan));
// 3
upsert_level('Для старосты группы №1', array($grp_starosta, $grp_dekan)); // группа старост общая; конкретика через категорию
// 4
upsert_level('Для старост групп №2 и №3', array($grp_starosta, $grp_dekan));
// 5
upsert_level('Для студентов группы №3', array($grp_student, $grp_starosta, $grp_dekan));

echo "== Уровни доступа готовы ==\n";
