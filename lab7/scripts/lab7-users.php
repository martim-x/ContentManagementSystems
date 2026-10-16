<?php
/**
 * Создание групп пользователей и учётных записей ЛР7.
 *
 * Группы (под Registered): Деканат, Старосты, Студенты.
 * Пользователи: dekan, starosta1..3, student1..3.
 *
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

echo "== ЛР7: группы пользователей ==\n";

// Группа Registered (обычно id=2 в Jos_ usergroups после установки).
$group_registered = 2; // предполагаем
// Но лучше найти по названию.
$grp_reg_id = null;
$r = db_query("SELECT id FROM `jos_usergroups` WHERE title = 'Registered'");
if ($r) $grp_reg_id = $r[0]['id'];
if (!$grp_reg_id) {
    echo "Группа Registered не найдена\n"; exit(1);
}
echo "Registered: {$grp_reg_id}\n";

// Функция создания группы (если нет).
function create_group($title, $parent_id, $params = array()) {
    $existing = db_query("SELECT id FROM `jos_usergroups` WHERE title = :t", array(':t' => $title));
    if ($existing) {
        echo "группа «{$title}»: уже есть (ID {$existing[0]['id']})\n";
        return $existing[0]['id'];
    }
    // Вставка. У jos_usergroups нет вторичного ключа на parent, но нужен порядок.
    // Для простоты используем прямой INSERT и рассчитываем lft/rgt минимально (их пересчитает rebuild после).
    db_query(
        "INSERT INTO `jos_usergroups` (parent_id, title, lft, rgt) VALUES (:pid, :t, 1, 2)",
        array(':pid' => $parent_id, ':t' => $title)
    );
    $new = db_query("SELECT id FROM `jos_usergroups` WHERE title = :t", array(':t' => $title));
    echo "группа «{$title}»: создана (ID {$new[0]['id']})\n";
    return $new[0]['id'];
}

// 1. Деканы
$grp_dekan = create_group('Деканат', $grp_reg_id);
// 2. Старосты
$grp_starosta = create_group('Старосты', $grp_reg_id);
// 3. Студенты
$grp_student = create_group('Студенты', $grp_reg_id);

// Пользователи (пароль задаст prepare; создаём записи с известным паролем стенда ЛР7).
function upsert_user($username, $name, $email, $groups, $hash) {
    $existing = db_query("SELECT id FROM `jos_users` WHERE username = :u", array(':u' => $username));
    $uid = $existing ? $existing[0]['id'] : null;
    if ($uid) {
        db_query("UPDATE `jos_users` SET name = :n, email = :e, block = 0, activation = 0 WHERE id = :id",
            array(':n' => $name, ':e' => $email, ':id' => $uid));
        echo "пользователь {$username}: обновлён (ID {$uid})\n";
    } else {
        db_query(
            "INSERT INTO `jos_users` (name, username, email, password, block, activation, registerDate, lastvisitDate, params)
             VALUES (:n, :u, :e, :h, 0, 0, NOW(), NOW(), '')",
            array(':n' => $name, ':u' => $username, ':e' => $email, ':h' => $hash)
        );
        $uid = db_query("SELECT id FROM `jos_users` WHERE username = :u", array(':u' => $username))[0]['id'];
        echo "пользователь {$username}: создан (ID {$uid})\n";
    }
    // Удаляем старые привязки групп и проставляем новые.
    db_query("DELETE FROM `jos_user_usergroup_map` WHERE user_id = :uid", array(':uid' => $uid));
    foreach ($groups as $gid) {
        db_query("INSERT INTO `jos_user_usergroup_map` (user_id, group_id) VALUES (:uid, :gid)",
            array(':uid' => $uid, ':gid' => $gid));
    }
    echo "  привязан к группам: " . implode(',', $groups) . "\n";
    return $uid;
}

$hash = password_hash('Gorod24!Admin2026', PASSWORD_BCRYPT);
$dekan_uid = upsert_user('dekan', 'Декан', 'dekan@gorod24.by', [$grp_dekan], $hash);

$hash_user = password_hash('Gorod24!User2026', PASSWORD_BCRYPT);
$s1 = upsert_user('starosta1', 'Староста №1', 'starosta1@gorod24.by', [$grp_starosta], $hash_user);
$s2 = upsert_user('starosta2', 'Староста №2', 'starosta2@gorod24.by', [$grp_starosta], $hash_user);
$s3 = upsert_user('starosta3', 'Староста №3', 'starosta3@gorod24.by', [$grp_starosta], $hash_user);
$st1 = upsert_user('student1', 'Студент №1', 'student1@gorod24.by', [$grp_student], $hash_user);
$st2 = upsert_user('student2', 'Студент №2', 'student2@gorod24.by', [$grp_student], $hash_user);
$st3 = upsert_user('student3', 'Студент №3', 'student3@gorod24.by', [$grp_student], $hash_user);

echo "== Готово ==\n";
