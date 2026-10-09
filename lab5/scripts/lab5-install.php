<?php
/**
 * Установка Joomla 5 для ЛР5 без интерактивного мастера.
 *
 * Запуск (внутри контейнера joomla):
 *   php /var/www/html/scripts-lab5-install.php
 *
 * Схема таблиц и справочные данные (группы, уровни доступа, расширения)
 * приходят из штатных файлов installation/sql/mysql — их импортирует
 * setup-lab5.sh. Здесь остаётся то, чего в SQL нет:
 *   1) configuration.php с параметрами подключения к базе;
 *   2) пользователь admin и его привязка к группе Super Users;
 *   3) настройки сайта из таблицы extensions (com_config);
 *   4) тип меню mainmenu.
 *
 * Скрипт идемпотентен: повторный запуск обновляет существующие записи.
 */

// Соединение с базой задаётся переменными окружения контейнера.
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

/** Возвращает первое значение столбца по идентификатору записи. */
function db_one( $sql, $params = array(), $column = 'id' ) {
	$rows = db_query( $sql, $params );
	return $rows ? $rows[0][ $column ] : null;
}

echo "== Установка Joomla 5 ==\n";

// ---------------------------------------------------------------------------
// 1. configuration.php
// ---------------------------------------------------------------------------
$config = <<<'PHP'
<?php
/**
 * Конфигурация Joomla, созданная скриптом lab5-install.php.
 * Значения совпадают с параметрами контейнеров docker-compose.yml.
 */
class JConfig
{
	public $offline = '0';
	public $debug = '1';
	// E_ALL без E_DEPRECATED: предупреждения о устаревших вызовах
	// в Joomla 5 не должны попадать в HTML страницы.
	public $error_reporting = 'default';
	public $sef = '1';
	public $sef_rewrite = '1';
	public $sef_suffix = '';
	public $unicodeslugs = '0';

	/*
	 * Joomla 5.4 читает параметры подключения под короткими именами
	 * host/user/password/db (см. libraries/src/Service/Provider/Database.php),
	 * а прежние dbhost/dbuser/dbname оставлены для совместимости со
	 * сторонним кодом и консольными командами. Нужны оба набора.
	 */
	public $dbtype = 'mysql';
	public $dbprefix = 'jos_';

	public $host = 'db';
	public $user = 'joomla';
	public $password = 'joomla';
	public $db = 'joomla';
	public $port = '3306';

	public $dbhost = 'db';
	public $dbport = '3306';
	public $dbname = 'joomla';
	public $dbuser = 'joomla';
	public $dbpassword = 'joomla';

	public $secret = 'Gorod24-LR5-Joomla-secret-key';

	public $log_path = '/var/www/html/administrator/logs';
	public $log_legacy = '1';
	public $tmp_path  = '/var/www/html/tmp';
	public $gzip      = '0';

	public $language = 'ru-RU';
	public $debug_lang = '0';

	// Имя сайта. Реестр приложения наполняется плагином
	// system/languagefilter из строки jos_languages текущего языка,
	// но если фильтр выключен, значение остаётся пустым, и тема
	// вызывает htmlspecialchars(null). Дублируем в configuration.php,
	// чтобы имя сайта не зависело от состояния плагина.
	public $sitename = 'Город24';

	// Часовой пояс и смещение: генератор RSS (RssRenderer) берёт
	// $app->get('offset') и передаёт его в DateTimeZone, поэтому
	// смещение нужно именно в формате +03:00, а не 'Europe/Minsk'.
	public $offset    = '+03:00';
	public $timezone  = 'Europe/Minsk';

	public $dbtype_pdo = 'mysql';
}

$config = new JConfig;
PHP;

file_put_contents( '/var/www/html/configuration.php', $config );
chmod( '/var/www/html/configuration.php', 0644 );
echo "configuration.php: создан\n";

// ---------------------------------------------------------------------------
// 2. Пользователь admin
// ---------------------------------------------------------------------------

// Группа Super Users и уровень доступа Super Users приходят из
// extensions.sql: у jos_usergroups уникальный ключ составной (parent_id, title),
// поэтому ON DUPLICATE KEY неприменим — ищем запись обычным запросом.
$super_group = db_one( "SELECT id FROM `jos_usergroups` WHERE title = 'Super Users'" );
$super_access = db_one( "SELECT id FROM `jos_viewlevels` WHERE title = 'Super Users'" );

if ( ! $super_group ) {
	echo "ОШИБКА: группа Super Users отсутствует — extensions.sql не импортирован\n";
	exit( 1 );
}

echo "группа Super Users: ID {$super_group}, уровень доступа: "
	. ( $super_access ?: 'Public' ) . "\n";

// Пароль хэшируется bcrypt: Joomla 5 принимает такой формат при входе.
$hash = password_hash( 'admin', PASSWORD_BCRYPT );

$existing = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );

if ( $existing ) {
	db_query(
		"UPDATE `jos_users` SET
		name = 'Администратор',
		email = 'admin@gorod24.by',
		password = :hash,
		block = 0,
		activation = 0,
		params = ''
	 WHERE id = :id",
		array( ':hash' => $hash, ':id' => $existing )
	);
	$user_id = $existing;
	echo "пользователь admin: обновлён (ID {$user_id})\n";
} else {
	// У jos_users поля params и registerDate объявлены NOT NULL без
	// значения по умолчанию, поэтому их нужно передавать явно.
	db_query(
		"INSERT INTO `jos_users`
		 (name, username, email, password, block, activation, registerDate, lastvisitDate, params)
		 VALUES ('Администратор', 'admin', 'admin@gorod24.by', :hash, 0, 0, NOW(), NOW(), '')",
		array( ':hash' => $hash )
	);
	$user_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );
	echo "пользователь admin: создан (ID {$user_id})\n";
}

