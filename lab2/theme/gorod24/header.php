<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="header-inner">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo gorod24_logo_html(); ?></a>
		<?php endif; ?>

		<nav class="main-nav" aria-label="Основное меню">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'main-menu',
				'depth'          => 1,
				'fallback_cb'    => 'gorod24_page_menu',
			) );
			?>
		</nav>
	</div>
</header>

<?php if ( get_header_image() ) : ?>
	<div class="header-banner">
		<img src="<?php echo esc_url( get_header_image() ); ?>" alt="<?php bloginfo( 'name' ); ?>">
	</div>
<?php endif; ?>
