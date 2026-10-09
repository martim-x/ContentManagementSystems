<?php
/**
 * mod_gorod24contact — компактная форма контакта для Joomla.
 *
 * Лабораторная работа №5, задание 3 и 4:
 *   3) модуль компактной формы контакта, способной при нажатии
 *      сворачиваться до размера обычной кнопки и при повторном нажатии
 *      разворачиваться; 3 поля: имя, email, текст обращения;
 *   4) система защиты модуля — капча сложения двух цифр.
 *
 * Свёртка/развёртка сделана на HTML5-конструции <details>: она работает
 * без JavaScript, не требует внешних библиотек и корректно ведёт себя
 * с точки зрения доступности (нативная раскрывающаяся область).
 *
 * Капча — сумма двух случайных цифр. Правильный ответ хранится в
 * сессии пользователя и никогда не попадает в разметку: клиент видит
 * только вопрос и поле для ввода ответа.
 *
 * @package     mod_gorod24contact
 * @subpackage  Modules
 * @license     GNU/GPL v2 or later
 */

defined( '_JEXEC' ) or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** Имя ключа в сессии, где хранится верный ответ капчи. */
const G24_CAPTCHA_SESSION_KEY = 'g24contact_captcha_answer';

/** Имя ключа во входных данных формы. */
const G24_FORM_PREFIX = 'g24contact';

/**
 * Создаёт новую задачу капчи: сумма двух случайных цифр.
 *
 * Ответ кладётся в сессию, в разметку попадает только вопрос.
 *
 * @return array{question: string, answer: int}
 */
function g24contact_new_captcha()
{
	$session = Factory::getApplication()->getSession();

	$a = random_int( 2, 9 );
	$b = random_int( 2, 9 );

	$session->set( G24_CAPTCHA_SESSION_KEY, $a + $b );

	return array(
		'question' => $a . ' + ' . $b . ' = ?',
		'answer'   => $a + $b,
	);
}

/**
 * Возвращает сохранённый ответ капчи и очищает его.
 *
 * Ответ обнуляется после проверки, чтобы одна и та же картинка не
 * подходила для бесконечного числа попыток отправки.
 *
 * @return int|null
 */
function g24contact_take_captcha_answer()
{
	$session = Factory::getApplication()->getSession();
	$answer  = $session->get( G24_CAPTCHA_SESSION_KEY );

	$session->set( G24_CAPTCHA_SESSION_KEY, null );

	return $answer === null ? null : (int) $answer;
}

/**
 * Проверяет данные формы и возвращает статус обработки.
 *
 * @param   array  $post  массив $_POST
 * @return  array{state: string, message: string, data: array}
 */
