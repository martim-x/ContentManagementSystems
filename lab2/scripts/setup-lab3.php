<?php
/**
 * ЛР №3: Работа с WP Elementor. Создание шаблона.
 * Запуск: docker compose run --rm -T wpcli eval-file - < scripts/setup-lab3.php
 */

echo "== ЛР3: Elementor лендинг «Город24» ==\n";

if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
	// WP-CLI eval-file грузит WP, но не все плагины инициализируются автоматически.
}

/**
 * Простой конструктор Elementor-структуры.
 */
function g24_section( $widgets, $column_size = 100, $settings = array() ) {
	return array(
		'id'       => g24_id(),
		'elType'   => 'section',
		'settings' => $settings,
		'elements' => array( array(
			'id'       => g24_id(),
			'elType'   => 'column',
			'settings' => array( '_column_size' => $column_size, '_inline_size' => null ),
			'elements' => $widgets,
			'isInner'  => false,
		) ),
		'isInner'  => false,
	);
}

function g24_multi_col( $cols ) {
	$columns = array();
	foreach ( $cols as $size => $widgets ) {
		$columns[] = array(
			'id'       => g24_id(),
			'elType'   => 'column',
			'settings' => array( '_column_size' => $size, '_inline_size' => null ),
			'elements' => $widgets,
			'isInner'  => false,
		);
	}
	return array(
		'id'       => g24_id(),
		'elType'   => 'section',
		'settings' => array(),
		'elements' => $columns,
		'isInner'  => false,
	);
}

function g24_widget( $type, $settings = array() ) {
	return array(
		'id'          => g24_id(),
		'elType'      => 'widget',
		'widgetType'  => $type,
		'settings'    => $settings,
		'elements'    => array(),
	);
}

function g24_id() {
	return dechex( rand( 0x100000, 0x7fffff ) );
}

/* ---------------- Задание 1: лендинг из 7 секций ---------------- */

$landing = array(

	// Секция 1: hero — главная новость.
	g24_section( array(
		g24_widget( 'heading', array(
			'title'   => 'Город24 — новостной портал Минска',
			'header_size' => 'h1',
			'align'   => 'center',
			'title_color' => '#1d4cbb',
		) ),
		g24_widget( 'text-editor', array(
			'editor'  => '<p>Локальная повестка 24/7, проверка фактов, без фейков. Политика, экономика, спорт, культура и жизнь районов — каждый день.</p>',
			'align'   => 'center',
			'text_color' => '#2c3e50',
		) ),
		g24_widget( 'button', array(
			'text'    => 'Читать новости',
			'link'    => array( 'url' => '/novosti/' ),
			'align'   => 'center',
			'button_text_color' => '#ffffff',
			'background_color'  => '#3563e9',
			'border_radius'     => array( 'unit' => 'px', 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8 ),
		) ),
	), 100, array( 'padding' => array( 'unit' => 'px', 'top' => 60, 'right' => 20, 'bottom' => 60, 'left' => 20 ) ) ),

	// Секция 2: рубрики — три колонки icon-box.
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Разделы портала', 'header_size' => 'h2', 'align' => 'center' ) ),
	) ),
	g24_multi_col( array(
		33 => array( g24_widget( 'icon-box', array(
			'selected_icon' => array( 'value' => 'fas fa-landmark', 'library' => 'fa-solid' ),
			'title_text'    => 'Политика',
			'description_text' => 'Бюджет, генплан, городской совет — решения власти простым языком.',
			'primary_color' => '#3563e9',
		) ) ),
		33 => array( g24_widget( 'icon-box', array(
			'selected_icon' => array( 'value' => 'fas fa-chart-line', 'library' => 'fa-solid' ),
			'title_text'    => 'Экономика',
			'description_text' => 'Цены, жильё, IT-рынок — что происходит с деньгами города.',
			'primary_color' => '#3563e9',
		) ) ),
		33 => array( g24_widget( 'icon-box', array(
			'selected_icon' => array( 'value' => 'fas fa-futbol', 'library' => 'fa-solid' ),
			'title_text'    => 'Спорт',
			'description_text' => 'Динамо, марафоны и всё, чем живёт спортивный Минск.',
			'primary_color' => '#3563e9',
		) ) ),
	) ),

	// Секция 3: главная новость — image-box.
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Главное сейчас', 'header_size' => 'h2', 'align' => 'center' ) ),
		g24_widget( 'image-box', array(
			'title_text' => 'В Минске откроется новая станция метро «Немига-2»',
			'description_text' => 'Первые поезда пустят в конце декабря. Станция станет пересадочной и свяжет центр города с новыми микрорайонами.',
			'position'   => 'top',
			'title_color' => '#1d4cbb',
		) ),
	) ),

	// Секция 4: культура — image галерея (media-carousel / gallery).
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Культура и события', 'header_size' => 'h2', 'align' => 'center' ) ),
		g24_widget( 'text-editor', array(
			'editor' => '<p>«Ночь музеев», опера «Кастусь Калиновский», открытые сцены в центре города — афиша выходных.</p>',
			'align'  => 'center',
		) ),
	) ),

	// Секция 5: прогресс/цифры — counters в три колонки.
	g24_multi_col( array(
		33 => array( g24_widget( 'counter', array(
			'title' => 'новостей в неделю', 'number' => 120, 'ending_number_color' => '#3563e9',
		) ) ),
		33 => array( g24_widget( 'counter', array(
			'title' => 'проверенных источников', 'number' => 48, 'ending_number_color' => '#3563e9',
		) ) ),
		33 => array( g24_widget( 'counter', array(
			'title' => 'лет с редакцией 24/7', 'number' => 7, 'ending_number_color' => '#3563e9',
		) ) ),
	) ),

	// Секция 6: цитата редакции — blockquote.
	g24_section( array(
		g24_widget( 'blockquote', array(
			'blockquote_content' => '«Мы пишем о Минске каждый день: каждая новость проходит редактуру и фактчекинг»',
			'quote_align' => 'center',
		) ),
	), 100, array( 'background_background' => 'classic', 'background_color' => '#e8eff6' ) ),

	// Секция 7: контакты — форма Contact Form 7.
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Предложить новость', 'header_size' => 'h2', 'align' => 'center' ) ),
		g24_widget( 'shortcode', array( 'shortcode' => '[contact-form-7 id="26" title="Обратная связь"]' ) ),
	), 100, array( 'padding' => array( 'unit' => 'px', 'top' => 40, 'right' => 20, 'bottom' => 40, 'left' => 20 ) ) ),
);

