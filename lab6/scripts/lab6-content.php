<?php
/**
 * Наполнение сайта Город24 для ЛР6: категории, статьи, меню и RSS-ленты.
 *
 * Запуск (внутри контейнера joomla):
 *   php /var/www/html/scripts-lab6-content.php
 *
 * Задание 1: лента новостей создаётся штатными средствами Joomla —
 *   это запись в таблице jos_newsfeeds (компонент com_newsfeeds,
 *   «Ленты новостей»), а не ручной XML. RSS-ленты статьи Joomla
 *   генерирует сама по адресу index.php?option=com_newsfeeds.
 * Задание 2: в статье добавляется ссылка на другой материал —
 *   это поле links, у Joomla это «Ссылка кнопки» (core.button_text
 *   не нужен): задание 2 реализуется в поле links статьи.
 *
 * Скрипт идемпотентен: записи создаются при первом запуске и обновляются
 * при повторном, дубли не появляются.
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

/** Возвращает id записи по значению колонки в таблице. */
function find_id( $table, $column, $value, $extra = '', $params = array() ) {
	return db_one(
		"SELECT id FROM `jos_{$table}` WHERE {$column} = :val {$extra}",
		array_merge( array( ':val' => $value ), $params ),
		'id'
	);
}

echo "== Наполнение сайта Город24 ==\n";

$user_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );
echo "автор (admin) ID: {$user_id}\n";

// ---------------------------------------------------------------------------
// Идентификаторы компонентов — из jos_extensions, а не константой.
//
// Роутер Joomla сопоставляет пункт меню с компонентом строго по
// component_id в jos_menu. При установке через SQL-схему у com_content
// свой extension_id (здесь 19), у com_newsfeeds — свой (16); хардкод
// «22/27» из другой установки оставлял пункты меню без компонента, и
// Route строил компонентные пути /component/content/... вместо
// SEF-адресов пунктов меню, а на SEF-адреса отвечал 404.
// ---------------------------------------------------------------------------
$content_ext_id = db_one(
	"SELECT extension_id FROM `jos_extensions` WHERE element = 'com_content' AND type = 'component'",
	array(),
	'extension_id'
);
$newsfeeds_ext_id = db_one(
	"SELECT extension_id FROM `jos_extensions` WHERE element = 'com_newsfeeds' AND type = 'component'",
	array(),
	'extension_id'
);
echo "component_id: com_content={$content_ext_id}, com_newsfeeds={$newsfeeds_ext_id}\n";

// ---------------------------------------------------------------------------
// 1. Категория «Новости»
// ---------------------------------------------------------------------------
$cat_alias = 'novosti';

$cat_id = find_id( 'categories', 'alias', $cat_alias, "AND extension = 'com_content'" );

$cat_data = array(
	'asset_id'     => 0,
	'parent_id'    => 1,
	'path'         => $cat_alias,
	'level'        => 1,
	'lft'          => 2,
	'rgt'          => 3,
	'extension'    => 'com_content',
	'title'        => 'Новости',
	'alias'        => $cat_alias,
	'note'         => '',
	'description'  => 'Главные новости Минска: политика, экономика, транспорт, спорт и культура.',
	'published'    => 1,
	'access'       => 1,
	'params'       => '{"category_layout":"","image":"","image_caption":"","image_alt":"","layout_params":"","filter_tag":"","use_assets":"0","workflow_id":"","description":""}',
	'metadesc'     => 'Главные новости Минска — лента Город24.',
	'metakey'      => 'минск, новости, город24',
	'metadata'     => '{"author_id":1,"og_title":"","og_description":"","og_image":"","twitter_title":"","twitter_description":"","twitter_image":""}',
	'created_user_id'  => $user_id,
	'created_time'     => date( 'Y-m-d H:i:s' ),
	'modified_user_id' => $user_id,
	'modified_time'    => date( 'Y-m-d H:i:s' ),
	'language'     => '*',
	'version'      => 1,
);

