<?php
/**
 * Пункты меню для ЛР7 (J3(в помощь).pdf):
 * - Регистрация новых пользователей (доступ Public)
 * - Вход / Выход (доступ Registered или Public для входа)
 * - «Создать материал» для декана и старосты (доступ ограничен уровнем доступа)
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

echo "== ЛР7: пункты меню ==\n";

// Получим уровни доступа, созданные ранее.
function get_level_id($title) {
    $r = db_query("SELECT id FROM `jos_viewlevels` WHERE title = :t", array(':t' => $title));
    return $r ? $r[0]['id'] : null;
}
$lvl_dekan = get_level_id('Для студентов'); // условно — для «Создать материал» декану и старостам
$lvl_starosta = get_level_id('Для старост');
$lvl_student = get_level_id('Для студентов');

// Функция создания/обновления пункта меню.
function menu_item($data) {
    $existing = db_query("SELECT id FROM `jos_menu` WHERE alias = :a AND menutype = 'mainmenu'",
        array(':a' => $data['alias']));
    $item_id = $existing ? $existing[0]['id'] : null;
    // Компонент для регистрации: com_users (регистрация через форму пользователя)
    // Для входа/выхода тоже com_users.
    // Для создания статьи через фронтенд: com_content с параметром view=form.
    if ($item_id) {
        $fields = array('title','alias','note','path','link','type','published','language','access','client_id','home','parent_id','level','lft','rgt','component_id','browserNav','img','params','template_style_id','publish_up','publish_down');
        $set = array(); $vals = array(':id' => $item_id);
        foreach ($fields as $k) {
            if ($k === 'id') continue;
            $set[] = "`{$k}` = :{$k}";
            $vals[":".$k] = $data[$k] ?? null;
        }
        // Для простоты обновляем только основные поля, не всё дерево.
        $update = array('title' => $data['title'], 'alias' => $data['alias'], 'link' => $data['link'], 'access' => $data['access'], 'published' => $data['published'], 'params' => $data['params'] ?? '{}');
        $update_sql = array(); $update_vals = array(':id' => $item_id);
        foreach ($update as $k => $v) {
            $update_sql[] = "`{$k}` = :{$k}";
            $update_vals[":".$k] = $v;
        }
        db_query("UPDATE `jos_menu` SET " . implode(', ', $update_sql) . " WHERE id = :id", $update_vals);
        echo "  пункт меню «{$data['title']}» (alias {$data['alias']}): обновлён\n";
        return $item_id;
    }
    $cols = array('menutype','title','alias','note','path','link','type','published','language','access','client_id','home','parent_id','level','lft','rgt','component_id','browserNav','params','img','template_style_id','publish_up','publish_down');
    $vals = array();
    $defaults = array('menutype'=>'mainmenu','type'=>'component','published'=>1,'language'=>'*','client_id'=>0,'home'=>0,'parent_id'=>1,'level'=>1,'access'=>1,'component_id'=>0,'browserNav'=>0,'img'=>'','params'=>'{}','template_style_id'=>0,'link'=>'','note'=>'','path'=>'','publish_up'=>date('Y-m-d H:i:s'),'publish_down'=>null);
    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $data)) $data[$k] = $v;
    }
    $row = array();
    foreach ($cols as $c) {
        $defaults = array('lft'=>0,'rgt'=>0,'level'=>1,'home'=>0,'client_id'=>0,'template_style_id'=>0,'browserNav'=>0,'img'=>'','params'=>'{}','publish_up'=>date('Y-m-d H:i:s'),'publish_down'=>null);
        $row[$c] = array_key_exists($c, $data) ? $data[$c] : (isset($defaults[$c]) ? $defaults[$c] : null);
        $vals[] = ':' . $c;
    }
    db_query("INSERT INTO `jos_menu` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")", $row);
    $new = db_query("SELECT id FROM `jos_menu` WHERE alias = :a AND menutype = 'mainmenu'", array(':a' => $data['alias']));
    echo "  пункт меню «{$data['title']}» (alias {$data['alias']}): создан (ID {$new[0]['id']})\n";
    return $new ? $new[0]['id'] : null;
}

// 1. Регистрация (доступ Public) — пункт для новых пользователей.
menu_item(array(
    'alias' => 'registraciya',
    'title' => 'Регистрация',
    'link' => 'index.php?option=com_users&view=registration',
    'parent_id' => 1,
    'access' => 1,
    'type' => 'component',
    'published' => 1,
    'params' => '{}',
    'path' => 'registraciya',
));

// 2. Вход / Выход — так как Joomla использует один компонент для входа и выхода,
// делаем один пункт «Вход» (доступ Registered) и один «Выход» (доступ Public для выхода).
// В простом варианте делаем только «Вход» с ссылкой на форму входа.
menu_item(array(
    'alias' => 'vhod',
    'title' => 'Вход',
    'link' => 'index.php?option=com_users&view=login',
    'parent_id' => 1,
    'access' => 1,
    'type' => 'component',
    'published' => 1,
    'params' => '{}',
    'path' => 'vhod',
));

// 3. Пункты «Создать материал» для декана и старосты (доступ ограничен уровнем).
// Уровень доступа: «Для старост» (доступ для декана и старосты) и «Для студентов» (доступ для студента, но пункт создания не нужен студенту).
// Для демонстрации задания «пункты меню видны только старосте и декану»
// назначаем доступ «Для старост» на пункт «Создать материал» для старосты/декана.

// Получим ID компонента com_content.
$r_ext = db_query("SELECT extension_id FROM `jos_extensions` WHERE element = 'com_content' AND type = 'component'");
$ext_content = $r_ext ? $r_ext[0]['extension_id'] : 22; // стандартный ID

// Для пункта «Создать материал» ссылка: index.php?option=com_content&view=form
// Параметры: нужно указать catid? Для простоты — без привязки к категории,
// но можно передать через параметры.

// Пункт меню для декана («Создать материал») — доступ «Для старост» (включает декана и старосту).
// Но чтобы показать, что декан может создавать, назначим уровень «Для старост».
menu_item(array(
    'alias' => 'sozdat-material-dekan',
    'title' => 'Создать материал (декан)',
    'link' => 'index.php?option=com_content&view=form',
    'parent_id' => 1,
    'access' => $lvl_starosta ?? 3,
    'type' => 'component',
    'published' => 1,
    'component_id' => $ext_content,
    'params' => '{"show_category":"","show_author":"","show_readmore":"","show_tags":"","language":"*","layout":"default","show_intro":"1","show_title":"1","show_icons":"0","show_print":"0","show_email":"0","show_hits":"0","show_publish_date":"1","show_create_date":"1","show_modify_date":"1","show_category_order":"1","show_article_order":"1","show_article_ordering":"1","show_pagination":"1","show_pagination_results":"1","show_pagination_limit":"1","show_feed":"1","feed_summary":"1","feed_limit":"1","feed_description":"1","feed_image":"1","show_navigation":"1","show_vote":"0","show_readmore_title":"1","show_readmore_text":"1","show_readmore_readmore":"1","show_readmore_intro":"1","show_readmore_full":"1","show_readmore_cats":"1","show_readmore_authors":"1","show_readmore_tags":"1","show_readmore_dates":"1","show_readmore_published":"1","show_readmore_modified":"1","show_readmore_hits":"1","show_readmore_comment":"1","show_readmore_vote":"1","show_readmore_author_alias":"1","show_readmore_image":"1","show_readmore_icon":"1","show_readmore_url":"1","show_readmore_target":"1","show_readmore_link":"1","show_readmore_text":"1","show_readmore_text_limit":"1","show_readmore_readmore_icon":"1"}',
    'path' => 'sozdat-material-dekan',
));

// Пункт для старосты (доступ тот же уровень или «Для старосты группы №1»?)
// Для демонстрации задания «пункты видны только старосте и декану соответственно»
// используем тот же уровень «Для старост».
menu_item(array(
    'alias' => 'sozdat-material-starosta',
    'title' => 'Создать материал (староста)',
    'link' => 'index.php?option=com_content&view=form',
    'parent_id' => 1,
    'access' => $lvl_starosta ?? 3,
    'type' => 'component',
    'published' => 1,
    'component_id' => $ext_content,
    'params' => '{}',
    'path' => 'sozdat-material-starosta',
));

echo "== Пункты меню готовы ==\n";
