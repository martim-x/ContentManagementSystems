<?php
/**
 * Капча «сложение двух цифр» для Joomla 5 (плагин группы captcha).
 *
 * Задание 4 ЛР6: защита форм от спама. Провайдер g24math рисует пример
 * «a + b = ?», сумму хранит в сессии и проверяет при отправке формы.
 * После проверки ответ сбрасывается: повторная отправка того же ответа
 * без обновления страницы не проходит.
 *
 * В Joomla 5 ядро капча-плагинов вынесено из поставки: капча добавляется
 * плагином, реализующим CaptchaProviderInterface, и регистрирующим
 * провайдера в событии onCaptchaSetup (см. CaptchaRegistry::initRegistry).
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Captcha\CaptchaSetupEvent;
use Joomla\CMS\Captcha\CaptchaProviderInterface;

require_once __DIR__ . '/src/G24mathCaptcha.php';

class PlgCaptchaG24math extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Joomla вызывает этот метод при загрузке подписчиков плагина.
	 *
	 * @return  array  карта «имя события => метод»
	 */
	public static function getSubscribedEvents(): array
	{
		return array(
			'onCaptchaSetup' => 'onCaptchaSetup',
		);
	}

	/**
	 * Регистрирует провайдера g24math в реестре капч.
	 *
	 * @param   CaptchaSetupEvent  $event  событие инициализации реестра
	 *
	 * @return  void
	 */
	public function onCaptchaSetup(CaptchaSetupEvent $event): void
	{
		$event->getCaptchaRegistry()->add(new G24mathCaptcha);
	}
}