if ( $cat_id ) {
	// При обновлении не трогаем служебные поля дерева (lft/rgt/level/path):
	// их пересчитывает сам Joomla, ручное изменение ломает вложенность.
	$skip = array( 'alias', 'extension', 'lft', 'rgt', 'level', 'path', 'parent_id', 'asset_id' );
	$set  = array();

	foreach ( array_keys( $cat_data ) as $k ) {
		if ( in_array( $k, $skip, true ) ) {
			continue;
		}
		$set[] = "`{$k}` = :{$k}";
	}

	$values = array( ':id' => $cat_id );

	foreach ( array_keys( $cat_data ) as $k ) {
		if ( in_array( $k, $skip, true ) ) {
			continue;
		}
		$values[ ':' . $k ] = $cat_data[ $k ];
	}

	db_query(
		"UPDATE `jos_categories` SET " . implode( ', ', $set ) . " WHERE id = :id",
		$values
	);
	echo "категория «Новости»: обновлена (ID {$cat_id})\n";
} else {
	$cols = array_keys( $cat_data );
	$sql  = "INSERT INTO `jos_categories` (`" . implode( '`,`', $cols ) . "`) VALUES (:"
		. implode( ',:', $cols ) . ')';

	db_query( $sql, $cat_data );
	$cat_id = find_id( 'categories', 'alias', $cat_alias, "AND extension = 'com_content'" );
	echo "категория «Новости»: создана (ID {$cat_id})\n";
}

// ---------------------------------------------------------------------------
// 2. Статьи
// ---------------------------------------------------------------------------
$articles = array(
	array(
		'alias' => 'novytramvay-v-minske',
		'title'  => 'Новый трамвай вышел на линию 5',
		'intro'  => 'Первый из десяти низкопольных вагонов 2026 года начал ходить от площади Мясникова до Серебрянки.',
		'text'   => "<p>Сегодня утром на маршрут №5 вышел первый низкопольный трамвай из новой партии. Вагоны оснащены кондиционерами, валидаторами бесконтактной оплаты и экранами с остановками.</p>\n<p>По словам городских властей, до конца года на линию выйдут все десять.</p>",
	),
	array(
		'alias' => 'novaya-ploshchadka-otkrylas',
		'title'  => 'На месте пустыря открылась площадка с велопарковкой',
		'intro'  => 'В бывшем промзоне у улицы Ленина обустроили велопарковку на 240 мест и зелёную зону отдыха.',
		'text'   => "<p>Велопарковка на 240 мест открылась в бывшем промзоне. Территорию благоустроили: асфальт, освещение, скамейки и велодвери.</p>\n<p>Проектом предусмотрена и зона отдыха с навесом.</p>",
	),
	array(
		'alias' => 'shkola-110-otremontirovali',
		'title'  => 'Школу №110 отремонтировали за четыре месяца',
		'intro'  => 'В здании заменили инженерные сети, окна и кровлю — здание открыли к новому учебному году.',
		'text'   => "<p>Капитальный ремонт школы №110 завершили досрочно. Заменили отопление, электрику, окна и кровлю, обновили спортзал и пищеблок.</p>\n<p>Школа приняла учеников 1 сентября без задержек.</p>",
	),
	array(
		'alias' => 'teatr-v-park-vystupil',
		'title'  => 'В парке открылась летняя сцена',
		'intro'  => 'Театр под открытым небом начал сезон: по выходным в парке идут спектакли и концерты.',
		'text'   => "<p>Летняя сцена в городском парке открыла сезон. Вход на спектакли свободный, концерты идут по субботам и воскресеньям.</p>\n<p>Расписание публикуют на сайте театра.</p>",
	),
	array(
		'alias' => 'hockey-sezon-nachalsya',
		'title'  => 'Хоккейный сезон начался победой минчан',
		'intro'  => 'В первом матче чемпионата команда победила соперника со счётом 3:1.',
		'text'   => "<p>Хоккейный сезон в Минске открылся матчем, который собрал полные трибулы. Домашняя команда победила со счётом 3:1.</p>\n<p>Следующая игра — в субботу.</p>",
	),
	array(
		'alias' => 'kofeinya-na-vokzale-zakrylas',
		'title'  => 'Кофейня в старом корпусе вокзала закрылась',
		'intro'  => 'Арендатор не продлил договор, помещение ищет нового владельца.',
		'text'   => "<p>Кофейня в историческом корпусе вокзала закрылась: арендатор не продлил договор. Помещение площадью 90 квадратных метров выставлено на поиск нового арендатора.</p>\n<p>За несколько лет здесь открыли около десятка разных проектов.</p>",
	),
);

/**
 * Возвращает id статьи по alias, при необходимости создаёт запись.
 *
 * @param   string  $alias  алиас статьи
 * @param   array   $data   поля статьи
 * @param   int     $cat_id категория
 * @return  int      id статьи
 */
