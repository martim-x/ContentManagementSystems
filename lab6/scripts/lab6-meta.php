<?php
/**
 * Мета-данные (задание 5 ЛР6):
 *   1) глобальные метаданные сайта (description, robots) — в configuration.php;
 *   2) description и ключевые слова у всех статей и категорий — они уже
 *      заполнены скриптом наполнения, здесь проверяем и дополняем пустые;
 *   3) robots = index, follow по умолчанию для материалов.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-meta.php
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

echo "== Мета-данные ==\n";

// ---------------------------------------------------------------------------
// 1. Глобальные метаданные в configuration.php.
//
// В Joomla 5 глобальные описание и robots хранятся в configuration.php
// (метаданные «Система -> Общая конфигурация -> Сайт»). Правим файл
// аккуратной заменой, не трогая остальной код.
// ---------------------------------------------------------------------------
$config_path = '/var/www/html/configuration.php';
$config      = file_get_contents( $config_path );
$meta_desc   = 'Город24 — новости Минска: транспорт, городская среда, школа и спорт. Свежие материалы редакции Город24.';
$meta_robots = 'index, follow';

$changed = false;

if ( false === strpos( $config, 'public $metadesc' ) || preg_match( '/public \$metadesc = \x27{0,1}\x27;/', $config ) ) {
	if ( preg_match( "/public \\\$metadesc = '[^']*';/" , $config ) ) {
		$config = preg_replace( "/public \\\$metadesc = '[^']*';/", "public \$metadesc = '" . $meta_desc . "';", $config );
	} else {
		$config = str_replace( "public \$sitename = 'Город24';", "public \$sitename = 'Город24';\n\tpublic \$metadesc = '" . $meta_desc . "';", $config );
	}
	$changed = true;
	echo "глобальное описание сайта: добавлено в configuration.php\n";
}

if ( preg_match( "/public \\\$robots = '[^']*';/", $config ) ) {
	$config = preg_replace( "/public \\\$robots = '[^']*';/", "public \$robots = '" . $meta_robots . "';", $config );
	$changed = true;
	echo "глобальный robots: {$meta_robots}\n";
}

if ( $changed ) {
	file_put_contents( $config_path, $config );
	chmod( $config_path, 0644 );
}

// ---------------------------------------------------------------------------
// 2. Проверка description/keywords у статей и категорий.
// ---------------------------------------------------------------------------
$rows = db_query(
	"SELECT id, title, metadesc, metakey FROM `jos_content` WHERE metadesc = '' OR metadesc IS NULL"
);

foreach ( $rows as $row ) {
	$desc = 'Новость Город24: ' . $row['title'] . '. Свежие новости Минска.';
	db_query(
		"UPDATE `jos_content` SET metadesc = :d WHERE id = :id",
		array( ':d' => $desc, ':id' => $row['id'] )
	);
	echo "статья {$row['id']} «{$row['title']}»: description заполнен\n";
}

if ( ! $rows ) {
	echo "у всех статей description заполнен\n";
}

$empty_keys = db_query(
	"SELECT id, title FROM `jos_content` WHERE metakey = '' OR metakey IS NULL"
);

foreach ( $empty_keys as $row ) {
	db_query(
		"UPDATE `jos_content` SET metakey = 'минск, новости, город24' WHERE id = :id",
		array( ':id' => $row['id'] )
	);
	echo "статья {$row['id']} «{$row['title']}»: keywords заполнены\n";
}

if ( ! $empty_keys ) {
	echo "у всех статей keywords заполнены\n";
}

echo "готово\n";