// Привязка пользователя к группе.
db_query( "DELETE FROM `jos_user_usergroup_map` WHERE user_id = :uid", array( ':uid' => $user_id ) );
db_query(
	"INSERT INTO `jos_user_usergroup_map` (user_id, group_id) VALUES (:uid, :gid)",
	array( ':uid' => $user_id, ':gid' => $super_group )
);
echo "admin добавлен в группу Super Users\n";

// ---------------------------------------------------------------------------
// 3. Настройки сайта
// ---------------------------------------------------------------------------
// В Joomla 5 настройки сайта (Настройки → Общие) хранятся не отдельными
// строками, а одним JSON-объектом в поле params строки
// name = com_config, element = com_config (client_id = 1).
// Поэтому читаем существующий JSON, дописываем нужные ключи и пишем назад —
// так обновление не затирает остальные параметры.
$config_row = db_one(
	"SELECT extension_id, params FROM `jos_extensions`
	 WHERE name = 'com_config' AND element = 'com_config'",
	array(),
	'extension_id'
);

if ( ! $config_row ) {
	echo "ОШИБКА: строка настроек com_config не найдена — extensions.sql не импортирован\n";
	exit( 1 );
}

$row = db_query(
	"SELECT params FROM `jos_extensions` WHERE extension_id = :eid",
	array( ':eid' => $config_row )
);
$params = json_decode( $row[0]['params'], true );
if ( ! is_array( $params ) ) {
	$params = array();
}

$settings = array(
	// Часовой пояс нужен генератору RSS: без него RssRenderer
	// падает на DateTimeZone с пустой строкой.
	'offset'            => '+03:00',
	'timezone'          => 'Europe/Minsk',
	'sitename'         => 'Город24',
	'site_description' => 'Новостной портал Минска. Локальная повестка 24/7, проверка фактов, без фейков.',
	'MetaDesc'         => 'Новостной портал Минска: политика, экономика, спорт, культура.',
	'MetaKeywords'     => 'минск, новости, город24, политика, экономика, спорт',
	'MetaTitle'        => 'Город24 — новостной портал Минска',
	'footertext'       => '© 2026 ООО «Городской портал». Лицензия СМИ № 0009551',
	'language'         => 'ru-RU',
	'template'         => 'gorod24',
	'debug'            => '0',
	'sef'              => '1',
	'sef_rewrite'      => '1',
);

foreach ( $settings as $name => $value ) {
	$params[ $name ] = $value;
}

// Русский язык - основной для контента и интерфейса сайта.
$params['language']         = 'ru-RU';
$params['default_language'] = 'ru-RU';
$params['debug_lang']       = '0';

db_query(
	"UPDATE `jos_extensions` SET params = :params WHERE extension_id = :eid",
	array(
		':params' => json_encode( $params, JSON_UNESCAPED_UNICODE ),
		':eid'   => $config_row,
	)
);
echo "настроек записано: " . count( $settings )
	. " (JSON в params строки {$config_row})\n";

// Название и описание читаются обратно — проверяем, что JSON валиден.
$check = db_query( "SELECT params FROM `jos_extensions` WHERE extension_id = :eid", array( ':eid' => $config_row ) );
$back  = json_decode( $check[0]['params'], true );
echo "проверка: sitename = " . ( $back['sitename'] ?? 'НЕТ' ) . "\n";

// ---------------------------------------------------------------------------
// 4. Тип меню
// ---------------------------------------------------------------------------
$menu_type = db_one( "SELECT id FROM `jos_menu_types` WHERE menutype = 'mainmenu'" );

if ( $menu_type ) {
	db_query(
		"UPDATE `jos_menu_types` SET description = 'Основное меню' WHERE id = :id",
		array( ':id' => $menu_type )
	);
	echo "тип меню mainmenu: обновлён (ID {$menu_type})\n";
} else {
	// В jos_menu_types обязательны menutype и title; поля params
	// у этой таблицы нет вовсе, описание имеет значение по умолчанию.
	db_query(
		"INSERT INTO `jos_menu_types` (menutype, title, description)
		 VALUES ('mainmenu', 'mainmenu', 'Основное меню')"
	);
	$menu_type = db_one( "SELECT id FROM `jos_menu_types` WHERE menutype = 'mainmenu'" );
	echo "тип меню mainmenu: создан (ID {$menu_type})\n";
}

// ---------------------------------------------------------------------------
// 5. Завершение установки
// ---------------------------------------------------------------------------
// Пока каталог installation/ лежит на месте, Joomla считает себя
// неустановленной и редиректит все страницы на installation/index.php
// (условие в includes/framework.php). Мастер установки удаляет его
// в финальном шаге - делаем то же самое, сохранив копию в /tmp,
// чтобы скрипт оставался идемпотентным при повторном запуске.
if ( is_dir( '/var/www/html/installation' ) ) {
	$backup = '/tmp/joomla-installation-backup.tar';
	$rc = 0;
	exec( 'tar -cf ' . escapeshellarg( $backup ) . ' -C /var/www/html installation 2>/dev/null', $out, $rc );

	if ( 0 === $rc ) {
		exec( 'rm -rf /var/www/html/installation' );
		echo "каталог installation/ удалён, копия: {$backup}\n";
	} else {
		echo "ВНИМАНИЕ: не удалось создать резервную копию installation/, каталог оставлен\n";
	}
} else {
	echo "каталог installation/ уже удалён\n";
}

echo "установка завершена\n";