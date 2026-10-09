<?php
/**
 * Безопасность сайта (задание 2 ЛР6: уменьшение вероятности
 * несанкционированного доступа):
 *   1) сильный пароль администратора вместо лабораторного «admin»;
 *   2) политика паролей: минимум 12 символов, цифры и спецсимволы;
 *   3) отключена саморегистрация на сайте (учётные записи создаёт админ);
 *   4) многофакторная аутентификация: включён метод «фиксированный код»
 *      и привязан к администратору — без второго фактора в админку не войти.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-security.php
 *
 * Код второго фактора: 246810 (лабораторный, задаётся здесь же).
 * Скрипт идемпотентен: повторный запуск пересоздаёт метод MFA.
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

echo "== Безопасность сайта ==\n";

// ---------------------------------------------------------------------------
// 1. Сильный пароль администратора.
// ---------------------------------------------------------------------------
$admin_id = db_one( "SELECT id FROM `jos_users` WHERE username = 'admin'" );

if ( ! $admin_id ) {
	echo "ОШИБКА: пользователь admin не найден\n";
	exit( 1 );
}

$hash = password_hash( 'Gorod24!Admin2026', PASSWORD_BCRYPT );

db_query(
	"UPDATE `jos_users` SET password = :hash, block = 0 WHERE id = :id",
	array( ':hash' => $hash, ':id' => $admin_id )
);
echo "пароль администратора: заменён на сильный (12+ символов, цифры, спецсимволы)\n";

// ---------------------------------------------------------------------------
// 2. Политика паролей и запрет саморегистрации (com_users).
// ---------------------------------------------------------------------------
$users_ext = db_one(
	"SELECT extension_id, params FROM `jos_extensions`
	 WHERE name = 'com_users' AND element = 'com_users' AND client_id = 1",
	array(),
	'extension_id'
);

if ( $users_ext ) {
	$row = db_query(
		"SELECT params FROM `jos_extensions` WHERE extension_id = :id",
		array( ':id' => $users_ext )
	);
	$params = json_decode( $row[0]['params'], true );

	if ( ! is_array( $params ) ) {
		$params = array();
	}

	$params['allowUserRegistration'] = '0';
	$params['minimum_length']        = '12';
	$params['minimum_integers']      = '1';
	$params['minimum_symbols']       = '1';
	$params['minimum_uppercase']     = '1';

	db_query(
		"UPDATE `jos_extensions` SET params = :params WHERE extension_id = :id",
		array( ':params' => json_encode( $params, JSON_UNESCAPED_UNICODE ), ':id' => $users_ext )
	);
	echo "политика паролей: 12+ символов, цифры, спецсимволы, заглавные\n";
	echo "саморегистрация: отключена (учётные записи создаёт администратор)\n";
}

// ---------------------------------------------------------------------------
// 3. Глобальная капча «сложение двух цифр» (задание 4: защита от спама).
//
// Поле капчи в формах Joomla берёт провайдера из глобальной настройки
// Default Captcha (configuration.php $captcha). Параметр контакта включает
// отображение, а провайдера подставляет глобальная настройка — ставим
// g24math сразу для всех форм (контакт и прочие публичные формы).
// ---------------------------------------------------------------------------
$config_path = '/var/www/html/configuration.php';
$config      = (string) file_get_contents( $config_path );

if ( false === strpos( $config, "public \$captcha = 'g24math'" ) ) {
	if ( preg_match( "/public \\\$captcha = '[^']*';/", $config ) ) {
		$config = preg_replace( "/public \\\$captcha = '[^']*';/", "public \$captcha = 'g24math';", $config );
	} else {
		$config = str_replace(
			"public \$sitename = 'Город24';",
			"public \$sitename = 'Город24';\n\tpublic \$captcha = 'g24math';",
			$config
		);
	}

	file_put_contents( $config_path, $config );
	chmod( $config_path, 0644 );
	echo "глобальная капча: g24math (configuration.php)\n";
} else {
	echo "глобальная капча: уже g24math\n";
}

// ---------------------------------------------------------------------------
// 4. Многофакторная аутентификация: метод «фиксированный код».
// ---------------------------------------------------------------------------
$fixed_plugin = db_one(
	"SELECT extension_id FROM `jos_extensions`
	 WHERE type = 'plugin' AND folder = 'multifactorauth' AND element = 'fixed'",
	array(),
	'extension_id'
);

if ( $fixed_plugin ) {
	db_query(
		"UPDATE `jos_extensions` SET enabled = 1 WHERE extension_id = :id",
		array( ':id' => $fixed_plugin )
	);
	echo "плагин MFA «фиксированный код»: включён\n";
}

$mfa_code = '246810';

// Существующий метод удаляем и создаём заново: скрипт идемпотентен.
db_query(
	"DELETE FROM `jos_user_mfa` WHERE user_id = :uid AND method = 'fixed'",
	array( ':uid' => $admin_id )
);

db_query(
	"INSERT INTO `jos_user_mfa`
	 (user_id, title, method, `default`, options, created_on, tries)
	 VALUES (:uid, 'Резервный код ЛР6', 'fixed', 1, :options, NOW(), 0)",
	array(
		':uid'     => $admin_id,
		':options' => json_encode( array( 'fixed_code' => $mfa_code ) ),
	)
);

// Второй фактор должен требоваться у администратора: Joomla требует
// MFA-код при входе, если у пользователя есть хотя бы один метод.
echo "MFA для admin: метод «фиксированный код» привязан (код: {$mfa_code})\n";

echo "готово\n";
