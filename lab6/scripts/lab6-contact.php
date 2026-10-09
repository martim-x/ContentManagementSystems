<?php
/**
 * Форма обратной связи (задание 7 ЛР6) — штатный компонент com_contact:
 *   1) категория контактов и контакт «Редакция Город24» с формой
 *      обратной связи и капчей g24math;
 *   2) пункт меню «Контакты» на сайте.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-contact.php
 *
 * Скрипт идемпотентен. Письма на стенде не отправляются (SMTP не настроен):
 * форма проходит валидацию и капчу, Joomla показывает сообщение об ошибке
 * отправки — для демонстрации защиты от спама этого достаточно.
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

echo "== Форма обратной связи (com_contact) ==\n";

$user_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );
$now     = date( 'Y-m-d H:i:s' );

// ---------------------------------------------------------------------------
// 1. Категория контактов.
// ---------------------------------------------------------------------------
$cat_id = db_one(
	"SELECT id FROM `jos_categories` WHERE alias = 'redakciya' AND extension = 'com_contact'",
	array(),
	'id'
);

if ( ! $cat_id ) {
	db_query(
		"INSERT INTO `jos_categories`
		 (asset_id, parent_id, path, level, lft, rgt, extension, title, alias, description,
		  published, access, created_user_id, created_time, modified_time, language)
		 VALUES (0, 1, 'redakciya', 1, 99, 100, 'com_contact', 'Редакция', 'redakciya',
		         'Контакты редакции Город24', 1, 1, :uid, NOW(), NOW(), '*')",
		array( ':uid' => $user_id )
	);
	$cat_id = db_one(
		"SELECT id FROM `jos_categories` WHERE alias = 'redakciya' AND extension = 'com_contact'",
		array(),
		'id'
	);
	echo "категория контактов «Редакция»: создана (ID {$cat_id})\n";
} else {
	echo "категория контактов «Редакция»: уже есть (ID {$cat_id})\n";
}

// ---------------------------------------------------------------------------
// 2. Контакт с формой обратной связи и капчей g24math.
// ---------------------------------------------------------------------------
$contact_params = json_encode( array(
	'show_email_form'     => '1',   // показывать форму обратной связи
	'captcha'             => 'g24math', // капча «сложение двух цифр»
	'show_email'          => '0',
	'show_street_address' => '1',
	'show_telephone'      => '1',
	'show_mobile'         => '0',
	'show_misc'           => '0',
	'show_links'          => '0',
	'show_position'       => '1',
	'allow_vcard'         => '0',
	'show_articles'       => '0',
	'contact_layout'      => '',
	'show_tags'           => '0',
), JSON_UNESCAPED_UNICODE );

$contact_id = db_one(
	"SELECT id FROM `jos_contact_details` WHERE alias = 'redakciya-gorod24'",
	array(),
	'id'
);

if ( $contact_id ) {
	db_query(
		"UPDATE `jos_contact_details`
		 SET name = 'Редакция Город24', email_to = 'admin@gorod24.by', telephone = '+375 (17) 200-00-24',
		     address = 'г. Минск, ул. Платонова, 22', con_position = 'Редакция',
		     published = 1, access = 1, catid = :catid, params = :params, language = '*', modified = NOW()
		 WHERE id = :id",
		array( ':catid' => $cat_id, ':params' => $contact_params, ':id' => $contact_id )
	);
	echo "контакт «Редакция Город24»: обновлён (ID {$contact_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_contact_details`
		 (name, alias, con_position, address, telephone, email_to, published, checked_out,
		  checked_out_time, ordering, params, user_id, catid, access, language, created, modified,
		  created_by, metakey, metadesc, metadata, featured, publish_up, publish_down, version, hits)
		 VALUES ('Редакция Город24', 'redakciya-gorod24', 'Редакция', 'г. Минск, ул. Платонова, 22',
		         '+375 (17) 200-00-24', 'admin@gorod24.by', 1, 0, '0000-00-00 00:00:00', 0,
		         :params, 0, :catid, 1, '*', NOW(), NOW(), :uid, 'город24, редакция, контакты',
		         'Форма обратной связи редакции Город24.', '{}', 0, NOW(), NULL, 1, 0)",
		array( ':params' => $contact_params, ':catid' => $cat_id, ':uid' => $user_id )
	);
	$contact_id = db_one(
		"SELECT id FROM `jos_contact_details` WHERE alias = 'redakciya-gorod24'",
		array(),
		'id'
	);
	echo "контакт «Редакция Город24»: создан (ID {$contact_id})\n";
}

// ---------------------------------------------------------------------------
// 3. Пункт меню «Контакты».
// ---------------------------------------------------------------------------
$contact_ext_id = db_one(
	"SELECT extension_id FROM `jos_extensions` WHERE element = 'com_contact' AND type = 'component'",
	array(),
	'extension_id'
);

$link = 'index.php?option=com_contact&view=contact&id=' . $contact_id;

$menu_id = db_one(
	"SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu' AND link = :link",
	array( ':link' => $link ),
	'id'
);

if ( $menu_id ) {
	db_query(
		"UPDATE `jos_menu`
		 SET title = 'Контакты', published = 1, component_id = :cid, params = :params,
		     publish_up = NOW(), publish_down = NULL
		 WHERE id = :id",
		array( ':cid' => $contact_ext_id, ':params' => '{}', ':id' => $menu_id )
	);
	echo "пункт меню «Контакты»: обновлён (ID {$menu_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_menu`
		 (menutype, title, alias, note, path, link, type, published, parent_id, level, language,
		  component_id, params, id, browserNav, access, client_id, home, checked_out, img,
		  template_style_id, publish_up, publish_down)
		 VALUES ('mainmenu', 'Контакты', 'kontakty', '', 'kontakty', :link, 'component', 1, 1, 1, '*',
		         :cid, '{}', 0, 0, 1, 0, 0, 0, '', 0, NOW(), NULL)",
		array( ':link' => $link, ':cid' => $contact_ext_id )
	);
	$menu_id = db_one(
		"SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu' AND link = :link",
		array( ':link' => $link ),
		'id'
	);
	echo "пункт меню «Контакты»: создан (ID {$menu_id})\n";
}

echo "готово\n";
