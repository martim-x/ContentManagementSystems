<?php
/**
 * Город24: функции темы (ЛР №2 по CMS).
 */

function gorod24_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-logo', array(
		'flex-width'  => true,
		'flex-height' => true,
	) );
	add_theme_support( 'custom-background', array(
		'default-color' => 'f6f7f9',
	) );
	add_theme_support( 'custom-header', array(
		'default-text-color' => '2c3e50',
		'width'              => 1600,
		'height'             => 300,
		'flex-width'         => true,
		'flex-height'        => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	register_nav_menus( array(
		'primary' => 'Основное меню (шапка)',
		'footer'  => 'Меню в подвале',
	) );

	add_image_size( 'gorod24-card', 800, 480, true );
}
add_action( 'after_setup_theme', 'gorod24_setup' );

function gorod24_widgets_init() {
	register_sidebar( array(
		'name'          => 'Боковая колонка',
		'id'            => 'sidebar-1',
		'description'   => 'Виджеты в правой колонке страниц новостей и записей.',
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'gorod24_widgets_init' );

function gorod24_scripts() {
	wp_enqueue_style( 'gorod24-style', get_stylesheet_uri(), array(), '1.0' );
}
add_action( 'wp_enqueue_scripts', 'gorod24_scripts' );

function gorod24_excerpt_length( $length ) {
	return 22;
}
add_filter( 'excerpt_length', 'gorod24_excerpt_length' );

function gorod24_excerpt_more( $more ) {
	return '…';
}
add_filter( 'excerpt_more', 'gorod24_excerpt_more' );

/**
 * Логотип «Город24»: цифры в конце названия подсвечиваются голубым.
 */
function gorod24_logo_html() {
	$name = get_bloginfo( 'name' );
	if ( preg_match( '/^(.*?)(\d+)$/u', $name, $m ) ) {
		return esc_html( $m[1] ) . '<span class="dot">' . esc_html( $m[2] ) . '</span>';
	}
	return esc_html( $name );
}

/**
 * Резервное меню из страниц, пока меню не назначено в «Внешний вид → Меню».
 */
function gorod24_page_menu() {
	echo '<ul class="main-menu">';
	wp_list_pages( array( 'title_li' => '', 'depth' => 1 ) );
	echo '</ul>';
}
