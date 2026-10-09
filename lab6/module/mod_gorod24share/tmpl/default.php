<?php
/**
 * Шаблон модуля шеринга: заголовок + вертикальный список кнопок.
 */

defined('_JEXEC') or die;

/** @var object $params */
/** @var array  $networks */
$heading = $params->get('heading', 'Поделиться новостью');
?>
<div class="g24share" role="group" aria-label="<?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?>">
	<p class="g24share__heading"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></p>
	<ul class="g24share__list">
		<?php foreach ($networks as $net) : ?>
			<li class="g24share__item <?php echo $net['css']; ?>">
				<a href="<?php echo htmlspecialchars($net['link'], ENT_QUOTES, 'UTF-8'); ?>"
					target="_blank" rel="noopener noreferrer nofollow"
					title="<?php echo htmlspecialchars($net['name'], ENT_QUOTES, 'UTF-8'); ?>">
					<span class="g24share__icon" aria-hidden="true"><?php echo $net['icon']; ?></span>
					<span class="g24share__name"><?php echo htmlspecialchars($net['name'], ENT_QUOTES, 'UTF-8'); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
