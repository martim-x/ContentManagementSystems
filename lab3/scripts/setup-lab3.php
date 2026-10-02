<?php
/**
 * ЛР №3: Работа с WP Elementor. Создание шаблона.
 * Запуск: docker compose run --rm -T wpcli eval-file - < scripts/setup-lab3.php
 *
 * Скрипт идемпотентен: перед созданием удаляет свои прошлые страницы по слагу,
 * поэтому повторный запуск не плодит дубли.
 */

echo "== ЛР3: Elementor лендинг «Город24» ==\n";

/**
 * Конструктор секции с одной колонкой.
 */
function g24_section( $widgets, $column_size = 100, $settings = array() ) {
	return g24_cols(
		array( array( $column_size, $widgets ) ),
		$settings
	);
}

/**
 * Конструктор секции из нескольких колонок.
 *
 * $cols — список пар [размер_колонки, виджеты]. Именно список, а не
 * ассоциативный массив: в PHP ключ 33, повторённый трижды, схлопывается в один
 * элемент, и три колонки превращаются в одну.
 */
function g24_cols( $cols, $settings = array() ) {
	$sizes = array();
	$columns = array();
	foreach ( $cols as $col ) {
		list( $size, $widgets ) = $col;
		$sizes[] = (string) $size;
		$columns[] = array(
			'id'       => g24_id(),
			'elType'   => 'column',
			'settings' => array( '_column_size' => $size, '_inline_size' => null ),
			'elements' => $widgets,
			'isInner'  => false,
		);
	}
	$settings['structure'] = implode( '', $sizes );
	return array(
		'id'       => g24_id(),
		'elType'   => 'section',
		'settings' => $settings,
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

/**
 * Удаляет страницу/запись предыдущего прогона, чтобы не было дублей.
 *
 * Ищет и по слагу, и по точному заголовку: WordPress генерирует слаг из
 * заголовка, поэтому старый прогон мог оставить запись с кириллическим слагом.
 */
function g24_reset( $post_type, $slug, $title = '' ) {
	$found = array();

	$by_slug = get_posts( array(
		'post_type'      => $post_type,
		'name'           => $slug,
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );
	foreach ( $by_slug as $p ) {
		$found[ $p->ID ] = $p;
	}

	if ( $title !== '' ) {
		$by_title = get_posts( array(
			'post_type'      => $post_type,
			'title'           => $title,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );
		foreach ( $by_title as $p ) {
			$found[ $p->ID ] = $p;
		}
	}

	foreach ( $found as $p ) {
		wp_delete_post( $p->ID, true );
	}
	return count( $found );
}

function g24_counter( $title, $number ) {
	return g24_widget( 'counter', array(
		'starting_number'     => 0,
		'ending_number'       => $number,
		'title'               => $title,
		'ending_number_color' => '#3563e9',
		'duration'            => 1200,
	) );
}

/* ---------------- Задание 1: лендинг ---------------- */

$landing = array(

	// Секция 1: hero — одна колонка.
	g24_section( array(
		g24_widget( 'heading', array(
			'title'       => 'Город24 — новостной портал Минска',
			'header_size' => 'h1',
			'align'       => 'center',
			'title_color' => '#1d4cbb',
		) ),
		g24_widget( 'text-editor', array(
			'editor'     => '<p>Локальная повестка 24/7, проверка фактов, без фейков. Политика, экономика, спорт, культура и жизнь районов — каждый день.</p>',
			'align'      => 'center',
			'text_color' => '#2c3e50',
		) ),
		g24_widget( 'button', array(
			'text'              => 'Читать новости',
			'link'              => array( 'url' => '/novosti/' ),
			'align'             => 'center',
			'button_text_color' => '#ffffff',
			'background_color'  => '#3563e9',
			'border_radius'     => array( 'unit' => 'px', 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8 ),
		) ),
	), 100, array(
		'padding'              => array( 'unit' => 'px', 'top' => 60, 'right' => 20, 'bottom' => 60, 'left' => 20 ),
		'background_background' => 'classic',
		'background_color'      => '#f4f7fb',
	) ),

	// Секция 2: заголовок группы рубрик — одна колонка.
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Разделы портала', 'header_size' => 'h2', 'align' => 'center' ) ),
	) ),

	// Секция 3: три колонки icon-box.
	g24_cols( array(
		array( 33, array( g24_widget( 'icon-box', array(
			'selected_icon'    => array( 'value' => 'fas fa-landmark', 'library' => 'fa-solid' ),
			'title_text'       => 'Политика',
			'description_text' => 'Бюджет, генплан, городской совет — решения власти простым языком.',
			'primary_color'    => '#3563e9',
		) ) ) ),
		array( 33, array( g24_widget( 'icon-box', array(
			'selected_icon'    => array( 'value' => 'fas fa-chart-line', 'library' => 'fa-solid' ),
			'title_text'       => 'Экономика',
			'description_text' => 'Цены, жильё, IT-рынок — что происходит с деньгами города.',
			'primary_color'    => '#3563e9',
		) ) ) ),
		array( 33, array( g24_widget( 'icon-box', array(
			'selected_icon'    => array( 'value' => 'fas fa-futbol', 'library' => 'fa-solid' ),
			'title_text'       => 'Спорт',
			'description_text' => 'Динамо, марафоны и всё, чем живёт спортивный Минск.',
			'primary_color'    => '#3563e9',
		) ) ) ),
	) ),

	// Секция 4: главная новость — две колонки (картинка + текст).
	g24_cols( array(
		array( 50, array( g24_widget( 'image-box', array(
			'image'           => '{IMG_NEWMETRO}',
			'title_text'      => 'Немига-2',
			'description_text' => 'Станция метро, которая свяжет центр с новыми микрорайонами.',
			'position'        => 'top',
		) ) ) ),
		array( 50, array(
			g24_widget( 'heading', array( 'title' => 'Главное сейчас', 'header_size' => 'h2', 'align' => 'left' ) ),
			g24_widget( 'text-editor', array(
				'editor' => '<p>В Минске откроется новая станция метро «Немига-2». Первые поезда пустят в конце декабря: станция станет пересадочной и свяжет центр города с новыми микрорайонами.</p>',
				'align'  => 'left',
			) ),
			g24_widget( 'button', array(
				'text'             => 'Читать новость',
				'link'             => array( 'url' => '/novosti/' ),
				'button_text_color' => '#ffffff',
				'background_color'  => '#3563e9',
			) ),
		) ),
	), array(
		'background_background' => 'classic',
		'background_color'      => '#e8eff6',
	) ),

	// Секция 5: культура — одна колонка.
	g24_section( array(
		g24_widget( 'heading', array( 'title' => 'Культура и события', 'header_size' => 'h2', 'align' => 'center' ) ),
		g24_widget( 'text-editor', array(
			'editor' => '<p>«Ночь музеев», опера «Кастусь Калиновский», открытые сцены в центре города — афиша выходных в одном месте.</p>',
			'align'  => 'center',
		) ),
	) ),

	// Секция 6: три колонки со счётчиками.
	g24_cols( array(
		array( 33, array( g24_counter( 'новостей в неделю', 120 ) ) ),
		array( 33, array( g24_counter( 'проверенных источников', 48 ) ) ),
		array( 33, array( g24_counter( 'лет с редакцией 24/7', 7 ) ) ),
	) ),

	// Секция 7: цитата редакции — одна колонка на цветном фоне.
	// Виджет blockquote в Elementor 4.x входит в PRO и без лицензии
	// подменяется пустой промо-заглушкой, поэтому цитата оформлена
	// бесплатным виджетом toggle (раскрывающийся блок).
	g24_section( array(
		g24_widget( 'toggle', array(
			'selected_icon'   => array( 'value' => 'fas fa-quote-left', 'library' => 'fa-solid' ),
			// Заголовок и содержимое лежат внутри повторителя tabs:
			// на верхнем уровне виджета таких контролов нет, поэтому без них
			// Elementor подставляет собственные значения «Toggle #1».
			'tabs'             => array(
				array(
					'tab_title'   => '«Мы пишем о Минске каждый день: каждая новость проходит редактуру и фактчекинг»',
					'tab_content' => '<p>Принципы редакции Город24: проверка источника, ссылка на первоисточник, отметка автора и даты публикации.</p>',
				),
			),
			'title_html_tag'  => 'h3',
			'icon_align'      => 'left',
			'title_color'     => '#1d4cbb',
			'content_color'   => '#2c3e50',
		) ),
	), 100, array(
		'background_background' => 'classic',
		'background_color'      => '#e8eff6',
	) ),

	// Секция 8: форма обратной связи — две колонки.
	g24_cols( array(
		array( 40, array(
			g24_widget( 'heading', array( 'title' => 'Предложить новость', 'header_size' => 'h2', 'align' => 'left' ) ),
			g24_widget( 'text-editor', array(
				'editor' => '<p>Присылайте идеи и жалобы — редакция свяжется с вами в течение рабочего дня.</p>',
			) ),
		) ),
		array( 60, array( g24_widget( 'shortcode', array( 'shortcode' => '[contact-form-7 id="{CF7_ID}" title="Обратная связь"]' ) ) ) ),
	), array(
		'padding' => array( 'unit' => 'px', 'top' => 40, 'right' => 20, 'bottom' => 40, 'left' => 20 ),
	) ),
);

// Подставляем реальные ID и URL перед сохранением.
$upload = wp_upload_dir();
$cf7    = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'posts_per_page' => 1 ) );
$cf7_id = $cf7 ? $cf7[0]->ID : 0;
$img    = $upload['baseurl'] . '/gen/post-gorod.png';

