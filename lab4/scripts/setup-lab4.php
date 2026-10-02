<?php
/**
 * Наполнение сайта для ЛР4 — «Перенос вёрстки на WordPress».
 *
 * Закрывает задания, которые делаются через консоль WordPress:
 *   №4  — Настройки → Чтение: статическая страница и страница записей;
 *   №10 — меню в консоли (Внешний вид → Меню), выводится wp_nav_menu();
 *   №11 — название и описание сайта из Настроек → Общих;
 *   №12 — страницы, созданные в консоли, выводятся the_content().
 *
 * Запуск:
 *   docker compose run --rm -T wpcli wp eval-file - < scripts/setup-lab4.php
 *
 * Скрипт идемпотентен: повторный прогон обновляет существующие записи
 * по слагу и не создаёт дублей.
 */

// ---------------------------------------------------------------------------
// Вспомогательные функции
// ---------------------------------------------------------------------------

/**
 * Создаёт запись или обновляет существующую по слагу.
 * Без проверки повторный запуск плодил бы копии вида «glavnaya-2».
 */
function g24_post( $args ) {
	$slug = $args['post_name'];
	$type = $args['post_type'];

	$existing = get_posts( array(
		'post_type'      => $type,
		'name'           => $slug,
		'post_status'    => 'any',
		'posts_per_page' => 1,
	) );

	if ( $existing ) {
		$args['ID'] = $existing[0]->ID;
		return (int) wp_update_post( $args );
	}

	return (int) wp_insert_post( $args );
}

/** Возвращает ID записи по слагу, создавая её при отсутствии. */
function g24_get_id( $type, $slug ) {
	$found = get_posts( array(
		'post_type'      => $type,
		'name'           => $slug,
		'post_status'    => 'any',
		'posts_per_page' => 1,
	) );

	return $found ? (int) $found[0]->ID : 0;
}

/** Ищет рубрику по названию, создаёт при отсутствии. */
function g24_category( $name, $slug ) {
	$term = term_exists( $slug, 'category' );
	if ( $term ) {
		return (int) $term['term_id'];
	}
	$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Создаёт меню или очищает существующее и заполняет его заново.
 * wp_create_nav_menu() на повторном прогоне возвращает WP_Error,
 * поэтому сначала ищем меню по имени.
 */
function g24_menu( $name ) {
	$existing = wp_get_nav_menu_object( $name );

	if ( $existing ) {
		foreach ( wp_get_nav_menu_items( $existing->term_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
		return (int) $existing->term_id;
	}

	$created = wp_create_nav_menu( $name );
	return is_wp_error( $created ) ? 0 : (int) $created;
}

// ---------------------------------------------------------------------------
// Задание №11 — название и описание сайта
// ---------------------------------------------------------------------------

// Эти значения выводятся в header.php и footer.php через bloginfo(),
// поэтому меняются здесь и подхватываются на всём сайте автоматически.
update_option( 'blogname', 'Город24' );
update_option( 'blogdescription', 'Новостной портал Минска' );
update_option( 'timezone_string', 'Europe/Minsk' );
update_option( 'date_format', 'j F Y' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'blog_public', 1 );

echo "название: " . get_bloginfo( 'name' ) . " | описание: " . get_bloginfo( 'description' ) . "\n";

// ---------------------------------------------------------------------------
// Рубрики
// ---------------------------------------------------------------------------

$g24_cats = array();
foreach ( array(
	'Политика' => 'politics',
	'Экономика' => 'economy',
	'Спорт' => 'sport',
	'Культура' => 'culture',
	'Город' => 'gorod',
) as $g24_name => $g24_slug ) {
	$g24_cats[ $g24_slug ] = g24_category( $g24_name, $g24_slug );
}

// Записьм назначается конкретная рубрика, поэтому запасная
// «Без рубрики» на сайте не нужна - иначе в карточках появлялся бы
// класс thumb-uncategorized без соответствующих стилей.
$g24_default_cat = (int) get_option( 'default_category' );
if ( $g24_default_cat && ! in_array( $g24_default_cat, $g24_cats, true ) ) {
	wp_delete_term( $g24_default_cat, 'category' );
	echo "удалена запасная рубрика «Без рубрики»\n";
}
echo "рубрик: " . count( $g24_cats ) . "\n";

// ---------------------------------------------------------------------------
// Задание №12 — страницы из вёрстки ЛР1, созданные в консоли
// ---------------------------------------------------------------------------

// Главная. Содержимое выводится через the_content() в page.php,
// поэтому здесь именно текст из редактора, а не разметка шаблона.
$front_id = g24_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Новости Минска — каждый день',
	'post_name'    => 'glavnaya',
	'post_content' => <<<'HTML'
<section class="container">
	<p>Портал работает с 2019 года. Каждая новость проходит редактуру
	и фактчекинг — мы не публикуем непроверенную информацию.</p>

	<h2>Что мы пишем</h2>
	<p>Политика и городской совет, экономика и курсы валют, спорт и культура,
	жизнь районов города. Материалы выходят ежедневно, короткие новости —
	в течение часа, большие темы — с подробным разбором.</p>
</section>
HTML,
) );

$news_id = g24_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Новости',
	'post_name'    => 'novosti',
	'post_content' => <<<'HTML'
<section class="container">
	<p>Здесь публикуются все материалы портала: политика, экономика, спорт,
	культура и жизнь районов города.</p>
</section>
HTML,
) );

