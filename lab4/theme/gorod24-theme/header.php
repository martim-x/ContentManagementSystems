<?php
/**
 * Шапка сайта.
 *
 * Задание №9: в файл header.php перенесена вся шапка из вёрстки ЛР1,
 * а в <head> добавлен вызов wp_head() — без него WordPress не подключит
 * стили и не отдаст служебные теги.
 *
 * Задание №11: вместо жёстко прописанного <h1>Город24</h1> стоит
 * bloginfo( 'name' ), а описание — bloginfo( 'description' ). Теперь
 * название меняется в Настройки → Общие и подхватывается здесь само.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<?php
	// Задание №11 — динамический заголовок вкладки браузера.
	// Собирается из названия и описания сайта, заданных в консоли.
	?>
	<title><?php bloginfo( 'name' ); ?> — <?php bloginfo( 'description' ); ?></title>

	<link rel="stylesheet" href="<?php bloginfo( 'stylesheet_uri' ); ?>">

	<?php
	// Задание №9 — обязательный вызов в <head>.
	// Через него WordPress печатает подключённые в functions.php
	// стили, скрипты и служебные мета-теги.
	wp_head();
	?>
</head>
<body <?php body_class(); ?>>

<header class="site-header">
	<div class="header-inner">
		<?php
		// Задание №11 — динамическое название сайта: «Город24».
		// Стиль .logo и вложенный <span class="dot"> заданы в css/style.css.
		?>
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			// Разбиваем «Город24» на «Город» + «24», чтобы «24» подсветить.
			$gorod24_name = get_bloginfo( 'name' );
			$gorod24_base = preg_replace( '/\d+$/', '', $gorod24_name );
			$gorod24_num  = substr( $gorod24_name, strlen( $gorod24_base ) );

			echo esc_html( $gorod24_base );
			if ( '' !== $gorod24_num ) {
				echo '<span class="dot">' . esc_html( $gorod24_num ) . '</span>';
			}
			?>
		</a>

		<?php
		// Задание №10 — динамическое меню вместо четырёх <a href="...">.
		// Пункты берутся из админки (Внешний вид → Меню), активный подсвечивается.
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'container'       => 'nav',
			'container_class' => 'main-nav',
			'menu_class'      => 'main-nav-list',
			'depth'          => 2,
			'fallback_cb'    => false,
		) );
		?>
	</div>
</header>

<main>
