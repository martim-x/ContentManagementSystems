<?php
/**
 * Модуль кнопок шеринга «Город24» (задание 6 ЛР6).
 *
 * Выводит ссылки «Поделиться» текущей страницей в Telegram, VK, X и
 * WhatsApp. Кнопки работают без внешних скриптов соцсетей: только
 * штатные share-адреса, поэтому страница не дёргает сторонние домены
 * и работает быстрее и приватнее, чем виджеты.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

$app    = Factory::getApplication();
$docUri = Uri::getInstance();

// Ссылка и заголовок текущей страницы. На статьях берём заголовок
// материала, на остальных — название сайта.
$url   = $docUri->toString(array('scheme', 'host', 'port', 'path'));
$query = $app->getInput()->get('option');
$title = $app->get('sitename');

if ($query === 'com_content' && $app->getInput()->get('view') === 'article') {
	// Заголовок документа уже собран роутером com_content.
	$title = $app->getDocument()->getTitle() ?: $title;
}

$encodedUrl   = rawurlencode($url);
$encodedTitle = rawurlencode($title);

$networks = array(
	array(
		'name' => 'Telegram',
		'link' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedTitle}",
		'css'  => 'g24share__item--telegram',
		'icon' => '✈',
	),
	array(
		'name' => 'ВКонтакте',
		'link' => "https://vk.com/share.php?url={$encodedUrl}&title={$encodedTitle}",
		'css'  => 'g24share__item--vk',
		'icon' => 'В',
	),
	array(
		'name' => 'X (Twitter)',
		'link' => "https://x.com/intent/post?url={$encodedUrl}&text={$encodedTitle}",
		'css'  => 'g24share__item--x',
		'icon' => '𝕏',
	),
	array(
		'name' => 'WhatsApp',
		'link' => "https://api.whatsapp.com/send?text={$encodedTitle}%20{$encodedUrl}",
		'css'  => 'g24share__item--whatsapp',
		'icon' => '✆',
	),
);

require ModuleHelper::getLayoutPath('mod_gorod24share', $params->get('layout', 'default'));
