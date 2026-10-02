<?php
/**
 * Подключение стилей и скриптов темы.
 *
 * Задание №8 ЛР4: пути до папок css и js прописаны через
 * get_template_directory_uri(), а не в page.php. Регистрация идёт
 * на хуке wp_enqueue_scripts, чтобы WordPress сам собрал теги <link>
 * и <script> в <head> и в конце <body>.
 *
 * Задание №10: через add_theme_support регистрируется область меню,
 * которую потом занимает wp_nav_menu() в header.php.
 */

// Задание №8 — стили и скрипты.
function gorod24_theme_scripts() {
	// Задание №7 — путь строится функцией get_template_directory_uri(),
	// поэтому тема работает из любого каталога wp-content/themes.
	// Задание №5: папка с вёрсткой (css, js, assets) перенесена в папку темы.
	// Задание №8: файлы подключаются не в page.php, а здесь, на хуке
	// wp_enqueue_scripts. Корневой style.css WordPress требует обязательно -
	// из него движок читает заголовок темы; правила лежат в нём же,
	// а @import тянет перенесённую вёрстку из css/style.css.
	wp_enqueue_style(
		'gorod24-style',
		get_template_directory_uri() . '/style.css',
		array(),
		'1.0.0'
	);

	wp_enqueue_script(
		'gorod24-main',
		get_template_directory_uri() . '/js/main.js',
		array(),
		'1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'gorod24_theme_scripts' );

// Задание №10 — объявление области меню «Верхнее меню».
// Само меню создаётся в админке (Внешний вид → Меню) либо скриптом
// setup-lab4.php; здесь только объявляем место, куда его выводить.
register_nav_menus( array(
	'primary' => __( 'Верхнее меню', 'gorod24' ),
	'footer'  => __( 'Меню подвала', 'gorod24' ),
) );

// Картинки-обложки: разрешаем выбирать миниатюру записи.
add_theme_support( 'post-thumbnails' );

// HTML5-разметка для стандартных элементов.
add_theme_support( 'html5', array(
	'search-form',
	'comment-form',
	'comment-list',
	'gallery',
	'caption',
) );
