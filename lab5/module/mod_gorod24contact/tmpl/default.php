<?php
/**
 * Разметка mod_gorod24contact.
 *
 * Свёртка/развёртка реализована нативным элементом <details>:
 * в свёрнутом состоянии видна только кнопка-суммари, в развёрнутом —
 * форма с тремя полями и капчей. JavaScript не используется.
 *
 * @var   string  $state          'success' | 'error' | ''
 * @var   string  $message        текст сообщения обратной связи
 * @var   array   $values         значения полей для повторного заполнения
 * @var   bool    $showFormOnLoad разворачивать ли форму при загрузке
 * @var   object  $params         параметры модуля
 */

defined( '_JEXEC' ) or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$state          = $state ?? '';
$message        = $message ?? '';
$values         = $values ?? array();
$showFormOnLoad = $showFormOnLoad ?? true;

$app     = Factory::getApplication();
$session = $app->getSession();

// Текущий адрес получаем через Uri: в Joomla 5 у приложения
// нет метода getCurrentUri(). Оставляем путь и строку запроса,
// чтобы форма возвращалась на ту же страницу со всеми параметрами.
$current_uri = Uri::getInstance()->toString( array( 'path', 'query' ) );

$color   = $params->get( 'theme_color', '#1d4ed8' );
$caption = $params->get( 'button_caption', 'Написать нам' );
$toEmail = $params->get( 'module_email', 'admin@gorod24.by' );

// Новая задача капчи на каждую загрузку формы. Ответ уходит в сессию.
$captcha  = g24contact_new_captcha();
$token    = $session->getFormToken();
?>
<div class="g24contact" style="--g24-color: <?php echo htmlspecialchars( $color, ENT_QUOTES, 'UTF-8' ); ?>;">

	<?php if ( '' !== $message ) : ?>
		<div class="g24contact__notice g24contact__notice--<?php echo htmlspecialchars( $state, ENT_QUOTES, 'UTF-8' ); ?>"
			 role="status">
			<?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?>
		</div>
	<?php endif; ?>

	<details class="g24contact__box" <?php echo $showFormOnLoad ? 'open' : ''; ?>>
		<summary class="g24contact__summary">
			<span class="g24contact__summary-icon" aria-hidden="true">✉</span>
			<span class="g24contact__summary-text"><?php echo htmlspecialchars( $caption, ENT_QUOTES, 'UTF-8' ); ?></span>
		</summary>

		<form class="g24contact__form" method="post" action="<?php echo htmlspecialchars( $current_uri, ENT_QUOTES, 'UTF-8' ); ?>#g24contact">
			<input type="hidden" name="g24contact_token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>" />
			<input type="hidden" name="g24contact_submit" value="1" />

			<p class="g24contact__row">
				<label for="g24contact-name"><?php echo g24contact_text( 'MOD_GOROD24CONTACT_FIELD_NAME' ); ?></label>
				<input type="text" id="g24contact-name" name="g24contact_name"
					   value="<?php echo htmlspecialchars( $values['name'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>"
					   required maxlength="60" autocomplete="name" />
			</p>

			<p class="g24contact__row">
				<label for="g24contact-email"><?php echo g24contact_text( 'MOD_GOROD24CONTACT_FIELD_EMAIL' ); ?></label>
				<input type="email" id="g24contact-email" name="g24contact_email"
					   value="<?php echo htmlspecialchars( $values['email'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>"
					   required maxlength="120" autocomplete="email" />
			</p>

			<p class="g24contact__row">
				<label for="g24contact-message"><?php echo g24contact_text( 'MOD_GOROD24CONTACT_FIELD_TEXT' ); ?></label>
				<textarea id="g24contact-message" name="g24contact_message" rows="4"
						  required maxlength="2000"><?php echo htmlspecialchars( $values['text'] ?? '', ENT_QUOTES, 'UTF-8' ); ?></textarea>
			</p>

			<?php // Задание 4: капча — сумма двух цифр. ?>
			<p class="g24contact__row g24contact__row--captcha">
				<label for="g24contact-captcha"><?php echo g24contact_text( 'MOD_GOROD24CONTACT_FIELD_CAPTCHA' ); ?></label>
				<span class="g24contact__captcha">
					<span class="g24contact__captcha-q"><?php echo htmlspecialchars( $captcha['question'], ENT_QUOTES, 'UTF-8' ); ?></span>
					<input type="text" id="g24contact-captcha" name="g24contact_captcha"
						   inputmode="numeric" pattern="[0-9]*" size="3" required
						   autocomplete="off" placeholder="?" />
				</span>
			</p>

			<p class="g24contact__actions">
				<button type="submit" class="g24contact__button">
					<?php echo g24contact_text( 'MOD_GOROD24CONTACT_BUTTON_SEND' ); ?>
				</button>
			</p>

			<p class="g24contact__hint">
				<?php
				// Шаблон подсказки содержит плейсхолдер %s. Используем
				// str_replace, а не vsprintf: если Joomla вернёт строку
				// уже без плейсхолдера, sprintf бросил бы исключение.
				echo str_replace(
					'%s',
					htmlspecialchars( $toEmail, ENT_QUOTES, 'UTF-8' ),
					g24contact_text( 'MOD_GOROD24CONTACT_HINT_EMAIL', 'Письма приходят на %s' )
				);
				?>
			</p>
		</form>
	</details>
</div>