function upsert_article( $alias, array $data, $cat_id, $user_id ) {
	$existing = db_one(
		"SELECT id FROM `jos_content` WHERE alias = :a",
		array( ':a' => $alias ),
		'id'
	);

	$fields = array(
		'title'            => $data['title'],
		'introtext'        => '<p>' . $data['intro'] . '</p>',
		'fulltext'         => $data['text'],
		'alias'            => $alias,
		'catid'            => $cat_id,
		'created_by'       => $user_id,
		'created'          => date( 'Y-m-d H:i:s' ),
		'modified'         => date( 'Y-m-d H:i:s' ),
		// NULL вместо 0000-00-00 00:00:00: Joomla 5 в строгом режиме
		// считает нулевую дату снятием с публикации.
		'publish_up'       => date( 'Y-m-d H:i:s' ),
		'publish_down'     => null,
		'state'            => 1,
		'access'           => 1,
		'language'         => '*',
		'metadesc'         => $data['intro'],
		'metakey'          => 'минск, новости, город24',
		'urls'             => '{}',
		'images'           => '{}',
		'attribs'          => '{}',
		'metadata'         => '{"author_id":' . $user_id . ',"og_title":"","og_description":"","og_image":"","twitter_title":"","twitter_description":"","twitter_image":""}',
		'note'             => '',
		'ordering'         => 0,
		'asset_id'         => 0,
		'checked_out'      => 0,
	);

	if ( $existing ) {
		$set = array();
		foreach ( array_keys( $fields ) as $k ) {
			$set[] = "`{$k}` = :{$k}";
		}
		db_query(
			"UPDATE `jos_content` SET " . implode( ', ', $set ) . " WHERE id = :id",
			array_merge( $fields, array( ':id' => $existing ) )
		);

		return $existing;
	}

	$cols = array_keys( $fields );
	$sql  = "INSERT INTO `jos_content` (`" . implode( '`,`', $cols ) . "`) VALUES (:"
		. implode( ',:', $cols ) . ')';

	db_query( $sql, $fields );

	return db_one( "SELECT id FROM `jos_content` WHERE alias = :a", array( ':a' => $alias ), 'id' );
}

$article_ids = array();

foreach ( $articles as $a ) {
	$id = upsert_article( $a['alias'], $a, $cat_id, $user_id );
	$article_ids[ $a['alias'] ] = $id;
	echo "статья «{$a['title']}»: ID {$id}\n";
}

// ---------------------------------------------------------------------------
// 2a. Пункты меню для материалов
// ---------------------------------------------------------------------------
// При включённом SEF статья открывается только если у неё есть пункт
// меню: без него роутер не знает алиас и отдаёт 301 на пустой адрес
// /component/content/article/. Поэтому заводим пункты меню на статьи,
// между которыми строим перекрёстные ссылки (задание 2).
$article_menu_items = array(
	'novytramvay-v-minske' => array( 'title' => 'Новый трамвай вышел на линию 5', 'parent' => 102 ),
	'shkola-110-otremontirovali' => array( 'title' => 'Школа №110: ремонт завершён', 'parent' => 102 ),
	'novaya-ploshchadka-otkrylas' => array( 'title' => 'Велопарковка на месте пустыря', 'parent' => 102 ),
	'teatr-v-park-vystupil' => array( 'title' => 'Летняя сцена в парке', 'parent' => 102 ),
	'hockey-sezon-nachalsya' => array( 'title' => 'Хоккейный сезон начался', 'parent' => 102 ),
	'kofeinya-na-vokzale-zakrylas' => array( 'title' => 'Кофейня на вокзале закрылась', 'parent' => 102 ),
);

$menu_order = 10;
$menu_lft   = 100;

foreach ( $article_menu_items as $item_alias => $item_data ) {
	$art = db_one(
		"SELECT id, catid FROM `jos_content` WHERE alias = :a",
		array( ':a' => $item_alias ),
		'id'
	);

	if ( ! $art ) {
		echo "статья {$item_alias}: не найдена, пункт меню пропущен\n";
		continue;
	}

	$link     = 'index.php?option=com_content&view=article&id=' . $art;
	$existing = db_one(
		"SELECT id FROM `jos_menu` WHERE link = :link",
		array( ':link' => $link ),
		'id'
	);

	$item_params = json_encode(
		array(
			'show_title'    => 1,
			'show_intro'    => 1,
			'show_readmore' => 1,
			'show_links'    => 1,
			'show_images'   => 0,
			'show_icons'    => 0,
			'show_category' => 1,
			'show_author'   => 0,
			'show_created'  => 0,
			'show_hits'     => 0,
			'show_tags'     => 0,
			'language'      => '*',
			'layout'        => 'default',
			'pagebreak'     => 0,
		),
		JSON_UNESCAPED_UNICODE
	);

	if ( $existing ) {
		db_query(
			"UPDATE `jos_menu` SET published = 1, title = :title, component_id = :cid, params = :params WHERE id = :id",
			array( ':title' => $item_data['title'], ':params' => $item_params, ':cid' => $content_ext_id, ':id' => $existing )
		);
	} else {
		$row = array(
			'menutype'  => 'mainmenu',
			'title'     => $item_data['title'],
			'alias'     => $item_alias,
			'note'      => '',
			'path'      => $item_alias,
			'link'      => $link,
			'type'      => 'component',
			'published' => 1,
			'parent_id' => $item_data['parent'],
			'level'     => 2,
			'language'  => '*',
			'component_id' => $content_ext_id,
			'params'    => $item_params,
			'id'        => 0,
			'browserNav' => 0,
			'access'    => 1,
			'client_id' => 0,
			'home'      => 0,
			'checked_out' => 0,
			'img'       => '',
			'template_style_id' => 0,
			// NULL вместо 0000-00-00 00:00:00: SiteMenu фильтрует пункты
			// условием «publish_down IS NULL OR publish_down >= now»,
			// и нулевая дата снимает пункт с публикации — SEF-адреса
			// перестают резолвиться.
			'publish_up'   => date( 'Y-m-d H:i:s' ),
			'publish_down' => null,
		);

		$cols   = array_keys( $row );
		$values = array();

		foreach ( $cols as $c ) {
			$values[] = ':' . $c;
		}

		db_query(
			"INSERT INTO `jos_menu` (`" . implode( '`,`', $cols ) . "`) VALUES ("
				. implode( ',', $values ) . ')',
			$row
		);

	}
}