$landing_json = str_replace(
	array( '{IMG_NEWMETRO}', '{CF7_ID}' ),
	array( $img, $cf7_id ),
	wp_json_encode( $landing )
);
$landing = json_decode( $landing_json, true );

g24_reset( 'page', 'landing', 'Лендинг Город24' );
$landing_id = wp_insert_post( array(
	'post_type'   => 'page',
	'post_status' => 'publish',
	'post_title'  => 'Лендинг Город24',
	'post_name'   => 'landing',
) );
update_post_meta( $landing_id, '_elementor_data', wp_slash( wp_json_encode( $landing ) ) );
update_post_meta( $landing_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $landing_id, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $landing_id, '_elementor_edit_mode', 'builder' );
echo "лендинг: страница {$landing_id}, секций " . count( $landing ) . ", форма CF7 {$cf7_id}\n";

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $landing_id );
echo "front page: {$landing_id}\n";

/* ---------------- Задание 2: шаблон Elementor + страница из шаблона ---------------- */

$template = array(
	// Обложка — одна колонка, тёмный фон.
	g24_section( array(
		g24_widget( 'heading', array(
			'title'       => 'Страница «Город24»',
			'header_size' => 'h1',
			'align'       => 'center',
			'title_color' => '#ffffff',
		) ),
		g24_widget( 'text-editor', array(
			'editor'     => '<p>Шаблон, сохранённый в Elementor: обложка, две колонки, кнопка действия.</p>',
			'align'      => 'center',
			'text_color' => '#ffffff',
		) ),
	), 100, array(
		'background_background' => 'classic',
		'background_color'      => '#1d3461',
		'padding'               => array( 'unit' => 'px', 'top' => 80, 'right' => 20, 'bottom' => 80, 'left' => 20 ),
	) ),
	// Две колонки: контент + сайдбар-подсказка.
	g24_cols( array(
		array( 60, array(
			g24_widget( 'heading', array( 'title' => 'О разделе', 'header_size' => 'h2' ) ),
			g24_widget( 'text-editor', array(
				'editor' => '<p>Здесь размещается основной текст страницы: анонсы, описания, справочная информация для читателей портала.</p>',
			) ),
		) ),
		array( 40, array(
			g24_widget( 'icon-box', array(
				'selected_icon'    => array( 'value' => 'fas fa-bullhorn', 'library' => 'fa-solid' ),
				'title_text'       => 'Реклама на портале',
				'description_text' => 'Баннеры и нативные статьи. Условия — по телефону редакции +375 (17) 305-24-24.',
			) ),
			g24_widget( 'button', array(
				'text'              => 'Связаться с редакцией',
				'link'              => array( 'url' => '/kontakty/' ),
				'button_text_color' => '#ffffff',
				'background_color'  => '#3563e9',
			) ),
		) ),
	) ),
);

