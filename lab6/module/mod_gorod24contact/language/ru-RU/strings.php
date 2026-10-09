<?php
/**
 * Языковые строки модуля mod_gorod24contact для регистрации в Joomla.
 *
 * Возвращает карту «константа => перевод». Файл используется скриптом
 * scripts/lab5-module.php, который кладёт строки в таблицу #__strings —
 * без неё Joomla не покажет метки полей формы на сайте.
 *
 * @package     mod_gorod24contact
 * @subpackage  Modules
 */

defined( '_JEXEC' ) or die;

return array(
	'MOD_GOROD24CONTACT'                        => 'Город24 — форма контакта',
	'MOD_GOROD24CONTACT_FIELD_TITLE'            => 'Заголовок модуля',
	'MOD_GOROD24CONTACT_FIELD_EMAIL'            => 'Email для писем',
	'MOD_GOROD24CONTACT_FIELD_COLOR'            => 'Цвет кнопки',
	'MOD_GOROD24CONTACT_FIELD_NAME'             => 'Имя пользователя',
	'MOD_GOROD24CONTACT_FIELD_EMAIL_LBL'        => 'Email',
	'MOD_GOROD24CONTACT_FIELD_TEXT'             => 'Текст обращения',
	'MOD_GOROD24CONTACT_FIELD_CAPTCHA'          => 'Решите пример, чтобы доказать, что вы человек',
	'MOD_GOROD24CONTACT_BUTTON_SEND'            => 'Отправить обращение',
	'MOD_GOROD24CONTACT_HINT_EMAIL'             => 'Письма приходят на %s',
	'MOD_GOROD24CONTACT_MAIL_SUBJECT'           => 'Обращение с сайта Город24',
	'MOD_GOROD24CONTACT_MAIL_BODY'              => "Обращение с сайта.\n\nИмя: %1\$s\nEmail: %2\$s\n\nТекст обращения:\n%3\$s",
	'MOD_GOROD24CONTACT_MSG_OK'                 => 'Спасибо! Обращение отправлено, мы ответим на указанный email.',
	'MOD_GOROD24CONTACT_MSG_BADTOKEN'           => 'Сессия устарела. Попробуйте отправить обращение ещё раз.',
	'MOD_GOROD24CONTACT_MSG_NEED_NAME'          => 'Укажите, как вас зовут.',
	'MOD_GOROD24CONTACT_MSG_BAD_EMAIL'          => 'Укажите корректный email.',
	'MOD_GOROD24CONTACT_MSG_NEED_TEXT'          => 'Напишите текст обращения.',
	'MOD_GOROD24CONTACT_MSG_BAD_CAPTCHA'        => 'Ответ на пример неверный. Попробуйте новый пример.',
	'MOD_GOROD24CONTACT_MSG_SEND_FAIL'          => 'Не удалось отправить письмо. Попробуйте позже.',
	'MOD_GOROD24CONTACT_MSG_SAVED'             => 'Обращение принято и сохранено. На стенде письма не отправляются: SMTP не настроен, текст обращения записан в administrator/logs/g24contact.log.',
);