// Колонки lft/rgt у jos_menu не трогаем: это пары вложенности, и
// ручная перенумерация ломает обход дерева ( Joomla перестаёт
// находить пункты меню целиком). Порядок задаётся полем menu_order,
// которое Joomla использует при сохранении пункта через API.
$article_menu_count = db_one(
	"SELECT COUNT(*) AS c FROM `jos_menu` WHERE link LIKE 'index.php?option=com_content&view=article%'",
	array(),
	'c'
);
echo "пунктов меню для статей: {$article_menu_count}\n";

// Чиним дерево пунктов меню. Значения lft/rgt задают вложенность,
// и ручная перенумерация ломает обход: Joomla перестаёт находить
// пункты. Пересборку делает отдельный скрипт на API Joomla -
// см. scripts/lab6-menu-tree.php, там Table\Nested::rebuild().
$menu_count = db_one(
	"SELECT COUNT(*) AS c FROM `jos_menu` WHERE client_id = 0",
	array(),
	'c'
);

echo "пунктов меню в базе: {$menu_count}\n";

// ---------------------------------------------------------------------------
// 3. Задание 2 — ссылка на другой материал
// ---------------------------------------------------------------------------
// В Joomla это штатное поле «Ссылка» у материала (links в jos_content).
// Значение задаётся в формате JSON: {"index.php?option=com_content&view=article&id=X": {"title": "..."}}
// После этого Joomla сама рисует кнопку-ссылку в конце статьи.
$first_alias  = 'novytramvay-v-minske';
$second_alias = 'shkola-110-otremontirovali';

$first_id  = $article_ids[ $first_alias ];
$second_id = $article_ids[ $second_alias ];

// Поле «Ссылки» у материала в Joomla 5 имеет фиксированную структуру:
// layout article/default_links.php читает ключи urla/urlatext/targeta,
// urlb/urlbtext/targetb и urlc/urlctext/targetc. Произвольный URL
// в качестве ключа Joomla не рисует, поэтому заполняем urla.
// Адрес указываем абсолютным. Относительный index.php?... Joomla
// в поле «Ссылки» не пропускает через роутер и схлопывает в "/",
// поэтому ссылка вела бы на главную. Абсолютный адрес сохраняется
// как есть и открывает нужный материал.
$link_json = json_encode(
	array(
	'urla'     => 'http://localhost:8099/index.php?option=com_content&view=article&id=' . $second_id,
	'urlatext' => 'Читайте также: ' . $articles[2]['title'],
	'targeta'  => 0,
),
JSON_UNESCAPED_UNICODE
);

db_query(
	"UPDATE `jos_content` SET urls = :urls WHERE id = :id",
	array( ':urls' => $link_json, ':id' => $first_id )
);
echo "ссылка из статьи {$first_id} на материал {$second_id}: задана (urls)\n";

// Задание выполняет штатное поле «Ссылки» (urla), его отрисовывает
// layout article/default_links.php. Дополнительная ссылка в тексте
// не нужна: Joomla переписывает такой href через SEF-роутер, и он
// уводил на главную.
// Ранее в текст материала добавлялась ссылка на второй материал.
// Теперь задание выполняет штатное поле «Ссылки» (urla), поэтому
// убираем дубликат, если он остался от прошлых запусков.
$current_fulltext = (string) db_one(
	"SELECT `fulltext` FROM `jos_content` WHERE id = :id",
	array( ':id' => $first_id ),
	'fulltext'
);