g24_reset( 'elementor_library', 'shablon-stranicy-gorod24', 'Шаблон страницы Город24' );
$template_id = wp_insert_post( array(
	'post_type'   => 'elementor_library',
	'post_status' => 'publish',
	'post_title'  => 'Шаблон страницы Город24',
	'post_name'   => 'shablon-stranicy-gorod24',
) );
wp_set_object_terms( $template_id, 'page', 'elementor_library_type' );
update_post_meta( $template_id, '_elementor_data', wp_slash( wp_json_encode( $template ) ) );
update_post_meta( $template_id, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $template_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $template_id, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $template_id, '_elementor_template_type', 'page' );
echo "шаблон elementor: {$template_id}\n";

// Новая страница, собранная из этого шаблона.
g24_reset( 'page', 'reklama', 'Реклама' );
$page_from_tpl = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Реклама',
	'post_name'    => 'reklama',
	'post_content' => '',
) );
update_post_meta( $page_from_tpl, '_elementor_data', wp_slash( wp_json_encode( $template ) ) );
update_post_meta( $page_from_tpl, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $page_from_tpl, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_from_tpl, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $page_from_tpl, '_elementor_template_id', $template_id );
echo "страница из шаблона: {$page_from_tpl}\n";

/* ---------------- Задание 4: встроенные блоки WordPress ---------------- */

