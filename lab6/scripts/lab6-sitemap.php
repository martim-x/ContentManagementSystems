<?php
/**
 * Карта сайта (задание 3 ЛР6):
 *   1) /sitemap.xml — XML-карта для поисковых систем: главная, категории,
 *      статьи, страница ленты; lastmod — дата изменения материала;
 *   2) robots.txt — копия штатного + строка Sitemap;
 *   3) статья «Карта сайта» со списком ссылок на все материалы и
 *      пункт меню — HTML-карта для посетителей.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-sitemap.php
 *
 * Скрипт идемпотентен: sitemap.xml и статья-карта пересоздаются.
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

$base = 'http://localhost:8099';

echo "== Карта сайта ==\n";

// ---------------------------------------------------------------------------
// 1. sitemap.xml
// ---------------------------------------------------------------------------
$urls = array(
	array( 'loc' => $base . '/', 'priority' => '1.0' ),
	array( 'loc' => $base . '/novosti', 'priority' => '0.9' ),
	array( 'loc' => $base . '/rss-lenta', 'priority' => '0.5' ),
);

$articles = db_query(
	"SELECT c.id, c.alias, c.modified, cat.alias AS cat_alias
	 FROM `jos_content` c
	 JOIN `jos_categories` cat ON cat.id = c.catid
	 WHERE c.state = 1 AND c.access = 1
	 ORDER BY c.id"
);

foreach ( $articles as $a ) {
	$urls[] = array(
		'loc'      => "{$base}/{$a['cat_alias']}/{$a['alias']}",
		'priority' => '0.8',
		'lastmod'  => date( 'Y-m-d', strtotime( $a['modified'] ?: 'now' ) ),
	);
}

$xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ( $urls as $u ) {
	$xml .= "\t<url>\n\t\t<loc>" . htmlspecialchars( $u['loc'], ENT_XML1 ) . "</loc>\n";

	if ( ! empty( $u['lastmod'] ) ) {
		$xml .= "\t\t<lastmod>{$u['lastmod']}</lastmod>\n";
	}

	$xml .= "\t\t<priority>{$u['priority']}</priority>\n\t</url>\n";
}

$xml .= "</urlset>\n";

file_put_contents( '/var/www/html/sitemap.xml', $xml );
echo 'sitemap.xml: сгенерирован, URL в карте: ' . count( $urls ) . "\n";

// ---------------------------------------------------------------------------
// 2. robots.txt со строкой Sitemap.
// ---------------------------------------------------------------------------
$robots_path = '/var/www/html/robots.txt';

if ( ! is_file( $robots_path ) && is_file( '/var/www/html/robots.txt.dist' ) ) {
	copy( '/var/www/html/robots.txt.dist', $robots_path );
}

$robots = (string) @file_get_contents( $robots_path );

if ( false === strpos( $robots, 'Sitemap:' ) ) {
	$robots = rtrim( $robots ) . "\n\nSitemap: {$base}/sitemap.xml\n";
	file_put_contents( $robots_path, $robots );
	echo "robots.txt: добавлена строка Sitemap\n";
} else {
	echo "robots.txt: строка Sitemap уже есть\n";
}

// ---------------------------------------------------------------------------
// 3. Статья «Карта сайта» и пункт меню.
// ---------------------------------------------------------------------------
$user_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );
$cat_id  = db_one(
	"SELECT id FROM `jos_categories` WHERE alias = 'novosti' AND extension = 'com_content'"
);

$links  = "<ul>\n\t<li><a href=\"{$base}/\">Главная</a></li>\n";
$links .= "\t<li><a href=\"{$base}/novosti\">Новости</a></li>\n";
$links .= "\t<li><a href=\"{$base}/rss-lenta\">RSS-лента</a></li>\n";

foreach ( $articles as $a ) {
	$links .= "\t<li><a href=\"{$base}/{$a['cat_alias']}/{$a['alias']}\">" . htmlspecialchars( '', ENT_QUOTES ) . '</a></li>' . "\n";
}

$links .= '</ul>';

// Заголовки статей подставляем в подписи ссылок.
$titles = db_query( "SELECT id, title, alias FROM `jos_content` WHERE state = 1" );
foreach ( $titles as $t ) {
	$links = str_replace(
		"<a href=\"{$base}/novosti/{$t['alias']}\"></a>",
		"<a href=\"{$base}/novosti/{$t['alias']}\">" . htmlspecialchars( $t['title'], ENT_QUOTES, 'UTF-8' ) . '</a>',
		$links
	);
}

$map_id = db_one(
	"SELECT id FROM `jos_content` WHERE alias = 'karta-sajta'",
	array(),
	'id'
);

if ( $map_id ) {
	db_query(
		"UPDATE `jos_content`
		 SET introtext = :text, modified = NOW(), state = 1, access = 1, language = '*'
		 WHERE id = :id",
		array( ':text' => '<p>Все разделы и материалы сайта Город24.</p>' . $links, ':id' => $map_id )
	);
	echo "статья «Карта сайта»: обновлена (ID {$map_id})\n";
} else {
	db_query(
		"INSERT INTO `jos_content`
		 (asset_id, title, alias, introtext, `fulltext`, catid, state, access, created_by, created, modified,
		  images, urls, attribs, language, featured, metadesc, metakey, metadata)
		 VALUES (0, 'Карта сайта', 'karta-sajta', :text, '', :catid, 1, 1, :uid, NOW(), NOW(),
		         '', '', '{}', '*', 0,
		         'Карта сайта Город24: все разделы и материалы.', 'карта сайта, город24', '{}')",
		array( ':text' => '<p>Все разделы и материалы сайта Город24.</p>' . $links, ':catid' => $cat_id, ':uid' => $user_id )
	);
	$map_id = db_one( "SELECT id FROM `jos_content` WHERE alias = 'karta-sajta'", array(), 'id' );
	echo "статья «Карта сайта»: создана (ID {$map_id})\n";
}

// Пункт меню «Карта сайта» в mainmenu.
$menu_row = db_one(
	"SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu' AND link = :link",
	array( ':link' => 'index.php?option=com_content&view=article&id=' . $map_id ),
	'id'
);

$menu_params = '{"show_title":"1","link_titles":"","show_intro":"1","info_block_position":"","show_category":"0","show_author":"0","show_publish_date":"0","show_hits":"0"}';

if ( $menu_row ) {
	db_query(
		"UPDATE `jos_menu`
		 SET title = 'Карта сайта', published = 1, component_id = :cid, params = :params,
		     publish_up = NOW(), publish_down = NULL
		 WHERE id = :id",
		array( ':cid' => 19, ':params' => $menu_params, ':id' => $menu_row )
	);
	echo "пункт меню «Карта сайта»: обновлён (ID {$menu_row})\n";
} else {
	db_query(
		"INSERT INTO `jos_menu`
		 (menutype, title, alias, note, path, link, type, published, parent_id, level, language,
		  component_id, params, id, browserNav, access, client_id, home, checked_out, img,
		  template_style_id, publish_up, publish_down)
		 VALUES ('mainmenu', 'Карта сайта', 'karta-sajta', '', 'karta-sajta',
		         'index.php?option=com_content&view=article&id={$map_id}', 'component', 1, 1, 1, '*',
		         19, :params, 0, 0, 1, 0, 0, 0, '', 0, NOW(), NULL)",
		array( ':params' => $menu_params )
	);
	$menu_row = db_one(
		"SELECT id FROM `jos_menu` WHERE menutype = 'mainmenu' AND alias = 'karta-sajta'",
		array(),
		'id'
	);
	echo "пункт меню «Карта сайта»: создан (ID {$menu_row})\n";
}

echo "готово\n";