$stripped_fulltext = preg_replace(
	'#\s*<p class="g24-related">.*?</p>#s',
	'',
	$current_fulltext
);

if ( $stripped_fulltext !== $current_fulltext ) {
	db_query(
		"UPDATE `jos_content` SET `fulltext` = :t WHERE id = :id",
		array( ':t' => $stripped_fulltext, ':id' => $first_id )
	);
	echo "дублирующая ссылка из текста: убрана\n";
} else {
	echo "дублирующей ссылки в тексте: нет\n";
}

// Пункт меню «Подробнее» на второй материал не обязателен, но
// убеждаемся, что материал доступен по постоянной ссылке.
$detail_alias = $articles[2]['alias'];
echo "целевой материал: /{$detail_alias} (id={$second_id})\n";

// ---------------------------------------------------------------------------
// 4. Пункт меню блога категории
// ---------------------------------------------------------------------------
$menu_item_params = '{"show_submenu_heading":"1","show_submenu_category":"1",'
	. '"show_title":"1","show_intro":"1","show_category":"0","show_image":"0",'
	. '"show_readmore":"1","show_author":"0","show_created":"1","show_hits":"1",'
	. '"show_publish_date":"0","show_category_order":"1","show_article_order":"0",'
	. '"show_tags":"0","show_images":"0","show_links":"1","show_attribs":"0",'
	. '"showIcon":"0","show_readmore_category":"0","language":"*","layout":"blog",'
	. '"module_layout":"","module_tag":"","moduleclass_sfx":"","cache":"0",'
	. '"pagebreak":"1","cat_pagination":"1","leading":"4","high":"4","list":"8",'
	. '"orderby_published":"1","orderby":"alpha","tag_pagination":"1"}';

$menu_row = db_one(
	"SELECT id FROM `jos_menu` WHERE link = :link AND type = 'component'",
	array( ':link' => 'index.php?option=com_content&view=category&id=' . $cat_id ),
	'id'
);

if ( $menu_row ) {
	db_query(
		"UPDATE `jos_menu` SET title = :title, component_id = :cid, params = :params WHERE id = :id",
		array( ':title' => 'Новости', ':params' => $menu_item_params, ':cid' => $content_ext_id, ':id' => $menu_row )
	);
	echo "пункт меню «Новости»: обновлён (ID {$menu_row})\n";
} else {
	$cat_link = 'index.php?option=com_content&view=category&id=' . $cat_id;

	// Колонки и значения строятся из одного массива, чтобы их количества
	// не могли разойтись: порядок ключей определяет оба списка.
	$menu_row_data = array(
		'menutype'         => 'mainmenu',
		'title'            => 'Новости',
		'alias'            => 'novosti',
		'note'             => '',
		'path'             => 'novosti',
		'link'             => $cat_link,
		'type'             => 'component',
		'published'        => 1,
		'language'         => '*',
		'params'           => $menu_item_params,
		'home'             => 0,
		'component_id'     => $content_ext_id,
		'parent_id'        => 1,
		'id'               => 0,
		'level'            => 1,
		'lft'              => 6,
		'rgt'              => 7,
		'browserNav'       => 0,
		'access'           => 1,
		'client_id'        => 0,
		'checked_out'      => 0,
		'img'              => '',
		'template_style_id' => 0,
		'publish_up'       => date( 'Y-m-d H:i:s' ),
		'publish_down'     => null,
	);

	$menu_cols   = array_keys( $menu_row_data );
	$menu_values = array();

	foreach ( $menu_cols as $c ) {
		$menu_values[] = ':' . $c;
	}

	db_query(
		"INSERT INTO `jos_menu` (`" . implode( '`,`', $menu_cols ) . "`) VALUES ("
			. implode( ',', $menu_values ) . ')',
		$menu_row_data
	);
	$menu_row = db_one(
		"SELECT id FROM `jos_menu` WHERE link = :link",
		array( ':link' => 'index.php?option=com_content&view=category&id=' . $cat_id ),
		'id'
	);
	echo "пункт меню «Новости»: создан (ID {$menu_row})\n";
}

// ---------------------------------------------------------------------------
// 5. Задание 1 — RSS-лента новостей
// ---------------------------------------------------------------------------
// Штатное средство Joomla: компонент com_newsfeeds. Лента описывается
// записью в jos_newsfeeds, а её источник — RSS-фид категории «Новости»:
// Joomla сама генерирует этот фид для com_content по адресу
// index.php?option=com_content&view=category&id=<id>&format=feed.
// Ранее link указывал на HTML-страницу категории, и модуль mod_feed
// при открытии такого «RSS» падал с ошибкой XMLReader.
$rss_feed_url = 'http://localhost:8099/index.php?option=com_content&view=category&id=' . $cat_id . '&format=feed&type=rss';
echo "адрес RSS-фида категории: {$rss_feed_url}\n";

