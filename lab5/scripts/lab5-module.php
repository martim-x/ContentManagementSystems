<?php
/**
 * Регистрация и настройка модуля mod_gorod24contact в Joomla 5.
 *
 * Запуск (внутри контейнера joomla):
 *   php /var/www/html/scripts-lab5-module.php
 *
 * Скрипт идемпотентен: повторный запуск обновляет существующие записи,
 * не создавая дублей.
 *
 * Задание 3: компактная форма контакта, сворачивающаяся до кнопки,
 *           три поля — имя, email, текст обращения.
 * Задание 4: капча сложения двух цифр (реализована в самом модуле).
 */

define( 'DB_HOST', getenv( 'JOOMLA_DB_HOST' ) ?: 'db' );
define( 'DB_NAME', getenv( 'JOOMLA_DB_NAME' ) ?: 'joomla' );
define( 'DB_USER', getenv( 'JOOMLA_DB_USER' ) ?: 'joomla' );
define( 'DB_PASS', getenv( 'JOOMLA_DB_PASSWORD' ) ?: 'joomla' );

/**
 * Выполняет SQL-запрос через PDO.
 *
 * @param   string  $sql    запрос
 * @param   array   $params подставляемые значения
 * @return  array   строки результата
 */
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

echo "== Регистрация модуля mod_gorod24contact ==\n";

// ---------------------------------------------------------------------------
// 1. Строка расширения в jos_extensions
// ---------------------------------------------------------------------------
// Joomla читает параметры модуля из JSON в поле params этой строки.
$module_params = array(
	'module_tag'      => 'g24contact',
	'module_position' => 'sidebar-right',
	'title_lbl'       => 'Написать в редакцию',
	'module_email'    => 'admin@gorod24.by',
	'button_caption'  => 'Написать нам',
	'theme_color'     => '#1d4ed8',
	'layout'          => 'default',
	'language'        => 'ru-RU',
	'cache'           => '0',
);

$json = json_encode( $module_params, JSON_UNESCAPED_UNICODE );

$extension_id = db_one(
	"SELECT extension_id FROM `jos_extensions`
	 WHERE name = 'module' AND element = 'mod_gorod24contact' AND client_id = 0",
	array(),
	'extension_id'
);

if ( $extension_id ) {
	db_query(
		"UPDATE `jos_extensions`
		 SET manifest_cache = '', params = :params, enabled = 1
		 WHERE extension_id = :eid",
		array( ':params' => $json, ':eid' => $extension_id )
	);
	echo "расширение mod_gorod24contact: обновлено (ID {$extension_id})\n";
} else {
	// У jos_extensions обязательные поля без default: manifest_cache, params, custom_data.
	db_query(
		"INSERT INTO `jos_extensions`
		 (name, type, element, folder, client_id, enabled, access, protected,
		  manifest_cache, params, custom_data)
		 VALUES ('module', 'module', 'mod_gorod24contact', 'mod_gorod24contact', 0, 1, 1, 0,
		         '', :params, '')",
		array( ':params' => $json )
	);
	$extension_id = db_one(
		"SELECT extension_id FROM `jos_extensions`
		 WHERE name = 'module' AND element = 'mod_gorod24contact'",
		array(),
		'extension_id'
	);
	echo "расширение mod_gorod24contact: создано (ID {$extension_id})\n";
}

// ---------------------------------------------------------------------------
// 2. Строка модуля в jos_modules
// ---------------------------------------------------------------------------
// title = NULL у модуля означает «показывать заголовок из настроек».
$module_id = db_one(
	"SELECT id FROM `jos_modules`
	 WHERE module = 'mod_gorod24contact' AND client_id = 0",
	array(),
	'id'
);

if ( $module_id ) {
	db_query(
		"UPDATE `jos_modules`
		 SET title = 'Написать в редакцию', position = 'sidebar-right',
		     showtitle = 1, published = 1, access = 1, params = :params, language = '*'
		 WHERE id = :mid",
		array( ':params' => $json, ':mid' => $module_id )
	);
	echo "модуль: обновлён (ID {$module_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_modules`
		 (title, note, position, module, client_id, showtitle, published,
		  access, ordering, content, params, language)
		 VALUES ('Написать в редакцию', '', 'sidebar-right', 'mod_gorod24contact', 0,
		         1, 1, 1, 0, '', :params, '*')",
		array( ':params' => $json )
	);
	$module_id = db_one(
		"SELECT id FROM `jos_modules` WHERE module = 'mod_gorod24contact'",
		array(),
		'id'
	);
	echo "модуль: создан (ID {$module_id})\n";
}

// ---------------------------------------------------------------------------
// 3. Привязка модуля к пункту меню главной страницы
// ---------------------------------------------------------------------------
$home_id = db_one(
	"SELECT id FROM `jos_menu` WHERE link = 'index.php?option=com_content&view=featured'",
	array(),
	'id'
);

if ( ! $home_id ) {
	echo "ВНИМАНИЕ: пункт меню главной не найден — привязка модуля пропущена\n";
	echo "модуль опубликован, назначьте его в позицию sidebar-right вручную\n";
} else {
	// В jos_modules_menu только moduleid и menuid, колонки ordering нет,
	// поэтому порядок модулей Joomla считает сама.
	// Не дублируем привязку при повторном запуске.
	$linked = db_one(
		"SELECT moduleid FROM `jos_modules_menu` WHERE moduleid = :m AND menuid = :menu",
		array( ':m' => $module_id, ':menu' => $home_id ),
		'moduleid'
	);

	if ( $linked ) {
		echo "привязка к главной: уже есть (модуль {$module_id}, меню {$home_id})\n";
	} else {
		db_query(
			"INSERT INTO `jos_modules_menu` (moduleid, menuid) VALUES (:m, :menu)",
			array( ':m' => $module_id, ':menu' => $home_id )
		);
		echo "привязка к главной: создана (модуль {$module_id}, меню {$home_id})\n";
	}
}

// ---------------------------------------------------------------------------
// 4. Регистрация языкового файла модуля
// ---------------------------------------------------------------------------
// Без строки в jos_strings Joomla не покажет метки полей формы.
$strings = require '/var/www/html/modules/mod_gorod24contact/language/ru-RU/strings.php';

$registered = 0;

foreach ( $strings as $key => $value ) {
	$exists = db_one(
		"SELECT id FROM `jos_strings`
		 WHERE language = 'ru-RU' AND extension = 'com_modules'
		   AND constant_key = :key",
		array( ':key' => $key ),
		'id'
	);

	if ( $exists ) {
		db_query(
			"UPDATE `jos_strings` SET string_value = :val WHERE id = :id",
			array( ':val' => $value, ':id' => $exists )
		);
	} else {
		db_query(
			"INSERT INTO `jos_strings` (name, value, constant_key, language, extension, updated)
			 VALUES ('MOD_GOROD24CONTACT', :val, :key, 'ru-RU', 'com_modules', NOW())",
			array( ':val' => $value, ':key' => $key )
		);
	}
	$registered++;
}

echo "языковых строк зарегистрировано: {$registered}\n";
echo "готово\n";