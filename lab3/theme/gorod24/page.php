<?php
/**
 * Статическая страница («О нас», «Город» и т.д.).
 */
get_header();
?>
<main class="container">
	<?php while ( have_posts() ) : the_post(); ?>
		<nav class="breadcrumbs">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> / <?php the_title(); ?>
		</nav>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-card' ); ?>>
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
