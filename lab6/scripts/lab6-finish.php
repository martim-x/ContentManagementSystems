<?php
/**
 * Язык, главная страница и оформление модуля mod_gorod24contact.
 *
 * Запуск (внутри контейнера joomla):
 *   php /var/www/html/scripts-lab6-finish.php
 *
 * Здесь доводятся до готовности вещи, которых нет в поставке Joomla:
 *   1) русский язык сайта (jos_languages) — иначе подписи полей формы
 *      показываются на английском;
 *   2) главная страница со статьями (пункт меню Home -> com_content
 *      featured с наполненной категорией) — вместо пустой заглушки;
 *   3) подключение CSS модуля формы ко всем страницам.
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

echo "== Язык, главная и оформление ==\n";

// ---------------------------------------------------------------------------
// 1. Русский язык сайта
// ---------------------------------------------------------------------------
// Поставка Joomla содержит только en-GB, поэтому русские подписи полей
// формы не находятся. Добавляем ru-RU и делаем его единственным
// опубликованным, иначе Joomla продолжит отдавать английский.
$ru = db_one( "SELECT lang_id FROM `jos_languages` WHERE lang_code = 'ru-RU'", array(), 'lang_id' );

if ( $ru ) {
	db_query( "UPDATE `jos_languages` SET published = 0 WHERE lang_code = 'en-GB'" );
	db_query( "UPDATE `jos_languages` SET published = 1 WHERE lang_id = :id", array( ':id' => $ru ) );
	echo "язык ru-RU: найден (ID {$ru}), опубликован; en-GB снят с публикации\n";
} else {
	$ru_row = array(
		'asset_id'    => 0,
		'lang_code'   => 'ru-RU',
		'title'       => 'Russian',
		'title_native' => 'Русский',
		'sef'         => 'ru',
		'image'       => 'ru-RU.png',
		'description' => 'Русский',
		'metakey'     => '',
		'metadesc'    => '',
		'sitename'    => 'Город24',
		'published'   => 1,
		'access'      => 1,
		'ordering'    => 1,
	);

	$cols   = array_keys( $ru_row );
	$values = array();

	foreach ( $cols as $c ) {
		$values[] = ':' . $c;
	}

	db_query(
		"INSERT INTO `jos_languages` (`" . implode( '`,`', $cols ) . "`) VALUES ("
			. implode( ',', $values ) . ')',
		$ru_row
	);

	$ru = db_one( "SELECT lang_id FROM `jos_languages` WHERE lang_code = 'ru-RU'", array(), 'lang_id' );

	// Старый английский убираем из публикации, чтобы Joomla не выбрала его.
	db_query( "UPDATE `jos_languages` SET published = 0 WHERE lang_code = 'en-GB'" );

	echo "язык ru-RU: создан (ID {$ru})\n";
}

$cat_id = db_one(
	"SELECT id FROM `jos_categories` WHERE alias = 'novosti' AND extension = 'com_content'",
	array(),
	'id'
);

// Язык сайта берётся из параметров компонента com_languages
// (ключ 'site'), а не из настроек сайта в com_config: см.
// SiteApplication.php, где при пустом значении подставляется
// ComponentHelper::getParams('com_languages')->get('site').
$cl_id = db_one(
	"SELECT extension_id FROM `jos_extensions`
	 WHERE name = 'com_languages' AND element = 'com_languages' AND client_id = 1",
	array(),
	'extension_id'
);

if ( $cl_id ) {
	$row = db_query(
		"SELECT params FROM `jos_extensions` WHERE extension_id = :id",
		array( ':id' => $cl_id )
	);
	$cl_params = json_decode( $row[0]['params'], true );

	if ( ! is_array( $cl_params ) ) {
		$cl_params = array();
	}

	$cl_params['site']   = 'ru-RU';
	$cl_params['debug']  = '0';
	$cl_params['seflang'] = 0;

	db_query(
		"UPDATE `jos_extensions` SET params = :params WHERE extension_id = :id",
		array( ':params' => json_encode( $cl_params, JSON_UNESCAPED_UNICODE ), ':id' => $cl_id )
	);
	echo "язык сайта (com_languages): ru-RU\n";
}

// Название сайта хранится в каждой строке jos_languages. Если у языка,
// который Joomla считает текущим, имя пустое, тема cassiopeia вызывает
// htmlspecialchars(null), и PHP печатает Deprecated прямо в HTML.
// Поэтому заполняем имя во всех строках, а не только в ru-RU.
$sitename_rows = db_query(
	"UPDATE `jos_languages` SET sitename = 'Город24' WHERE sitename = '' OR sitename IS NULL"
);
$all_names = db_query( "SELECT lang_code, sitename FROM `jos_languages`" );

echo 'название сайта по языкам:';

foreach ( $all_names as $row ) {
	echo ' ' . $row['lang_code'] . '=' . ( '' === $row['sitename'] ? 'ПУСТО' : $row['sitename'] );
}

echo "\n";

// ---------------------------------------------------------------------------
// 2. Главная страница
// ---------------------------------------------------------------------------
// Пункт Home должен показывать избранные материалы. Присваиваем статьям
// featured = 1 и убеждаемся, что пункт опубликован.
db_query( "UPDATE `jos_content` SET featured = 1 WHERE catid = :catid", array( ':catid' => $cat_id ) );

// Главная (view=featured) читает избранные материалы из отдельной
// таблицы jos_content_frontpage. Без неё Joomla показывает пустую
// страницу, даже если колонка jos_content.featured выставлена в 1.
$frontpage = db_query(
	"SELECT id FROM `jos_content` WHERE state = 1 AND access = 1 AND language IN ('*', 'ru-RU') ORDER BY id"
);

$linked = 0;

foreach ( $frontpage as $row ) {
	$has = db_one(
		"SELECT content_id FROM `jos_content_frontpage` WHERE content_id = :id",
		array( ':id' => $row['id'] ),
		'content_id'
	);

	if ( $has ) {
		continue;
	}

	db_query(
		"INSERT INTO `jos_content_frontpage` (content_id, featured_up, featured_down)
		 VALUES (:id, NULL, NULL)",
		array( ':id' => $row['id'] )
	);
	$linked++;
}

$count = db_query( "SELECT COUNT(*) AS n FROM `jos_content_frontpage`" );
echo "в jos_content_frontpage добавлено: {$linked}, всего записей: {$count[0]['n']}\n";

$home = db_one(
	"SELECT id FROM `jos_menu` WHERE link = 'index.php?option=com_content&view=featured'",
	array(),
	'id'
);

if ( $home ) {
	db_query(
		"UPDATE `jos_menu` SET published = 1, title = 'Главная', language = '*' WHERE id = :id",
		array( ':id' => $home )
	);
	echo "главная (меню ID {$home}): опубликована, заголовок «Главная»\n";
} else {
	echo "ВНИМАНИЕ: пункт главной не найден\n";
}

// Пункт «Новости» должен быть опубликован и доступен.
$news = db_one( "SELECT id FROM `jos_menu` WHERE alias = 'novosti'", array(), 'id' );

if ( $news ) {
	db_query(
		"UPDATE `jos_menu` SET published = 1, language = '*' WHERE id = :id",
		array( ':id' => $news )
	);
	echo "пункт «Новости» (меню ID {$news}): опубликован\n";
}

// У модуля формы должно быть поле language = '*', иначе при включённом
// ru-RU Joomla не выведет его ни на одной странице.
db_query(
	"UPDATE `jos_modules` SET language = '*', published = 1 WHERE module = 'mod_gorod24contact'"
);
echo "модуль формы: language = '*', опубликован\n";

// ---------------------------------------------------------------------------
// 3. Оформление модуля
// ---------------------------------------------------------------------------
// Штатных способов подключить CSS модуля к сайту в Joomla 5 нет,
// поэтому добавляем <link> в head через конфигурацию сайта:
// Joomla поддерживает пользовательский код в настройках компонента
// com_config -> custom (поле custom в параметрах сайта).
$css_url = '/modules/mod_gorod24contact/assets/style.css';

$custom = '<link rel="stylesheet" href="' . $css_url . '">';

$config_id = db_one(
	"SELECT extension_id FROM `jos_extensions` WHERE name = 'com_config' AND element = 'com_config'",
	array(),
	'extension_id'
);

if ( $config_id ) {
	$row = db_query(
		"SELECT params FROM `jos_extensions` WHERE extension_id = :id",
		array( ':id' => $config_id )
	);
	$params = json_decode( $row[0]['params'], true );

	if ( ! is_array( $params ) ) {
		$params = array();
	}

	// Не добавляем тег повторно при каждом запуске скрипта.
	$existing = isset( $params['custom'] ) ? (string) $params['custom'] : '';

	if ( false === strpos( $existing, $css_url ) ) {
		$params['custom'] = trim( $existing . "\n" . $custom );
	}

	db_query(
		"UPDATE `jos_extensions` SET params = :params WHERE extension_id = :id",
		array( ':params' => json_encode( $params, JSON_UNESCAPED_UNICODE ), ':id' => $config_id )
	);
	echo "CSS модуля подключён через custom: {$css_url}\n";
}

// Проверяем, что файл стиля физически доступен веб-серверу.
$style_path = '/var/www/html/modules/mod_gorod24contact/assets/style.css';
echo 'файл стилей: ' . ( is_file( $style_path ) ? 'на месте' : 'ОТСУТСТВУЕТ' ) . "\n";

echo "готово\n";