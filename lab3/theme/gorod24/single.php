<?php
/**
 * Одна запись: карточка со стилями, метками и комментариями.
 */
get_header();
?>
<main class="container">
	<?php while ( have_posts() ) : the_post(); ?>
		<nav class="breadcrumbs">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> /
			<?php $cat = get_the_category(); ?>
			<?php if ( $cat ) : ?>
				<a href="<?php echo esc_url( get_category_link( $cat[0] ) ); ?>"><?php echo esc_html( $cat[0]->name ); ?></a> /
			<?php endif; ?>
			<?php the_title(); ?>
		</nav>

		<div class="content-wrap">
			<div class="content-main">
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-card' ); ?>>
					<?php if ( $cat ) : ?>
						<a class="category" href="<?php echo esc_url( get_category_link( $cat[0] ) ); ?>"><?php echo esc_html( $cat[0]->name ); ?></a>
					<?php endif; ?>

					<h1 class="entry-title"><?php the_title(); ?></h1>

					<p class="entry-meta">
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?>, <?php echo esc_html( get_the_time() ); ?></time>
						<span>· <?php the_author_posts_link(); ?></span>
						<?php if ( 'private' === get_post_status() ) : ?>
							<span class="privacy-badge">Приватная запись</span>
						<?php endif; ?>
					</p>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="thumb entry-thumb" style="background-image: url('<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'large' ) ); ?>');"></div>
					<?php endif; ?>

					<div class="entry-content">
						<?php the_content(); ?>
					</div>

					<?php echo get_the_tag_list( '<p class="entry-tags"><span>Метки:</span> ', ', ', '</p>' ); ?>
				</article>

				<?php comments_template(); ?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