// Создаём страницу лендинга.
$landing_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Лендинг Город24',
	'post_name'    => 'landing',
) );
update_post_meta( $landing_id, '_elementor_data', wp_slash( wp_json_encode( $landing ) ) );
update_post_meta( $landing_id, '_elementor_version', '3.0.0' );
update_post_meta( $landing_id, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $landing_id, '_elementor_edit_mode', 'builder' );
echo "лендинг: страница {$landing_id}\n";

// Достаём ID формы Contact Form 7 динамически и правим шорткод.
$cf7 = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => 1 ) );
if ( $cf7 ) {
	$cf7_id = $cf7[0]->ID;
	update_post_meta( $landing_id, '_elementor_data', wp_slash( str_replace(
		'[contact-form-7 id="26" title="Обратная связь"]',
		"[contact-form-7 id=\"{$cf7_id}\" title=\"Обратная связь\"]",
		get_post_meta( $landing_id, '_elementor_data', true )
	) ) );
	echo "форма CF7: {$cf7_id}\n";
}

// Лендинг становится главной страницей.
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $landing_id );
echo "front page: {$landing_id}\n";

/* ---------------- Задание 2: шаблон Elementor + страница из шаблона ---------------- */

$template = array(
	// Обложка страницы-шаблона.
	g24_section( array(
		g24_widget( 'heading', array(
			'title'   => 'Страница «Город24»',
			'header_size' => 'h1',
			'align'   => 'center',
			'title_color' => '#ffffff',
		) ),
		g24_widget( 'text-editor', array(
			'editor' => '<p>Шаблон, сохранённый в Elementor: обложка, две колонки, кнопка действия.</p>',
			'align'  => 'center',
			'text_color' => '#ffffff',
		) ),
	), 100, array(
		'background_background' => 'classic',
		'background_color'      => '#1d3461',
		'padding' => array( 'unit' => 'px', 'top' => 80, 'right' => 20, 'bottom' => 80, 'left' => 20 ),
	) ),
	// Две колонки: контент + сайдбар-подсказка.
	g24_multi_col( array(
		60 => array(
			g24_widget( 'heading', array( 'title' => 'О разделе', 'header_size' => 'h2' ) ),
			g24_widget( 'text-editor', array(
				'editor' => '<p>Здесь размещается основной текст страницы: анонсы, описания, справочная информация для читателей портала.</p>',
			) ),
		),
		40 => array(
			g24_widget( 'icon-box', array(
				'selected_icon' => array( 'value' => 'fas fa-bullhorn', 'library' => 'fa-solid' ),
				'title_text'    => 'Реклама на портале',
				'description_text' => 'Баннеры и нативные статьи. Условия — по телефону редакции +375 (17) 305-24-24.',
			) ),
			g24_widget( 'button', array(
				'text'   => 'Связаться с редакцией',
				'link'   => array( 'url' => '/kontakty/' ),
				'button_text_color' => '#ffffff',
				'background_color'  => '#3563e9',
			) ),
		),
	) ),
);