$about_id = g24_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'О нас',
	'post_name'    => 'o-nas',
	'post_content' => <<<'HTML'
<section class="container">
	<h2>Редакция</h2>
	<div class="team-grid">
		<div class="team-card">
			<div class="avatar"></div>
			<h3>Анна Соколова</h3>
			<p class="role">Главный редактор</p>
		</div>
		<div class="team-card">
			<div class="avatar"></div>
			<h3>Дмитрий Кравцов</h3>
			<p class="role">Корреспондент, политика</p>
		</div>
		<div class="team-card">
			<div class="avatar"></div>
			<h3>Мария Лабецкая</h3>
			<p class="role">Корреспондент, экономика</p>
		</div>
		<div class="team-card">
			<div class="avatar"></div>
			<h3>Павел Гринь</h3>
			<p class="role">Фотограф</p>
		</div>
	</div>

	<h2>Наша история</h2>
	<p>В 2019 году мы начали как студенческий блог о жизни города: писали о
	ремонте дворов и расписании автобусов. За семь лет выросли в редакцию
	из 12 человек — и по-прежнему пишем о том, что волнует минчан каждый день.</p>

	<h2>Частые вопросы</h2>
	<div class="row-list">
		<div class="row-item" style="padding: 16px 20px;">
			<div>
				<h3>Как предложить новость?</h3>
				<p>Написать на почту news@gorod24.by — редакция отвечает в течение часа.</p>
			</div>
		</div>
		<div class="row-item" style="padding: 16px 20px;">
			<div>
				<h3>Как оформить премиум-подписку?</h3>
				<p>Первый месяц бесплатно, далее — 9,90 BYN в месяц, отмена в любой момент.</p>
			</div>
		</div>
		<div class="row-item" style="padding: 16px 20px;">
			<div>
				<h3>Можно ли разместить рекламу?</h3>
				<p>Да: баннеры и нативные статьи. Условия — по телефону редакции.</p>
			</div>
		</div>
	</div>

	<h2>Контакты и обратная связь</h2>
	<p>Адрес редакции: г. Минск, ул. Немига, 5, офис 312.<br>
	Телефон: +375 (17) 305-24-24. Email: news@gorod24.by.</p>
</section>
HTML,
) );

$city_id = g24_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Город',
	'post_name'    => 'gorod',
	'post_content' => <<<'HTML'
<section class="container">
	<h2>Афиша выходных</h2>
	<p>Культурные события, концерты и выставки Минска — подборка на неделю.</p>

	<h2>Погода в Минске</h2>
	<p>Переменная облачность, +18…+21 °C, ветер слабый, восточный.</p>

	<h2>Транспорт</h2>
	<p>Метро работает с 5:30 до 0:50. Наземный транспорт — с 5:15 до 0:20.</p>
</section>
HTML,
) );

echo "страницы: glavnaya={$front_id}, novosti={$news_id}, o-nas={$about_id}, gorod={$city_id}\n";

// ---------------------------------------------------------------------------
// Записи — новости из вёрстки ЛР1
// ---------------------------------------------------------------------------

$g24_posts = array(
	array( 'Городской совет утвердил бюджет на 2027 год', 'politics', '2026-09-11 14:30' ),
	array( 'Курс: что будет с ценами на жильё этой осенью', 'economy', '2026-09-11 12:05' ),
	array( 'Минское «Динамо» вышло в плей-офф кубка', 'sport', '2026-09-10 22:40' ),
	array( 'Ночь музеев: центр города станет открытой сценой', 'culture', '2026-09-10 18:15' ),
	array( 'Ремонт ул. Ленина завершат на две недели раньше', 'gorod', '2026-09-10 11:00' ),
	array( 'В Минске открылся крупнейший IT-хаб на 2000 мест', 'economy', '2026-09-09 16:45' ),
	array( 'Обсуждение генплана: где появятся новые парки', 'politics', '2026-09-09 13:20' ),
	array( 'Марафон «Минск-24»: регистрация открыта', 'sport', '2026-09-08 10:30' ),
	array( 'Премьера сезона: опера «Кастусь Калиновский»', 'culture', '2026-09-08 09:15' ),
	array( 'В Минске откроется новая станция метро «Немига-2»', 'gorod', '2026-09-07 15:40' ),
);