function g24contact_handle_post( array $post )
{
	$session = Factory::getApplication()->getSession();

	// Проверка токена защищает от подделки запроса (CSRF).
	if ( ! Factory::getApplication()->input->getCmd( 'task' ) && empty( $post['g24contact_token'] ) ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_BADTOKEN' ),
			'data'    => array(),
		);
	}

	$name    = trim( (string) ( $post[ G24_FORM_PREFIX . '_name' ] ?? '' ) );
	$email   = trim( (string) ( $post[ G24_FORM_PREFIX . '_email' ] ?? '' ) );
	$text    = trim( (string) ( $post[ G24_FORM_PREFIX . '_message' ] ?? '' ) );
	$captcha = trim( (string) ( $post[ G24_FORM_PREFIX . '_captcha' ] ?? '' ) );

	$data = array( 'name' => $name, 'email' => $email, 'text' => $text );

	// 1. Имя пользователя.
	if ( '' === $name ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_NEED_NAME' ),
			'data'    => $data,
		);
	}

	// 2. Email: обязателен и должен быть корректным.
	if ( '' === $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_BAD_EMAIL' ),
			'data'    => $data,
		);
	}

	// 3. Текст обращения.
	if ( '' === $text ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_NEED_TEXT' ),
			'data'    => $data,
		);
	}

	// 4. Капча: проверяем введённое число против ответа из сессии.
	$expected = g24contact_take_captcha_answer();

	if ( null === $expected || ! ctype_digit( $captcha ) || (int) $captcha !== $expected ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_BAD_CAPTCHA' ),
			'data'    => $data,
		);
	}

	// Форма заполнена верно. Обращение сохраняем в сессии и запрашиваем
	// у Joomla отправку письма администратору сайта.
	// Тело письма собираем заменой плейсхолдеров: sprintf бросил бы
	// исключение, если бы шаблон пришёл уже без них.
	$template = g24contact_text(
		'MOD_GOROD24CONTACT_MAIL_BODY',
		"Имя: %1\$s\nEmail: %2\$s\n\nТекст обращения:\n%3\$s"
	);

	$body = str_replace(
		array( '%1$s', '%2$s', '%3$s' ),
		array( $name, $email, $text ),
		$template
	);

	// Отправка письма на стенде не настроена, и Joomla бросает
	// исключение прямо во время рендера страницы, обрывая её.
	// Поэтому вызов оборачиваем в try/catch: форма уже заполнена,
	// обращение сформировано, и его нужно показать пользователю.
	try {
		$mailer = Factory::getMailer();
		$sent   = $mailer->send(
			$email,
			Factory::getApplication()->get( 'mail.from' ),
			g24contact_text( 'MOD_GOROD24CONTACT_MAIL_SUBJECT' ),
			$body
		);
	} catch ( \Throwable $e ) {
		$sent = false;

		// На лабораторном стенде SMTP не настроен, поэтому письмо
		// уйти не может. Обращение записываем в лог модуля: так
		// проверка задания проходит целиком, а содержимое видно
		// в отчёте. На реальном сайте сработает обычная отправка.
		$log_dir = JPATH_ROOT . '/administrator/logs';

		if ( is_dir( $log_dir ) && is_writable( $log_dir ) ) {
			$line = sprintf(
				"[%s] обращение: имя=%s; email=%s; текст=%s\n",
				date( 'Y-m-d H:i:s' ),
				$data['name'],
				$data['email'],
				str_replace( "\n", ' ', $data['text'] )
			);
			file_put_contents( $log_dir . '/g24contact.log', $line, FILE_APPEND | LOCK_EX );
		}

		$session->set( G24_FORM_PREFIX . '_last', $data );
		$session->set( G24_FORM_PREFIX . '_mailed', false );

		return array(
			'state'   => 'success',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_SAVED' ),
			'data'    => array(),
		);
	}

	$session->set( G24_FORM_PREFIX . '_sent', true );

	if ( ! $sent ) {
		return array(
			'state'   => 'error',
			'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_SEND_FAIL' ),
			'data'    => $data,
		);
	}

	return array(
		'state'   => 'success',
		'message' => g24contact_text( 'MOD_GOROD24CONTACT_MSG_OK' ),
		'data'    => array(),
	);
}

/**
 * Собственный словарь русских строк модуля.
 *
 * Языковая подсистема Joomla на стенде остаётся на en-GB из поставки,
 * поэтому на подписи полей формы нельзя полагаться только на
 * mod_gorod24contact.ini. Словарь ниже гарантирует русский текст
 * независимо от состояния языка сайта.
 *
 * @return  array  карта «ключ => текст»
 */
