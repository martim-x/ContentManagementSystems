<?php
/**
 * Модуль поиска (задание 8 ЛР6) — Smart Search (com_finder):
 *   1) компонент Smart Search включён (в Joomla 5 он встроен);
 *   2) модуль mod_finder «Поиск по сайту» опубликован в позиции
 *       sidebar-right и привязан ко всем страницам.
 *
 * Индексация контента выполняется штатной CLI-командой Joomla из
 * setup-скрипта: php cli/joomla.php finder:index (purge и rebuild).
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-search.php
 *
 * Скрипт идемпотентен.
 */

define( 'DB_HOST', getenv( 'JOOMLA_DB_HOST' ) ?: 'db' );
define( 'DB_NAME', getenv( 'JOOMLA_DB_NAME' ) ?: 'joomla' );
define( 'DB_USER', getenv( 'JOOMLA_DB_USER' ) ?: 'joomla' );
define( 'DB_PASS', getenv( 'JOOMLA_DB_PASSWORD' ) ?: 'joomla' );

/** Выполняет SQL-запрос через PDO. */
function db_query( $sql, $params = array() ) {
	static $pdo = null;

	if ( null === $pdo ) {
		$pdo = new PDO(
			'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
			DB_USER,
			DB_PASS,
			array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			)
		);
	}

	$stmt = $pdo->prepare( $sql );
	$stmt->execute( $params );

	return $stmt->fetchAll();
}

/** Возвращает первое значение столбца по результату запроса. */
function db_one( $sql, $params = array(), $column = 'id' ) {
	$rows = db_query( $sql, $params );
	return $rows ? $rows[0][ $column ] : null;
}

echo "== Модуль поиска ==\n";

// ---------------------------------------------------------------------------
// 1. Компонент Smart Search включён.
// ---------------------------------------------------------------------------
db_query(
	"UPDATE `jos_extensions` SET enabled = 1
	 WHERE element = 'com_finder' AND type = 'component'"
);
db_query(
	"UPDATE `jos_extensions` SET enabled = 1
	 WHERE element = 'mod_finder' AND type = 'module' AND client_id = 0"
);
echo "com_finder и mod_finder: включены\n";

// ---------------------------------------------------------------------------
// 2. Модуль «Поиск по сайту» в sidebar-right, на всех страницах.
// ---------------------------------------------------------------------------
$finder_params = json_encode( array(
	'show_label'     => '1',
	'label'          => 'Поиск по сайту',
	'placeholder'    => 'Что ищем?',
	'show_suggested' => '1',
	'set_itemid'     => '0',
	'searchfilter'   => '',
	'module_tag'     => 'div',
	'bootstrap_size' => 0,
	'header_tag'     => 'h3',
	'header_class'   => '',
	'style'          => '0',
	'cache'          => '0',
) );

$mod_id = db_one(
	"SELECT id FROM `jos_modules` WHERE module = 'mod_finder' AND client_id = 0",
	array(),
	'id'
);

if ( $mod_id ) {
	db_query(
		"UPDATE `jos_modules`
		 SET title = 'Поиск по сайту', position = 'sidebar-right', showtitle = 0,
		     published = 1, access = 1, language = '*', params = :params
		 WHERE id = :id",
		array( ':params' => $finder_params, ':id' => $mod_id )
	);
	echo "модуль поиска: обновлён (ID {$mod_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_modules`
		 (title, note, position, module, client_id, showtitle, published, access, ordering, content, params, language)
		 VALUES ('Поиск по сайту', '', 'sidebar-right', 'mod_finder', 0, 0, 1, 1, 0, '', :params, '*')",
		array( ':params' => $finder_params )
	);
	$mod_id = db_one(
		"SELECT id FROM `jos_modules` WHERE module = 'mod_finder' AND client_id = 0",
		array(),
		'id'
	);
	echo "модуль поиска: создан (ID {$mod_id})\n";
}

// Привязка ко всем страницам (menuid = 0), без дублей.
$linked = db_one(
	"SELECT moduleid FROM `jos_modules_menu` WHERE moduleid = :m AND menuid = 0",
	array( ':m' => $mod_id ),
	'moduleid'
);

if ( ! $linked ) {
	db_query(
		"INSERT INTO `jos_modules_menu` (moduleid, menuid) VALUES (:m, 0)",
		array( ':m' => $mod_id )
	);
	echo "привязка поиска: на всех страницах\n";
} else {
	echo "привязка поиска: уже есть\n";
}

echo "готово\n";
