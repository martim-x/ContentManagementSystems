<?php
/**
 * Главный шаблон темы: выводится, когда WordPress не нашёл более
 * подходящего (главная страница как блог, архивы, поиск, 404).
 *
 * Задание №9: шапка и подвал подключаются функциями get_header()
 * и get_footer() — разметка хранится в header.php и footer.php,
 * а не дублируется в каждом шаблоне.
 */

get_header();
?>

<div class="container">
	<?php if ( have_posts() ) : ?>

		<?php if ( is_home() && ! is_front_page() ) : ?>
			<h1><?php single_post_title(); ?></h1>
		<?php endif; ?>

		<div class="news-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$gorod24_cats = get_the_category();
				$gorod24_cat  = $gorod24_cats ? $gorod24_cats[0]->slug : 'gorod';
				?>
				<article class="news-card">
					<a href="<?php the_permalink(); ?>">
						<div class="thumb thumb-<?php echo esc_attr( $gorod24_cat ); ?>"></div>
						<?php if ( $gorod24_cats ) : ?>
							<span class="category"><?php echo esc_html( $gorod24_cats[0]->name ); ?></span>
						<?php endif; ?>
						<h3><?php the_title(); ?></h3>
						<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
							<?php echo esc_html( get_the_date( 'd F, H:i' ) ); ?>
						</time>
					</a>
				</article>
			<?php endwhile; ?>
		</div>

		<?php
		the_posts_pagination( array(
			'mid_size'  => 2,
			'prev_text' => '← Назад',
			'next_text' => 'Вперёд →',
		) );
		?>

	<?php else : ?>
		<h1>Ничего не найдено</h1>
		<p>По вашему запросу ничего не найдено. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Вернуться на главную</a>.</p>
	<?php endif; ?>
</div>

<?php
get_footer();