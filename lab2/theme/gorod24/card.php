<?php
/**
 * Карточка новости (используется в циклах главной, ленты и рубрик).
 */
$cats  = get_the_category();
$cat   = $cats ? $cats[0] : null;
$slug  = $cat ? $cat->slug : 'gorod';
$thumb = get_the_post_thumbnail_url( get_the_ID(), 'gorod24-card' );
?>
<article class="news-card">
	<a class="thumb thumb-<?php echo esc_attr( $slug ); ?>" href="<?php the_permalink(); ?>"<?php if ( $thumb ) : ?> style="background-image: url('<?php echo esc_url( $thumb ); ?>');"<?php endif; ?>></a>
	<?php if ( $cat ) : ?>
		<a class="category" href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
	<?php endif; ?>
	<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?>, <?php echo esc_html( get_the_time() ); ?></time>
</article>