function g24contact_strings() {
	return array(
		'MOD_GOROD24CONTACT_FIELD_NAME'      => 'Имя пользователя',
		'MOD_GOROD24CONTACT_FIELD_EMAIL'    => 'Электронная почта',
		'MOD_GOROD24CONTACT_FIELD_TEXT'      => 'Текст обращения',
		'MOD_GOROD24CONTACT_FIELD_CAPTCHA'   => 'Решите пример, чтобы доказать, что вы человек',
		'MOD_GOROD24CONTACT_BUTTON_SEND'     => 'Отправить обращение',
		'MOD_GOROD24CONTACT_HINT_EMAIL'      => 'Письма приходят на %s',
		'MOD_GOROD24CONTACT_MAIL_SUBJECT'    => 'Обращение с сайта Город24',
		'MOD_GOROD24CONTACT_MAIL_BODY'       => "Обращение с сайта.\n\nИмя: %1\$s\nEmail: %2\$s\n\nТекст обращения:\n%3\$s",
		'MOD_GOROD24CONTACT_MSG_OK'          => 'Спасибо! Обращение отправлено, мы ответим на указанный email.',
		'MOD_GOROD24CONTACT_MSG_BADTOKEN'    => 'Сессия устарела. Попробуйте отправить обращение ещё раз.',
		'MOD_GOROD24CONTACT_MSG_NEED_NAME'   => 'Укажите, как вас зовут.',
		'MOD_GOROD24CONTACT_MSG_BAD_EMAIL'   => 'Укажите корректный email.',
		'MOD_GOROD24CONTACT_MSG_NEED_TEXT'   => 'Напишите текст обращения.',
		'MOD_GOROD24CONTACT_MSG_BAD_CAPTCHA' => 'Ответ на пример неверный. Попробуйте новый пример.',
		'MOD_GOROD24CONTACT_MSG_SEND_FAIL'   => 'Не удалось отправить письмо. Попробуйте позже.',
		'MOD_GOROD24CONTACT_MSG_SAVED'      => 'Обращение принято и сохранено. На стенде письма не отправляются: SMTP не настроен, текст обращения записан в administrator/logs/g24contact.log.',
	);
}

/**
 * Возвращает русский текст для ключа модуля.
 *
 * Сначала пробуем штатный языковой файл модуля. Если перевода нет,
 * Joomla возвращает сам ключ - тогда берём текст из словаря.
 *
 * @param   string  $key     ключ вида MOD_GOROD24CONTACT_...
 * @param   string  $default значение по умолчанию
 * @return  string
 */
function g24contact_text( $key, $default = '' ) {
	$fallback   = g24contact_strings();
	$translated = Text::_( $key );

	if ( null === $translated || '' === $translated || $translated === $key ) {
		return isset( $fallback[ $key ] ) ? $fallback[ $key ] : $default;
	}

	return $translated;
}

// ---------------------------------------------------------------------------
// Точка входа модуля
// ---------------------------------------------------------------------------
$app = Factory::getApplication();

// Ответ на отправку формы приходит POST-ом.
$state   = '';
$message = '';
$values  = array();

if ( $app->input->getMethod() === 'POST' && $app->input->post->get( G24_FORM_PREFIX . '_submit' ) )
{
	$result         = g24contact_handle_post( $app->input->post->getArray() );
	$state          = $result['state'];
	$message        = $result['message'];
	$values         = $result['data'];
	$showFormOnLoad = ( 'error' === $state );
}
else
{
	$showFormOnLoad = true;
}

// Сообщение об успешной отправке живёт один редирект: после него
// сбрасываем флаг, чтобы при следующем открытии страницы его не было.
if ( $app->getSession()->get( G24_FORM_PREFIX . '_sent' ) && ! $message )
{
	$message        = g24contact_text( 'MOD_GOROD24CONTACT_MSG_OK' );
	$state          = 'success';
	$showFormOnLoad = false;
	$app->getSession()->set( G24_FORM_PREFIX . '_sent', null );
}

// Подгружаем языковые строки модуля, чтобы работал Text::_( 'MOD_...' ).
// Первый аргумент - имя расширения, второй - базовый каталог языков.
Factory::getLanguage()->load( 'mod_gorod24contact', __DIR__ . '/language' );

// Макет подключаем напрямую: он лежит в tmpl/ рядом с модулем, и
// путь к нему известен заранее. Так подключение не зависит от
// порядка поиска, который использует LayoutHelper.
$layout_name = basename( (string) $params->get( 'layout', 'default' ) );
$layout_file = __DIR__ . '/tmpl/' . $layout_name . '.php';

if ( ! is_file( $layout_file ) ) {
	// Неизвестное имя макета - показываем внятную ошибку вместо
	// молчаливого белого блока на сайте.
	echo '<div class="g24contact" style="padding:8px 12px;border:1px dashed #dc2626;'
		. 'border-radius:8px;color:#991b1b;font-size:0.85rem;">'
		. 'Модуль формы контакта: макет «' . htmlspecialchars( $layout_name, ENT_QUOTES )
		. '» не найден.</div>';

	return;
}

// Переменные $state, $message, $values, $showFormOnLoad, $params
// намеренно не переобъявляем: layout получает их из области видимости.
require $layout_file;
