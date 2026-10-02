<?php
/**
 * Главная страница: герой с главной новостью + сетка последних новостей.
 */
get_header();
?>
<main id="content">
	<?php
	$sticky_ids = array_map( 'intval', (array) get_option( 'sticky_posts' ) );
	$hero_args  = array(
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'has_password'   => false,
	);
	$hero = new WP_Query( array_merge( $sticky_ids ? array(
		'post__in' => $sticky_ids,
		'orderby'  => 'post__in',
	) : array( 'ignore_sticky_posts' => true ), $hero_args ) );
	if ( $hero->have_posts() ) :
		while ( $hero->have_posts() ) :
			$hero->the_post();
			$cats = get_the_category();
			$cat  = $cats ? $cats[0] : null;
			?>
			<section class="hero">
				<div class="hero-inner">
					<span class="hero-tag"><?php echo $cat ? esc_html( $cat->name ) : 'Главное'; ?></span>
					<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					<a class="btn" href="<?php the_permalink(); ?>">Читать полностью</a>
				</div>
			</section>
			<?php
		endwhile;
		wp_reset_postdata();
	endif;
	?>

	<div class="container">
		<div class="section-title">
			<h2>Последние новости</h2>
			<?php $news_page = get_option( 'page_for_posts' ); ?>
			<?php if ( $news_page ) : ?>
				<a class="all-link" href="<?php echo esc_url( get_permalink( $news_page ) ); ?>">Все новости →</a>
			<?php endif; ?>
		</div>

		<div class="news-grid">
			<?php
			$grid = new WP_Query( array(
				'posts_per_page' => 6,
				'offset'         => 1,
				'post_status'    => 'publish',
			) );
			while ( $grid->have_posts() ) :
				$grid->the_post();
				get_template_part( 'card' );
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</div>
</main>
<?php get_footer(); ?>
