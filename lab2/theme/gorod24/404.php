<?php
get_header();
?>
<main class="container">
	<article class="entry-card">
		<h1>404 — страница не найдена</h1>
		<p>Такой страницы на сайте нет. Вернитесь на <a href="<?php echo esc_url( home_url( '/' ) ); ?>">главную страницу</a> или воспользуйтесь поиском.</p>
	</article>
</main>
<?php get_footer(); ?>
