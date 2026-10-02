<?php
/**
 * Шаблон отдельной записи (новости).
 * Задание №9: шапка и подвал вызываются функциями get_header() и get_footer().
 */

get_header();
?>

<div class="container">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php endif; ?>

			<h1><?php the_title(); ?></h1>

			<p style="color: var(--text-muted);">
				<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
					<?php echo esc_html( get_the_date( 'd F Y, H:i' ) ); ?>
				</time>
			</p>

			<?php
			the_content();

			wp_link_pages( array(
				'before' => '<div class="page-links">Страницы: ',
				'after'  => '</div>',
			) );
			?>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();