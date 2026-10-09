<?php
/**
 * Активация и настройка расширений ЛР6, установленных через CLI:
 *   - plg_captcha_g24math  — капча «сложение двух цифр» (задание 4);
 *   - mod_gorod24share     — кнопки шеринга соцсетей (задание 6);
 *   - подключение CSS шеринга к сайту.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-extensions.php
 *
 * Сами пакеты ставит setup-lab6.sh штатным CLI: php cli/joomla.php
 * extension:install --path=... — здесь остаётся то, что в базу через
 * инсталлятор не приходит: включение плагина, публикация и привязка
 * модуля, подключение стилей.
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

echo "== Активация расширений ЛР6 ==\n";

// ---------------------------------------------------------------------------
// 1. Плагин капчи g24math: включаем после CLI-установки.
// ---------------------------------------------------------------------------
$g24math = db_one(
	"SELECT extension_id FROM `jos_extensions`
	 WHERE type = 'plugin' AND folder = 'captcha' AND element = 'g24math'",
	array(),
	'extension_id'
);

if ( $g24math ) {
	db_query(
		"UPDATE `jos_extensions` SET enabled = 1, access = 1 WHERE extension_id = :id",
		array( ':id' => $g24math )
	);
	echo "плагин капчи g24math: включён (ID {$g24math})\n";
} else {
	echo "ВНИМАНИЕ: плагин g24math не установлен — капча работать не будет\n";
}

// ---------------------------------------------------------------------------
// 2. Модуль шеринга: строка в jos_modules и привязка ко всем страницам.
// ---------------------------------------------------------------------------
$share_id = db_one(
	"SELECT id FROM `jos_modules` WHERE module = 'mod_gorod24share' AND client_id = 0",
	array(),
	'id'
);

$share_params = json_encode( array(
	'heading'   => 'Поделиться новостью',
	'style'     => '0',
	'module_tag' => 'div',
	'bootstrap_size' => 0,
	'header_tag' => 'h3',
	'header_class' => '',
	'cache'     => '0',
), JSON_UNESCAPED_UNICODE );

if ( $share_id ) {
	db_query(
		"UPDATE `jos_modules`
		 SET title = 'Поделиться новостью', position = 'sidebar-right',
		     showtitle = 0, published = 1, access = 1, language = '*', params = :params
		 WHERE id = :id",
		array( ':params' => $share_params, ':id' => $share_id )
	);
	echo "модуль шеринга: обновлён (ID {$share_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_modules`
		 (title, note, position, module, client_id, showtitle, published, access, ordering, content, params, language)
		 VALUES ('Поделиться новостью', '', 'sidebar-right', 'mod_gorod24share', 0, 0, 1, 1, 0, '', :params, '*')",
		array( ':params' => $share_params )
	);
	$share_id = db_one(
		"SELECT id FROM `jos_modules` WHERE module = 'mod_gorod24share' AND client_id = 0",
		array(),
		'id'
	);
	echo "модуль шеринга: создан (ID {$share_id})\n";
}

// Привязка «на всех страницах»: menuid = 0. Дубликат не создаём.
$linked = db_one(
	"SELECT moduleid FROM `jos_modules_menu` WHERE moduleid = :m AND menuid = 0",
	array( ':m' => $share_id ),
	'moduleid'
);

if ( ! $linked ) {
	db_query(
		"INSERT INTO `jos_modules_menu` (moduleid, menuid) VALUES (:m, 0)",
		array( ':m' => $share_id )
	);
	echo "привязка шеринга: на всех страницах\n";
} else {
	echo "привязка шеринга: уже есть\n";
}

// ---------------------------------------------------------------------------
// 3. CSS шеринга — подключение к сайту.
//
// Пробованный механизм com_config (параметр custom) в Joomla 5 не выводится
// в <head>, поэтому подключаем CSS напрямую в шаблоне cassiopeia — тем же
// идемпотентным способом, каким lab6-analytics.php добавляет Google
// Analytics: перед </head> с маркером g24share-css.
// ---------------------------------------------------------------------------
$css_url = '/modules/mod_gorod24share/assets/style.css';

$template_path = '/var/www/html/templates/cassiopeia/index.php';
$template      = (string) file_get_contents( $template_path );

if ( false === strpos( $template, 'g24share-css' ) ) {
	$snippet = "<!-- g24share-css: стили кнопок шеринга (mod_gorod24share) -->\n"
		. '<link rel="stylesheet" href="' . $css_url . '">' . "\n</head>";

	$updated = str_replace( '</head>', $snippet, $template );

	if ( $updated !== $template ) {
		file_put_contents( $template_path, $updated );
		chown( $template_path, 'www-data' );
		echo "CSS шеринга подключён в шаблон: {$css_url}\n";
	} else {
		echo "ОШИБКА: не найден </head> в шаблоне cassiopeia\n";
	}
} else {
	echo "CSS шеринга: уже подключён\n";
}

echo "готово\n";