$rss_params = json_encode(
	array(
		'feedLimit'        => 10,
		'feedImage'        => '',
		'feedTitle'        => 'Город24: главное за день',
		'feedLink'         => 'http://localhost:8099/',
		'feedDescription'  => 'Локальная повестка Минска: проверка фактов, без фейков.',
		'language'         => 'ru-RU',
		'rssAuthor'        => 'Редакция Город24',
		'categoryParams'   => array(),
		'feedFormat'       => 'rss',
	),
	JSON_UNESCAPED_UNICODE
);

$rss_id = db_one(
	"SELECT id FROM `jos_newsfeeds` WHERE name = 'Новости Город24'",
	array(),
	'id'
);

if ( $rss_id ) {
	db_query(
		"UPDATE `jos_newsfeeds`
		 SET name = 'Новости Город24', alias = :alias, catid = :catid, params = :params,
		     numarticles = 10, link = :link, description = :descr, language = '*',
		     published = 1, access = 1, rtl = 0, cache_time = 0
		 WHERE id = :id",
		array(
			':alias'   => 'gorod24-news',
			':catid'   => $cat_id,
			':params'  => $rss_params,
			':link'    => $rss_feed_url,
			':descr'   => 'Лента главных новостей Минска',
			':id'      => $rss_id,
		)
	);
	echo "RSS-лента: обновлена (ID {$rss_id})\n";
} else {
	$rss_row = array(
		'name'             => 'Новости Город24',
		'alias'            => 'gorod24-news',
		'catid'            => $cat_id,
		'description'      => 'Лента главных новостей Минска',
		'published'        => 1,
		'params'           => $rss_params,
		'link'             => $rss_feed_url,
		'numarticles'      => 10,
		'language'         => '*',
		'created'          => date( 'Y-m-d H:i:s' ),
		'created_by'       => $user_id,
		'created_by_alias' => '',
		'modified'         => date( 'Y-m-d H:i:s' ),
		'modified_by'      => $user_id,
		'cache_time'       => 0,
		'checked_out'      => 0,
		'checked_out_time' => null,
		'ordering'         => 0,
		'rtl'              => 0,
		'access'           => 1,
		'metakey'          => 'минск, новости, город24',
		'metadesc'         => 'Лента главных новостей Минска — Город24.',
		'metadata'         => '{}',
		'publish_up'       => date( 'Y-m-d H:i:s' ),
		'publish_down'     => null,
		'version'          => 1,
		'hits'             => 0,
		'images'           => '{}',
	);

	$rss_cols   = array_keys( $rss_row );
	$rss_values = array();

	foreach ( $rss_cols as $c ) {
		$rss_values[] = ':' . $c;
	}

	db_query(
		"INSERT INTO `jos_newsfeeds` (`" . implode( '`,`', $rss_cols ) . "`) VALUES ("
			. implode( ',', $rss_values ) . ')',
		$rss_row
	);
	$rss_id = db_one( "SELECT id FROM `jos_newsfeeds` WHERE name = 'Новости Город24'", array(), 'id' );
	echo "RSS-лента: создана (ID {$rss_id})\n";
}

// ---------------------------------------------------------------------------
// 6. Модуль RSS-ленты
// ---------------------------------------------------------------------------
// Модуль mod_feed читает RSS по единственному обязательному параметру
// rssurl (FeedFactory::getFeed), остальные ключи управляют отображением:
// rssitems — сколько позиций показать, rsstitle/rssdesc/rssimage/
// rssdate/rssitemdate/rssitemdesc — что выводить. Пустой rssurl у
// mod_feed приводит к ValueError внутри XMLReader и HTTP 500.
$feed_module_params = json_encode(
	array(
		'rssurl'       => $rss_feed_url,
		'rssitems'     => 10,
		'rsstitle'     => 1,
		'rssdesc'      => 1,
		'rssimage'     => 1,
		'rssdate'      => 1,
		'rssitemdate'  => 1,
		'rssitemdesc'  => 1,
		'word_count'   => 0,
		'rssrtl'       => 0,
		'module_tag'   => 'div',
		'bootstrap_size' => 0,
		'header_tag'   => 'h3',
		'header_class' => '',
		'style'        => '0',
	),
	JSON_UNESCAPED_UNICODE
);

