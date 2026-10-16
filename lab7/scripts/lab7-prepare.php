<?php
/**
 * Подготовка перенесённого сайта «Город24» к ЛР7.
 *
 * Сайт приходит из ЛР6 (дамп базы + файлы тома joomla_data), но стенд ЛР7
 * слушает другой порт, поэтому часть данных хранит абсолютные адреса и
 * требует правки. Скрипт делает три вещи:
 *
 *   1) заменяет в базе адрес стенда ЛР6 (localhost:8099) на адрес ЛР7
 *      (localhost:8102) — иначе ссылка «Читайте также», RSS-лента и
 *      sitemap.xml указывают на чужой порт;
 *   2) выключает многофакторную аутентификацию: в ЛР7 её нет, а второй
 *      шаг при входе мешает переключаться между деканом, старостой и
 *      студентом на защите;
 *   3) приводит пароль администратора к известному значению стенда ЛР7.
 *
 * Запуск (внутри контейнера web7):
 *   php /var/www/html/scripts/lab7-prepare.php
 *
 * Скрипт идемпотентен: повторный запуск ничего не портит.
 */

define( 'DB_HOST', getenv( 'JOOMLA_DB_HOST' ) ?: 'db' );
define( 'DB_NAME', getenv( 'JOOMLA_DB_NAME' ) ?: 'joomla' );
define( 'DB_USER', getenv( 'JOOMLA_DB_USER' ) ?: 'joomla' );
define( 'DB_PASS', getenv( 'JOOMLA_DB_PASSWORD' ) ?: 'joomla' );

/** Порт стенда ЛР6 и порт стенда ЛР7. */
const OLD_PORT = 'localhost:8099';
const NEW_PORT = 'localhost:8102';

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

/** Возвращает первое значение столбца по запросу. */
function db_one( $sql, $params = array(), $column = 'id' ) {
	$rows = db_query( $sql, $params );
	return $rows ? $rows[0][ $column ] : null;
}

echo "== Подготовка сайта ЛР7 ==\n";

// ---------------------------------------------------------------------------
// 1. Замена адреса стенда в текстовых полях базы.
//
// Таблицы и колонки перечислены явно: это те места, где Joomla хранит
// абсолютные адреса, заданные вручную. Массовая замена по всей базе
// затронула бы сериализованные структуры и сломала бы их длины.
// ---------------------------------------------------------------------------
$text_fields = array(
	'content'            => array( 'urls', 'fulltext', 'introtext', 'metadesc' ),
	'newsfeeds'          => array( 'link', 'params' ),
	'modules'            => array( 'params' ),

	'menu'               => array( 'params' ),
	'contact_details'    => array( 'misc' ),

);

$replaced_total = 0;

foreach ( $text_fields as $table => $columns ) {
	foreach ( $columns as $column ) {
		$rows = db_query(
			"SELECT id, `{$column}` AS val FROM `jos_{$table}` WHERE `{$column}` LIKE :needle",
			array( ':needle' => '%' . OLD_PORT . '%' )
		);

		foreach ( $rows as $row ) {
			$fixed = str_replace( OLD_PORT, NEW_PORT, (string) $row['val'] );

			db_query(
				"UPDATE `jos_{$table}` SET `{$column}` = :val WHERE id = :id",
				array( ':val' => $fixed, ':id' => $row['id'] )
			);

			$replaced_total++;
		}
	}
}

echo "заменено значений с адресом стенда: {$replaced_total} (" . OLD_PORT . ' -> ' . NEW_PORT . ")\n";

// ---------------------------------------------------------------------------
// 2. Многофакторная аутентификация: выключить.
//
// В ЛР7 MFA не требуется, а метод, оставшийся от ЛР6, требует код при
// каждом входе. Убираем и сам метод у пользователя, и плагин-провайдер.
// ---------------------------------------------------------------------------
$mfa_plugin = db_one(
	"SELECT extension_id FROM `jos_extensions`
	 WHERE type = 'plugin' AND folder = 'multifactorauth' AND element = 'fixed'",
	array(),
	'extension_id'
);

if ( $mfa_plugin ) {
	db_query(
		"UPDATE `jos_extensions` SET enabled = 0 WHERE extension_id = :id",
		array( ':id' => $mfa_plugin )
	);
	echo "плагин MFA «фиксированный код»: выключен\n";
}

$mfa_rows = db_query( "SELECT user_id FROM `jos_user_mfa`" );
db_query( "DELETE FROM `jos_user_mfa`" );
echo 'методов MFA удалено: ' . count( $mfa_rows ) . " (вход без второго шага)\n";

// ---------------------------------------------------------------------------
// 3. Администратор стенда ЛР7.
//
// Пароль и e-mail перезаписываются: после переноса в базе значение от ЛР6,
// а стенд ЛР7 документируется своим. Пароль хэшируется bcrypt — формат,
// который Joomla 5 принимает при входе.
// ---------------------------------------------------------------------------
$admin_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );

if ( ! $admin_id ) {
	echo "ОШИБКА: пользователь admin не найден — сайт перенесён не полностью\n";
	exit( 1 );
}

db_query(
	"UPDATE `jos_users` SET name = 'Администратор', email = 'admin@gorod24.by',
	 password = :hash, block = 0, activation = 0, params = '' WHERE id = :id",
	array(
		':hash' => password_hash( 'Gorod24!Admin2026', PASSWORD_BCRYPT ),
		':id'   => $admin_id,
	)
);
echo "пользователь admin: пароль стенда ЛР7 задан (ID {$admin_id})\n";

// То же для остальных учётных записей, перенесённых из ЛР6: у них тоже
// пароль от стенда ЛР6 — приводим всех к паролю ЛР7, чтобы демонстрация
// не зависела от прошлой лабы.
$others = db_query( "SELECT id, username FROM `jos_users` WHERE username <> 'admin'" );

foreach ( $others as $u ) {
	db_query(
		"UPDATE `jos_users` SET password = :hash WHERE id = :id",
		array(
			':hash' => password_hash( 'Gorod24!User2026', PASSWORD_BCRYPT ),
			':id'   => $u['id'],
		)
	);
}

echo 'прочим пользователям задан пароль Gorod24!User2026: ' . count( $others ) . "\n";

// ---------------------------------------------------------------------------
// 4. Самостоятельная регистрация (нужна по заданию ЛР7).
//
// Параметр новых учётных записей: регистрация разрешена, учётная запись
// создаётся заблокированной и активируется администратором из админки —
// это второй пункт задания («активировать новых пользователей»).
// ---------------------------------------------------------------------------
$row = db_query(
	"SELECT extension_id, params FROM `jos_extensions` WHERE name = 'com_users' AND element = 'com_users'"
);

if ( $row ) {
	$params = json_decode( $row[0]['params'], true );

	if ( ! is_array( $params ) ) {
		$params = array();
	}

	$params['allowUserRegistration'] = '1';
	// 'admin' — учётная запись создаётся и ждёт активации администратором.
	$params['new_usertype'] = '2';
	$params['useractivation'] = 'admin';

	db_query(
		"UPDATE `jos_extensions` SET params = :params WHERE extension_id = :eid",
		array(
			':params' => json_encode( $params, JSON_UNESCAPED_UNICODE ),
			':eid'    => $row[0]['extension_id'],
		)
	);
	echo "самостоятельная регистрация: разрешена, активация — администратором\n";
}

echo "подготовка завершена\n";