$template_id = wp_insert_post( array(
	'post_type'    => 'elementor_library',
	'post_status'  => 'publish',
	'post_title'   => 'Шаблон страницы Город24',
) );
wp_set_object_terms( $template_id, 'page', 'elementor_library_type' );
update_post_meta( $template_id, '_elementor_data', wp_slash( wp_json_encode( $template ) ) );
update_post_meta( $template_id, '_elementor_version', '3.0.0' );
update_post_meta( $template_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $template_id, '_wp_page_template', 'elementor_header_footer' );
echo "шаблон elementor: {$template_id}\n";

// Новая страница, собранная из этого шаблона.
$page_from_tpl = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Реклама',
	'post_name'    => 'reklama',
	'post_content' => '',
) );
update_post_meta( $page_from_tpl, '_elementor_data', wp_slash( wp_json_encode( $template ) ) );
update_post_meta( $page_from_tpl, '_elementor_version', '3.0.0' );
update_post_meta( $page_from_tpl, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_from_tpl, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $page_from_tpl, '_elementor_template_id', $template_id );
echo "страница из шаблона: {$page_from_tpl}\n";

/* ---------------- Задание 4: встроенные блоки WordPress ---------------- */

// Изображение для демонстрации блоков (обложка).
$upload = wp_upload_dir();
$cover  = $upload['basedir'] . '/gen/site-icon.png';

$blocks_page = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Демонстрация блоков',
	'post_name'    => 'bloki',
	'post_content' => <<<HTML
<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":60} -->
<div class="wp-block-cover" style="min-height:60px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} --><h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">Демонстрация встроенных блоков WordPress</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center","textColor":"white"} --><p class="has-text-align-center has-white-color has-text-color">Группа «Медиа»: блок «Обложка»</p><!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->

<!-- wp:heading --><h2 class="wp-block-heading">Текстовые блоки</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Абзац (Paragraph) — базовый текстовый блок портала.</p><!-- /wp:paragraph -->
<!-- wp:quote --><blockquote class="wp-block-quote"><p>Цитата (Quote) — используем для заявлений спикеров мэрии.</p><cite>Редакция Город24</cite></blockquote><!-- /wp:quote -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Список (List) — пункт один</li><!-- /wp:list-item --><!-- wp:list-item --><li>Пункт два</li><!-- /wp:list-item --></ul><!-- /wp:list -->

<!-- wp:heading --><h2 class="wp-block-heading">Медиа-блоки</h2><!-- /wp:heading -->
<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="{$upload['baseurl']}/gen/post-politika.png" alt="Политика"/></figure><!-- /wp:image --><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="{$upload['baseurl']}/gen/post-sport.png" alt="Спорт"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->
<!-- wp:file --><div class="wp-block-file"><a href="{$upload['baseurl']}/gen/post-gorod.png">Скачать баннер (File)</a></div><!-- /wp:file -->

<!-- wp:heading --><h2 class="wp-block-heading">Дизайн-блоки</h2><!-- /wp:heading -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="/novosti/">Все новости (Buttons)</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><p>Колонка 1 (Columns)</p></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><p>Колонка 2 (Columns)</p></div><!-- /wp:column --></div><!-- /wp:columns -->
<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->

<!-- wp:heading --><h2 class="wp-block-heading">Виджеты</h2><!-- /wp:heading -->
<!-- wp:latest-posts {"postsToShow":3} /-->
<!-- wp:categories /-->

<!-- wp:heading --><h2 class="wp-block-heading">Встраивания (Embeds)</h2><!-- /wp:heading -->
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ"} --><figure class="wp-block-embed is-type-video"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=dQw4w9WgXcQ</div></figure><!-- /wp:embed -->
HTML
) );
echo "страница блоков: {$blocks_page}\n";

/* ---------------- Задание 5: плагины блоков ---------------- */
echo "плагины блоков ставятся через wp-cli (kadence-blocks, stackable)\n";

echo "== ЛР3 готово ==\n";