// Пункт меню самой ленты.
// Поиск строго по mainmenu и точному link: запрос «LIKE '%com_newsfeeds%'»
// первым находил одноимённый пункт АДМИНСКОГО меню (client_id = 1,
// «News Feeds»), и тогда новый пункт не создавался, а модуль ленты
// привязывался к админскому пункту и не показывался на сайте.
$feed_menu = db_one(
	"SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu' AND link = :link",
	array( ':link' => 'index.php?option=com_newsfeeds&view=newsfeed&id=' . $rss_id ),
	'id'
);

if ( ! $feed_menu ) {
	$feed_menu_row = array(
		'menutype'  => 'mainmenu',
		'title'     => 'RSS-лента',
		'alias'     => 'rss-lenta',
		'note'      => '',
		'path'      => 'rss-lenta',
		'link'      => 'index.php?option=com_newsfeeds&view=newsfeed&id=' . $rss_id,
		'type'      => 'component',
		'published' => 1,
		'parent_id' => 1,
		'level'     => 1,
		'language'  => '*',
		'component_id' => $newsfeeds_ext_id,
		'params'    => '{}',
		'id'        => 0,
		'lft'       => 8,
		'rgt'       => 9,
		'browserNav' => 0,
		'access'    => 1,
		'client_id' => 0,
		'home'      => 0,
		'checked_out' => 0,
		'img'       => '',
		'template_style_id' => 0,
		// NULL вместо 0000-00-00: нулевая дата в publish_down снимает пункт
		// меню с публикации в выборке SiteMenu (см. комментарий выше).
		'publish_up'   => date( 'Y-m-d H:i:s' ),
		'publish_down' => null,
	);

	$fm_cols   = array_keys( $feed_menu_row );
	$fm_values = array();

	foreach ( $fm_cols as $c ) {
		$fm_values[] = ':' . $c;
	}

	db_query(
		"INSERT INTO `jos_menu` (`" . implode( '`,`', $fm_cols ) . "`) VALUES ("
			. implode( ',', $fm_values ) . ')',
		$feed_menu_row
	);

	$feed_menu = db_one(
		"SELECT id FROM `jos_menu` WHERE alias = 'rss-lenta'",
		array(),
		'id'
	);
	echo "пункт меню ленты: создан (ID {$feed_menu})\n";
} else {
	echo "пункт меню ленты: найден (ID {$feed_menu})\n";
}

// Модуль mod_feed с источником «лента новостей».
$feed_module = db_one(
	"SELECT id FROM `jos_modules` WHERE module = 'mod_feed' AND client_id = 0",
	array(),
	'id'
);

if ( $feed_module ) {
	db_query(
		"UPDATE `jos_modules`
		 SET title = 'RSS-лента новостей', position = 'main-bottom', published = 1,
		     showtitle = 0, access = 1, language = '*', params = :params
		 WHERE id = :id",
		array( ':params' => $feed_module_params, ':id' => $feed_module )
	);
	$mod_id = $feed_module;
	echo "модуль mod_feed: обновлён (ID {$mod_id})\n";
} else {
	$feed_module_row = array(
		'title'      => 'RSS-лента новостей',
		'note'       => '',
		'position'   => 'main-bottom',
		'module'     => 'mod_feed',
		'client_id'  => 0,
		'showtitle'  => 0,
		'published'  => 1,
		'access'     => 1,
		'ordering'   => 0,
		'content'    => '',
		'params'     => $feed_module_params,
		'language'   => '*',
	);

	$fd_cols   = array_keys( $feed_module_row );
	$fd_values = array();

	foreach ( $fd_cols as $c ) {
		$fd_values[] = ':' . $c;
	}

	db_query(
		"INSERT INTO `jos_modules` (`" . implode( '`,`', $fd_cols ) . "`) VALUES ("
			. implode( ',', $fd_values ) . ')',
		$feed_module_row
	);
	$mod_id = db_one(
		"SELECT id FROM `jos_modules` WHERE module = 'mod_feed' AND client_id = 0",
		array(),
		'id'
	);
	echo "модуль mod_feed: создан (ID {$mod_id})\n";
}

// Привязываем модуль к пункту меню ленты.
$linked_feed = db_one(
	"SELECT moduleid FROM `jos_modules_menu` WHERE moduleid = :m AND menuid = :menu",
	array( ':m' => $mod_id, ':menu' => $feed_menu ),
	'moduleid'
);

if ( ! $linked_feed ) {
	db_query(
		"INSERT INTO `jos_modules_menu` (moduleid, menuid) VALUES (:m, :menu)",
		array( ':m' => $mod_id, ':menu' => $feed_menu )
	);
	echo "привязка mod_feed к пункту ленты: создана\n";
} else {
	echo "привязка mod_feed к пункту ленты: уже есть\n";
}

