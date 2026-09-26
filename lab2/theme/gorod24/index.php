<?php
/**
 * Лента новостей (страница записей), рубрики, метки и поиск.
 */
get_header();
?>
<main class="container">
	<nav class="breadcrumbs">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> /
		<?php
		if ( is_search() ) {
			echo 'Поиск';
		} elseif ( is_archive() ) {
			the_archive_title();
		} else {
			single_post_title();
		}
		?>
	</nav>

	<h1>
		<?php
		if ( is_search() ) {
			echo 'Результаты поиска: ' . esc_html( get_search_query() );
		} elseif ( is_archive() ) {
			the_archive_title();
		} else {
			single_post_title();
		}
		?>
	</h1>
	<?php if ( is_archive() && get_the_archive_description() ) : ?>
		<div class="archive-desc"><?php the_archive_description(); ?></div>
	<?php endif; ?>

	<?php if ( is_home() && ! is_search() ) : ?>
		<div class="filter-row">
			<ul>
				<li><a href="<?php echo esc_url( $news_page_url = get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/' ) ); ?>">Все</a></li>
				<?php wp_list_categories( array( 'title_li' => '', 'depth' => 1 ) ); ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="content-wrap">
		<div class="content-main">
			<?php if ( have_posts() ) : ?>
				<div class="news-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'card' );
					endwhile;
					?>
				</div>
				<?php
				the_posts_pagination( array(
					'mid_size'  => 1,
					'prev_text' => '←',
					'next_text' => '→',
				) );
				?>
			<?php else : ?>
				<div class="entry-card">
					<h2>Ничего не найдено</h2>
					<p>Попробуйте изменить запрос или вернуться на <a href="<?php echo esc_url( home_url( '/' ) ); ?>">главную страницу</a>.</p>
				</div>
			<?php endif; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</main>
<?php get_footer(); ?>
