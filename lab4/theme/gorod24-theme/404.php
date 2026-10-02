<?php
/**
 * Страница ошибки 404. Задание №9: шапка и подвал вызываются
 * функциями get_header() и get_footer().
 */
get_header();
?>
<div class="container">
	<h1>Страница не найдена</h1>
	<p>Такой страницы на портале нет. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Вернуться на главную</a>.</p>
</div>
<?php
get_footer();