// Привязки mod_feed к пунктам вне mainmenu — мусор от прошлых запусков
// (например, к админскому «News Feeds»): на сайте такой модуль всё равно
// не отображается, а в админке список привязок выглядит сломанным.
db_query(
	"DELETE FROM `jos_modules_menu`
	 WHERE moduleid = :m
	   AND menuid NOT IN (SELECT id FROM (SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu') site_items)",
	array( ':m' => $mod_id )
);
echo "сторонние привязки mod_feed: удалены\n";

echo "адрес страницы ленты: /rss-lenta (модуль mod_feed показывает RSS-фид)\n";

// ---------------------------------------------------------------------------
// 7. Пересборка дерева пунктов меню
// ---------------------------------------------------------------------------
// Делаем строго последним шагом: выше по скрипту создаются пункты
// «Новости» и «RSS-лента», и если пересобрать дерево раньше, их
// значения lft/rgt окажутся посчитанными поверх готовой структуры.
// Дерево строится отдельно для каждого menutype: в jos_menu рядом
// лежат mainmenu (сайт) и main (админка), а lft/rpt считаются в
// пределах одного меню. Смешивать их нельзя - пункты разных меню
// получат пересекающиеся значения, и Joomla перестанет их находить.
// Колонка path хранит не идентификаторы, а алиасы предков через
// слеш: "novosti/novytramvay-v-minske". Именно по ней SEF-роутер
// Joomla собирает адрес страницы, поэтому path считаем из алиасов.
$tree_menu_types = db_query( "SELECT DISTINCT menutype FROM `jos_menu` ORDER BY menutype" );

$tree_total = 0;

foreach ( $tree_menu_types as $tree_menu_type ) {
	$tree_rows = db_query(
		"SELECT id, parent_id, alias FROM `jos_menu`
		 WHERE menutype = :menutype
		 ORDER BY parent_id, lft, id",
		array( ':menutype' => $tree_menu_type['menutype'] )
	);

	// Группируем по родителю и по алиасу: алиас нужен для path.
	$tree_by_parent = array();

	foreach ( $tree_rows as $row ) {
		$tree_by_parent[ (int) $row['parent_id'] ][] = (int) $row['id'];

		$tree_alias[ (int) $row['id'] ] = $row['alias'];
	}

	$tree_counter = rebuild_menu_tree( 1, 0, 1, $tree_by_parent, '', $tree_alias );

	echo "  {$tree_menu_type['menutype']}: {$tree_counter} значений, "
		. count( $tree_rows ) . " пунктов\n";

	$tree_total += (int) $tree_counter;
}

/**
 * Пересчитывает lft/rgt/level/path пунктов меню обходом в глубину.
 *
 * В образе нет пакета joomla-console, поэтому штатный
 * Table\Nested::rebuild() из консоли недоступен, и обход делаем сами.
 * Значения обязаны удовлетворять условию вложенности Joomla:
 * lft(parent) < lft(child) < rgt(child) < rgt(parent).
 *
 * @param int    $parent_id     Родитель, с которого начинаем обход.
 * @param int    $counter       Счётчик, растёт на каждом посещении узла.
 * @param int    $level         Уровень вложенности для потомков.
 * @param array  $tree_by_parent Пункты, сгруппированные по parent_id.
 * @param string $tree_path     Путь алиасов предков через слеш.
 * @param array  $tree_alias    Алиас каждого пункта по его id.
 *
 * @return int Значение счётчика после обхода.
 */
function rebuild_menu_tree( $parent_id, $counter, $level, $tree_by_parent, $tree_path, $tree_alias ) {
	foreach ( $tree_by_parent[ $parent_id ] ?? array() as $child_id ) {
		$lft = ++$counter;

		// path хранит алиасы предков через слеш, а не идентификаторы:
		 // именно по нему SEF-роутер Joomla собирает адрес страницы.
		$child_alias = $tree_alias[ $child_id ] ?? (string) $child_id;
		$child_path  = '' === $tree_path ? $child_alias : $tree_path . '/' . $child_alias;

		$counter = rebuild_menu_tree(
			$child_id,
			$counter,
			$level + 1,
			$tree_by_parent,
			$child_path,
			$tree_alias
		);

		$rgt = ++$counter;

		db_query(
			"UPDATE `jos_menu` SET lft = :lft, rgt = :rgt, level = :level, path = :path WHERE id = :id",
			array(
				':lft'   => $lft,
				':rgt'   => $rgt,
				':level' => $level,
				':path'  => $child_path,
				':id'    => $child_id,
			)
		);
	}

	return $counter;
}

echo "дерево пунктов меню перестроено: {$tree_total} значений\n";

echo "наполнение завершено\n";