$g24_post_count = 0;
foreach ( $g24_posts as $g24_item ) {
	list( $g24_title, $g24_cat, $g24_date ) = $g24_item;

	// Слаг генерируется из заголовка, поэтому повторный прогон
	// находит ту же запись и обновляет её.
	$g24_slug = sanitize_title( $g24_title );

	$g24_post_id = g24_post( array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_title'    => $g24_title,
		'post_name'     => $g24_slug,
		'post_date'     => $g24_date,
		'post_excerpt'  => 'Краткое содержание материала: ' . $g24_title . '.',
		'post_content'  => '<p>' . $g24_title . '. Материал подготовлен редакцией портала «Город24».'
			. ' Подробности — в полном тексте.</p>',
	) );

	// Рубрика выставляется только после создания записи.
	if ( $g24_post_id && ! empty( $g24_cats[ $g24_cat ] ) ) {
		wp_set_post_categories( $g24_post_id, array( $g24_cats[ $g24_cat ] ) );
	}

	$g24_post_count++;
}
echo "записей: {$g24_post_count}\n";

// Стандартная запись «Hello world!» из установки WordPress на лендинке
// не нужна — она попадала бы в цикл последних новостей.
$g24_hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
if ( $g24_hello ) {
	wp_delete_post( $g24_hello->ID, true );
	echo "удалена служебная запись «Hello world!»\n";
}

// Первая запись становится главной новостью на лендинге.
$g24_latest = get_posts( array(
	'post_type'   => 'post',
	'numberposts' => 1,
	'orderby'     => 'date',
	'order'       => 'DESC',
) );
if ( $g24_latest ) {
	echo "главная новость: " . $g24_latest[0]->post_title . "\n";
}

// ---------------------------------------------------------------------------
// Задание №4 — Настройки → Чтение
// ---------------------------------------------------------------------------

// «Статическая страница» вместо «Ваши последние записи».
// Главная — созданная в консоле страница, записи выводятся на отдельной.
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front_id );
update_option( 'page_for_posts', $news_id );

echo "чтение: show_on_front=" . get_option( 'show_on_front' )
	. ", главная=" . get_option( 'page_on_front' )
	. ", записи=" . get_option( 'page_for_posts' ) . "\n";

// ---------------------------------------------------------------------------
// Задание №10 — меню в консоли
// ---------------------------------------------------------------------------

$g24_primary = g24_menu( 'Верхнее меню' );
if ( $g24_primary ) {
	foreach ( array(
		array( $front_id, 'Главная' ),  // пункт меню подписан вручную, не заголовком
		array( $news_id, 'Новости' ),
		array( $about_id, 'О нас' ),
		array( $city_id, 'Город' ),
	) as $g24_item ) {
		wp_update_nav_menu_item( $g24_primary, 0, array(
			'menu-item-title'     => $g24_item[1],
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $g24_item[0],
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		) );
	}
	echo "меню «Верхнее меню»: ID {$g24_primary}, пунктов: "
		. count( wp_get_nav_menu_items( $g24_primary ) ) . "\n";
}

$g24_footer = g24_menu( 'Меню подвала' );
if ( $g24_footer ) {
	foreach ( array(
		array( $news_id, 'Новости' ),
		array( $about_id, 'О нас' ),
		array( $city_id, 'Город' ),
	) as $g24_item ) {
		wp_update_nav_menu_item( $g24_footer, 0, array(
			'menu-item-title'     => $g24_item[1],
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $g24_item[0],
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		) );
	}
	echo "меню «Меню подвала»: ID {$g24_footer}, пунктов: "
		. count( wp_get_nav_menu_items( $g24_footer ) ) . "\n";
}

// Назначение меню теме: связывает консольное меню с областями,
// объявленными в functions.php через register_nav_menus().
$g24_locations = get_theme_mod( 'nav_menu_locations' );
if ( ! is_array( $g24_locations ) ) {
	$g24_locations = array();
}
$g24_locations['primary'] = $g24_primary;
$g24_locations['footer']  = $g24_footer;
set_theme_mod( 'nav_menu_locations', $g24_locations );

echo "области меню: " . implode( ', ', array_keys( $g24_locations ) ) . "\n";

// Виджет «Последние новости» в подвале.
update_option( 'widget_gorod24_recent', array(
	2 => array(
		'title'  => 'Свежее',
		'number' => 5,
	),
	'_multiwidget' => 1,
) );
update_option( 'sidebars_widgets', array(
	'wp_inactive_widgets' => array(),
	'footer-1'             => array( 'gorod24_recent-2' ),
	'array_version'        => 3,
) );

echo "виджет «Свежее» назначен в область footer-1\n";

// ---------------------------------------------------------------------------
// Итог
// ---------------------------------------------------------------------------

echo "тема: " . wp_get_theme()->get( 'Name' ) . "\n";
echo "готово\n";