<?php
/**
 * Результаты поиска. Задание №9: шапка и подвал вызываются
 * функциями get_header() и get_footer().
 */
get_header();
?>
<div class="container">
	<h1>Поиск: <?php echo esc_html( get_search_query() ); ?></h1>

	<?php if ( have_posts() ) : ?>
		<div class="news-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="news-card">
					<a href="<?php the_permalink(); ?>">
						<h3><?php the_title(); ?></h3>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
	<?php else : ?>
		<p>Ничего не найдено. Попробуйте другой запрос.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