// Синхронизируемый шаблон (группа «Шаблоны»): создаём переиспользуемый блок.
g24_reset( 'wp_block', 'blok-portala-gorod24', 'Блок портала Город24' );
$pattern_id = wp_insert_post( array(
	'post_type'    => 'wp_block',
	'post_status'  => 'publish',
	'post_title'   => 'Блок портала Город24',
	'post_name'    => 'blok-portala-gorod24',
	'post_content' => '<!-- wp:paragraph --><p>Переиспользуемый блок редакции: он вставлен на страницу демонстрации блоков и остаётся доступным в редакторе.</p><!-- /wp:paragraph -->',
) );
echo "синхронизированный шаблон: {$pattern_id}\n";

$baseurl = $upload['baseurl'];

$blocks_content = <<<HTML
<!-- wp:paragraph --><p>Ниже — встроенные блоки редактора WordPress по одной штуке из каждой группы: Текст, Медиа, Дизайн, Виджеты, Тема, Встраивания, Шаблоны.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Текст»</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Абзац (Paragraph) — базовый текстовый блок портала.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Заголовок третьего уровня (Heading)</h3><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Список (List) — пункт один</li><!-- /wp:list-item --><!-- wp:list-item --><li>Пункт два</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- wp:quote --><blockquote class="wp-block-quote"><p>Цитата (Quote) — для заявлений спикеров мэрии.</p><cite>Редакция Город24</cite></blockquote><!-- /wp:quote -->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Медиа»</h2><!-- /wp:heading -->
<!-- wp:cover {"dimRatio":50,"overlayColor":"black","minHeight":220,"url":"{$baseurl}/gen/site-icon.png"} -->
<div class="wp-block-cover" style="min-height:220px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="{$baseurl}/gen/site-icon.png" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} --><h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">Обложка (Cover)</h2><!-- /wp:heading --></div></div>
<!-- /wp:cover -->
<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="{$baseurl}/gen/post-politika.png" alt="Политика"/></figure><!-- /wp:image --><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="{$baseurl}/gen/post-sport.png" alt="Спорт"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->
<!-- wp:file --><div class="wp-block-file"><a href="{$baseurl}/gen/post-gorod.png">Скачать баннер (File)</a></div><!-- /wp:file -->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Дизайн»</h2><!-- /wp:heading -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="/novosti/">Все новости (Buttons)</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><h3>Колонка 1</h3></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><h3>Колонка 2 (Columns)</h3></div><!-- /wp:column --></div><!-- /wp:columns -->
<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>Рубрика</td><td>Записей</td></tr><tr><td>Политика</td><td>2</td></tr><tr><td>Спорт</td><td>2</td></tr></tbody></table></figure><!-- /wp:table -->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Виджеты»</h2><!-- /wp:heading -->
<!-- wp:search --><form role="search" method="get" class="wp-block-search"><label class="wp-block-search__label" for="wp-block-search-1">Поиск по порталу</label><div class="wp-block-search__inside-wrapper"><input class="wp-block-search__input" id="wp-block-search-1" type="search" name="s" value=""/><button type="submit" class="wp-block-search__button">Найти</button></div></form><!-- /wp:search -->
<!-- wp:latest-posts {"postsToShow":3} /-->
<!-- wp:categories /-->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Тема»</h2><!-- /wp:heading -->
<!-- wp:site-title /-->
<!-- wp:site-tagline /-->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Встраивания»</h2><!-- /wp:heading -->
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=dQw4w9WgXcQ</div></figure><!-- /wp:embed -->

<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Группа «Шаблоны» (синхронизированный блок)</h2><!-- /wp:heading -->
<!-- wp:block {"ref":{$pattern_id}} /-->
HTML;

g24_reset( 'page', 'bloki', 'Демонстрация блоков' );
$blocks_page = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Демонстрация блоков',
	'post_name'    => 'bloki',
	'post_content' => $blocks_content,
) );
echo "страница блоков: {$blocks_page}\n";

/* ---------------- Задание 5: плагины блоков ---------------- */

// is_plugin_active() живёт в wp-admin и вне его контекста недоступна,
// поэтому активность проверяем напрямую: в active_plugins лежат пути
// к файлам плагинов, а не их слаги.
$active_plugins = (array) get_option( 'active_plugins', array() );
foreach ( array( 'kadence-blocks', 'stackable-ultimate-gutenberg-blocks' ) as $plugin ) {
	$is_active = false;
	foreach ( $active_plugins as $path ) {
		if ( strpos( $path, $plugin . '/' ) === 0 ) {
			$is_active = true;
			break;
		}
	}
	if ( $is_active ) {
		echo "плагин блоков активен: {$plugin}\n";
	} else {
		echo "ВНИМАНИЕ: плагин не активен: {$plugin}\n";
	}
}

echo "== ЛР3 готово ==\n";