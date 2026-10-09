<?php
/**
 * Провайдер капчи g24math: пример «a + b = ?».
 *
 * Ответ хранится в сессии под ключом g24math.answer. display() генерирует
 * новый пример, checkAnswer() сравнивает ответ пользователя и сбрасывает
 * сохранённое значение — так один ответ нельзя использовать дважды.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Captcha\CaptchaProviderInterface;

class G24mathCaptcha implements CaptchaProviderInterface
{
	/** @inheritdoc */
	public function getName(): string
	{
		return 'g24math';
	}

	/** @inheritdoc */
	public function display(string $name = '', array $attributes = array()): string
	{
		// Две цифры от 1 до 9: сумма всегда двузначная и легко читается.
		$a = random_int(1, 9);
		$b = random_int(1, 9);

		$app  = Factory::getApplication();
		$session = $app->getSession();
		$session->set('g24math.answer', $a + $b);

		$id   = htmlspecialchars($attributes['id'] ?? 'g24math-answer', ENT_QUOTES, 'UTF-8');
		$cls  = htmlspecialchars($attributes['class'] ?? 'form-control', ENT_QUOTES, 'UTF-8');
		$req  = empty($attributes['required']) ? '' : ' required';

		return sprintf(
			'<div class="g24math" style="margin:8px 0;">'
			. '<label for="%s" style="display:block;font-size:.9rem;">'
			. 'Решите пример, чтобы доказать, что вы не робот: <b>%d + %d = ?</b>'
			. '</label>'
			. '<input type="text" inputmode="numeric" id="%s" name="%s" class="%s"%s '
			. 'placeholder="Ответ цифрами" autocomplete="off" />'
			. '</div>',
			$id, $a, $b, $id, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $cls, $req
		);
	}

	/** @inheritdoc */
	public function checkAnswer(?string $code = null): bool
	{
		if ($code === null || $code === '') {
			return false;
		}

		$session = Factory::getApplication()->getSession();
		$answer  = $session->get('g24math.answer');

		// Сбрасываем ответ до генерации нового примера.
		$session->clear('g24math.answer');

		return $answer !== null && (int) $code === (int) $answer;
	}

	/** @inheritdoc */
	public function setupField(\Joomla\CMS\Form\FormField $field, \SimpleXMLElement $element): void
	{
		// Нестандартных настроек поля не требуется.
	}
}
