<?php
/**
 * Автонастройка сайта «Город24» для ЛР №2 по CMS.
 * Запуск: docker compose run --rm -T wpcli eval-file - < ../lab2/scripts/setup-content.php
 * Закрывает задания №2, №6, №7, №8, №9, №10 (№4 виджеты и №5 плагины — командами wp).
 */

echo "== Задание 2: правка стандартной темы (название, шапка, фон) ==\n";
update_option( 'blogname', 'Город24' );
update_option( 'blogdescription', 'Новостной портал Минска — локальная повестка 24/7' );

// Дополнительные стили для стандартной темы Twenty Twenty-Five:
// фон страницы и акцентная линия под шапкой.
$ttf_css = "body { background-color: #eef3f9; }\n"
	. ".wp-site-blocks > header { border-bottom: 4px solid #3563e9; }\n"
	. ".wp-block-site-title a { color: #1d4cbb; }\n";
$existing = get_posts( array(
	'post_type'      => 'custom_css',
	'post_name'      => 'twentytwentyfive',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
) );
if ( $existing ) {
	wp_update_post( array( 'ID' => $existing[0]->ID, 'post_content' => $ttf_css ) );
	$css_id = $existing[0]->ID;
} else {
	$css_id = wp_insert_post( array(
		'post_type'    => 'custom_css',
		'post_name'    => 'twentytwentyfive',
		'post_status'  => 'publish',
		'post_content' => $ttf_css,
	) );
}
echo "custom_css (twentytwentyfive): {$css_id}\n";

echo "== Задание 6: часовой пояс, дата/время, политика комментирования ==\n";
update_option( 'timezone_string', 'Europe/Minsk' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'H:i' );
update_option( 'start_of_week', 1 );

update_option( 'default_comment_status', 'open' );
update_option( 'comment_registration', '1' );
update_option( 'comment_moderation', '1' );
update_option( 'comment_whitelist', '1' );
update_option( 'require_name_email', 1 );
update_option( 'close_comments_for_old_posts', 1 );
update_option( 'close_comments_days_old', 30 );
update_option( 'thread_comments', '1' );
update_option( 'thread_comments_depth', 2 );
echo "настройки обсуждения обновлены\n";

update_option( 'permalink_structure', '/%postname%/' );
global $wp_rewrite;
$wp_rewrite->flush_rules( true );

echo "== Удаляем демо-контент WordPress ==\n";
foreach ( array( 1, 2, 3 ) as $demo_id ) {
	if ( get_post( $demo_id ) ) {
		wp_delete_post( $demo_id, true );
	}
}

echo "== Задание 7: рубрика + подрубрики ==\n";
$parent = wp_insert_term( 'Новости Минска', 'category', array(
	'slug'        => 'novosti-minska',
	'description' => 'Главные события города за неделю',
) );
if ( is_wp_error( $parent ) ) {
	$parent = get_term_by( 'slug', 'novosti-minska', 'category' );
	$parent_id = $parent->term_id;
} else {
	$parent_id = $parent['term_id'];
}

$cat_ids = array();
foreach ( array(
	'Политика'  => 'politika',
	'Экономика' => 'ekonomika',
	'Спорт'     => 'sport',
	'Культура'  => 'kultura',
	'Город'     => 'gorod',
) as $name => $slug ) {
	$t = wp_insert_term( $name, 'category', array( 'slug' => $slug, 'parent' => $parent_id ) );
	if ( is_wp_error( $t ) ) {
		$t = get_term_by( 'slug', $slug, 'category' );
		$cat_ids[ $slug ] = $t->term_id;
	} else {
		$cat_ids[ $slug ] = $t['term_id'];
	}
}
echo "рубрика-родитель: {$parent_id}, подрубрики: " . wp_json_encode( $cat_ids ) . "\n";

echo "== Генерируем изображения-заглушки (GD) и прикрепляем к записям ==\n";
function gorod24_gradient_png( $file, $w, $h, $c1, $c2, $label = '' ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}
	$im = imagecreatetruecolor( $w, $h );
	for ( $x = 0; $x < $w; $x++ ) {
		$t   = $x / max( 1, $w - 1 );
		$col = imagecolorallocate(
			$im,
			(int) round( $c1[0] + ( $c2[0] - $c1[0] ) * $t ),
			(int) round( $c1[1] + ( $c2[1] - $c1[1] ) * $t ),
			(int) round( $c1[2] + ( $c2[2] - $c1[2] ) * $t )
		);
		imagefilledrectangle( $im, $x, 0, $x, $h, $col );
	}
	if ( '' !== $label && function_exists( 'imagestring' ) ) {
		$white = imagecolorallocate( $im, 255, 255, 255 );
		imagestring( $im, 5, 24, $h - 32, $label, $white );
	}
	imagepng( $im, $file );
	imagedestroy( $im );
	return true;
}

$gen_dir = wp_upload_dir()['basedir'] . '/gen';
wp_mkdir_p( $gen_dir );

function gorod24_attach( $file, $title ) {
	$att = wp_insert_attachment( array(
		'post_mime_type' => 'image/png',
		'post_title'     => $title,
		'post_status'    => 'inherit',
	), $file );
	if ( is_wp_error( $att ) || ! $att ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
	return (int) $att;
}

$palettes = array(
	'politika'  => array( array( 53, 99, 233 ), array( 29, 76, 187 ) ),
	'ekonomika' => array( array( 66, 153, 225 ), array( 53, 99, 233 ) ),
	'sport'     => array( array( 44, 62, 80 ), array( 72, 92, 128 ) ),
	'kultura'   => array( array( 191, 144, 144 ), array( 54, 34, 34 ) ),
	'gorod'     => array( array( 144, 163, 191 ), array( 65, 85, 123 ) ),
);
$thumbs = array();
if ( function_exists( 'imagecreatetruecolor' ) ) {
	foreach ( $palettes as $slug => $colors ) {
		$file = $gen_dir . '/post-' . $slug . '.png';
		gorod24_gradient_png( $file, 1200, 720, $colors[0], $colors[1], 'GOROD24' );
		$thumbs[ $slug ] = gorod24_attach( $file, 'Заглушка: ' . $slug );
	}

	$icon_file = $gen_dir . '/site-icon.png';
	gorod24_gradient_png( $icon_file, 512, 512, $palettes['politika'][0], $palettes['politika'][1], '' );
	$icon_att = gorod24_attach( $icon_file, 'Иконка сайта Город24' );
	if ( $icon_att ) {
		update_option( 'site_icon', $icon_att );
	}
	echo "изображения: " . wp_json_encode( $thumbs ) . ", иконка: {$icon_att}\n";
} else {
	echo "GD недоступен — записи без миниатюр (сработают градиентные заглушки темы)\n";
}

echo "== Задание 8: публикации по подрубрикам + метки ==\n";
$posts_data = array(
	array(
		'title'    => 'Городской совет утвердил бюджет на 2027 год',
		'slug'     => 'budget-2027',
		'cat'      => 'politika',
		'tags'     => array( 'Минск', 'бюджет', 'городской совет' ),
		'date'     => '2026-09-11 14:30:00',
		'content'  => "<p>Депутаты Мингорсовета утвердили бюджет столицы на 2027 год в первом чтении. Большая часть расходов традиционно направлена на образование, медицину и транспорт.</p><p>Отдельная строка — завершение строительства станции метро «Немига-2» и обновление подвижного состава. Обсуждение бюджета во втором чтении пройдёт в октябре.</p>",
	),
	array(
		'title'    => 'Обсуждение генплана: где появятся новые парки',
		'slug'     => 'genplan-parki',
		'cat'      => 'politika',
		'tags'     => array( 'Минск', 'генплан', 'парки' ),
		'date'     => '2026-09-11 10:20:00',
		'content'  => "<p>В Мингорисполкоме прошло публичное обсуждение обновлённого генерального плана Минска. Среди предложений — три новых парка в зелёных залах по Кальварийской и Орловской.</p><p>Горожане могут оставить замечания на портале городских инициатив до конца месяца. Итоговые границы зелёных зон утвердят вместе с генпланом.</p>",
	),
	array(
		'title'    => 'Курс: что будет с ценами на жильё этой осенью',
		'slug'     => 'zhilyo-osen',
		'cat'      => 'ekonomika',
		'tags'     => array( 'жильё', 'цены', 'новостройки' ),
		'date'     => '2026-09-11 12:05:00',
		'content'  => "<p>Средняя цена квадратного метра в минских новостройках за август выросла на 1,8%. Аналитики связывают это со спросом на квартиры с отделкой и сокращением предложения готового жилья.</p><p>На вторичном рынке картина спокойнее: цены держатся около летних значений. Эксперты не ждут резких скачков до конца года.</p>",
	),
	array(
		'title'    => 'В Минске открылся крупнейший IT-хаб на 2000 мест',
		'slug'     => 'it-hab',
		'cat'      => 'ekonomika',
		'tags'     => array( 'IT', 'хаб', 'офисы' ),
		'date'     => '2026-09-09 16:45:00',
		'content'  => "<p>В районе Пироговской открылся крупнейший в стране IT-хаб: восемь этажей, 2000 рабочих мест, конференц-залы и собственный дата-центр.</p><p>Первыми резидентами стали 14 компаний. Город рассчитывает, что хаб ускорит развитие высокотехнологичных сервисов и удержит выпускников ИТ-специальностей в столице.</p>",
	),
	array(
		'title'    => 'Минское «Динамо» вышло в плей-офф кубка',
		'slug'     => 'dinamo-kubok',
		'cat'      => 'sport',
		'tags'     => array( 'спорт', 'Динамо', 'кубок' ),
		'date'     => '2026-09-10 22:40:00',
		'content'  => "<p>Минское «Динамо» в овертайме обыграло соперников и гарантировало себе место в плей-офф кубка. Решающую шайбу забросили за четыре минуты до финальной сирены.</p><p>Следующий матч команда проведёт дома в пятницу. Билеты уже в продаже на сайте клуба.</p>",
	),
	array(
		'title'    => 'Марафон «Минск-24»: регистрация открыта',
		'slug'     => 'marafon-minsk24',
		'cat'      => 'sport',
		'tags'     => array( 'марафон', 'бег', 'Минск' ),
		'date'     => '2026-09-09 09:00:00',
		'content'  => "<p>Открылась регистрация на осенний марафон «Минск-24». Дистанции — 5, 10 и 42 километра, старт у Дворца спорта, финиш на набережной Свислочи.</p><p>Организаторы ждут шесть тысяч участников из двенадцати стран. Первым трёмстам зарегистрировавшимся — фирменные футболки.</p>",
	),
	array(
		'title'    => 'Ночь музеев: центр города станет открытой сценой',
		'slug'     => 'noch-muzeev',
		'cat'      => 'kultura',
		'tags'     => array( 'культура', 'музеи', 'события' ),
		'date'     => '2026-09-10 18:15:00',
		'content'  => "<p>В субботу в Минске пройдёт «Ночь музеев»: вход свободный, музеи работают до полуночи, в центре города — открытые сцены с концертами и лекциями.</p><p>Шаттлы свяжут Верхний город, Оперный театр и Национальную библиотеку. Полная программа — на сайте Городского методического центра.</p>",
	),
	array(
		'title'    => 'Премьера сезона: опера «Кастусь Калиновский»',
		'slug'     => 'opera-kalinovskij',
		'cat'      => 'kultura',
		'tags'     => array( 'опера', 'премьера', 'театр' ),
		'date'     => '2026-09-08 20:10:00',
		'content'  => "<p>Большой театр оперы и балета открывает сезон премьерой «Кастусь Калиновский» — первой национальной оперой, поставленной в новой сценической редакции.</p><p>Партии исполняют солисты театра и приглашённые вокалисты. Показы идут до конца сентября, билеты раскуплены на две трети.</p>",
	),
	array(
		'title'    => 'Ремонт ул. Ленина завершат на две недели раньше',
		'slug'     => 'remont-lenina',
		'cat'      => 'gorod',
		'tags'     => array( 'ремонт', 'дороги', 'улицы' ),
		'date'     => '2026-09-10 11:00:00',
		'content'  => "<p>Капитальный ремонт улицы Ленина идёт с опережением графика: подрядчик обещает сдать движение по всем полосам на две недели раньше срока.</p><p>Обновят не только полотно, но и тротуары, освещение и остановочные пункты. Перенесённые маршруты вернутся к привычным схемам с 1 октября.</p>",
	),
);

$post_ids = array();
foreach ( $posts_data as $i => $p ) {
	$pid = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $p['title'],
		'post_name'    => $p['slug'],
		'post_content' => $p['content'],
		'post_date'    => $p['date'],
		'post_category' => array( $cat_ids[ $p['cat'] ] ),
	), true );
	if ( is_wp_error( $pid ) ) {
		echo "ОШИБКА создания записи: {$p['title']}: {$pid->get_error_message()}\n";
		continue;
	}
	wp_set_object_terms( $pid, $p['tags'], 'post_tag' );
	if ( isset( $thumbs[ $p['cat'] ] ) && $thumbs[ $p['cat'] ] ) {
		set_post_thumbnail( $pid, $thumbs[ $p['cat'] ] );
	}
	$post_ids[] = $pid;
}
echo "записи: " . wp_json_encode( $post_ids ) . "\n";

echo "== Задание 9: HTML-стили в записи + приватность ==\n";
$styled = wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_title'   => 'Тестируем HTML-редактор: стили в записи',
	'post_name'    => 'html-redaktor-stili',
	'post_date'    => '2026-09-12 12:00:00',
	'post_category' => array( $cat_ids['gorod'] ),
	'tags_input'   => array( 'эксперимент', 'HTML-редактор' ),
	'post_content' => "<p>Эта запись создана для задания №9: абзацы оформлены вручную в HTML-редакторе WordPress — выравнивание, фон, цвет текста и отступы.</p>"
		. "<h2 style=\"text-align:center;color:#1d4cbb;\">Выравнивание и цвет заголовка</h2>"
		. "<p style=\"text-align:center;\">Абзац выровнен по центру: text-align: center.</p>"
		. "<p style=\"background-color:#3563e9;color:#ffffff;padding:16px 24px;border-radius:8px;\">Белый текст на синем фоне с внутренними отступами 16px / 24px и скруглением 8px.</p>"
		. "<p style=\"color:#dc3545;text-indent:40px;line-height:1.8;\">Красный цвет текста, красная строка 40px (text-indent) и увеличенный межстрочный интервал line-height: 1.8.</p>"
		. "<blockquote style=\"background-color:#e8eff6;border-left:4px solid #3563e9;padding:12px 20px;\">Цитата с голубым фоном и акцентной левой границей.</blockquote>",
) );
if ( isset( $thumbs['gorod'] ) && $thumbs['gorod'] ) {
	set_post_thumbnail( $styled, $thumbs['gorod'] );
}

$private = wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'private',
	'post_title'   => 'Приватная заметка редакции',
	'post_name'    => 'privatnaya-zametka',
	'post_date'    => '2026-09-12 13:00:00',
	'post_category' => array( $cat_ids['gorod'] ),
	'post_content' => '<p>Эта запись опубликована со статусом «Личное» (private): просматривать её могут только авторизованные пользователи с правами администратора/редактора. Обычным посетителям запись недоступна.</p>',
) );

$passworded = wp_insert_post( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'post_title'     => 'Конфиденциальная запись по паролю',
	'post_name'      => 'zapis-po-parolyu',
	'post_date'      => '2026-09-12 14:00:00',
	'post_category'  => array( $cat_ids['gorod'] ),
	'post_password'  => 'lab2026',
	'post_content'   => '<p>Эта запись защищена паролем. Пароль для проверки: lab2026. Доступна любому, кто знает пароль, — так в WordPress настраивается промежуточная приватность между публичной и личной записью.</p>',
) );
echo "styles: {$styled}, private: {$private}, passworded: {$passworded}\n";

echo "== Задание 10: страницы из вёрстки ЛР №1 + меню ==\n";
$cf7_post = wp_insert_post( array(
	'post_type'    => 'wpcf7_contact_form',
	'post_status'  => 'publish',
	'post_title'   => 'Обратная связь',
	'post_name'    => 'obratnaya-svyaz',
) );
if ( $cf7_post && ! is_wp_error( $cf7_post ) ) {
	update_post_meta( $cf7_post, '_form',
		"<p><label>Ваше имя (обязательно)<br>[text* your-name autocomplete:name] </label></p>\n"
		. "<p><label>Ваш email (обязательно)<br>[email* your-email autocomplete:email] </label></p>\n"
		. "<p><label>Тема<br>[text your-subject] </label></p>\n"
		. "<p><label>Сообщение<br>[textarea your-message] </label></p>\n"
		. "<p>[submit \"Отправить\"]</p>" );
	update_post_meta( $cf7_post, '_mail', array(
		'subject'            => '[your-subject] — обращение с сайта Город24',
		'sender'             => '[your-name] <[your-email]>',
		'body'               => "Сообщение: [your-message]\n\nEmail отправителя: [your-email]",
		'recipient'          => 'news@gorod24.by',
		'additional_headers' => 'Reply-To: [your-email]',
		'attachments'        => '',
	) );
}
$cf7_id = $cf7_post;

$front_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Главная',
	'post_name'    => 'glavnaya',
	'post_content' => '<p>Новостной портал Минска. Локальная повестка 24/7, проверка фактов, без фейков.</p>',
) );

$news_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Новости',
	'post_name'    => 'novosti',
	'post_content' => '',
) );

$about_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'О нас',
	'post_name'    => 'o-nas',
	'post_content' => "<p style=\"max-width:720px;\">«Город24» — независимое городское медиа. Мы пишем о Минске каждый день: политика, экономика, спорт, культура и жизнь районов. Каждая новость проходит редактуру и фактчекинг — мы не публикуем непроверенную информацию.</p>"
		. "<p style=\"max-width:720px;\">Портал работает с 2019 года. Издатель — ООО «Городской портал», свидетельство о регистрации СМИ № 0009551.</p>"
		. "<h2 style=\"margin-top:40px;\">Редакция</h2>"
		. "<div class=\"team-grid\">"
		. "<div class=\"team-card\"><div class=\"avatar\"></div><h3>Анна Соколова</h3><p class=\"role\">Главный редактор</p></div>"
		. "<div class=\"team-card\"><div class=\"avatar\"></div><h3>Дмитрий Кравцов</h3><p class=\"role\">Корреспондент, политика</p></div>"
		. "<div class=\"team-card\"><div class=\"avatar\"></div><h3>Мария Лабецкая</h3><p class=\"role\">Корреспондент, экономика</p></div>"
		. "<div class=\"team-card\"><div class=\"avatar\"></div><h3>Павел Гринь</h3><p class=\"role\">Фотограф</p></div>"
		. "</div>"
		. "<h2 style=\"margin-top:40px;\">Частые вопросы</h2>"
		. "<div class=\"row-list\" style=\"max-width:720px;\">"
		. "<div class=\"row-item\"><div><h3>Как предложить новость?</h3><p>Через <a href=\"/kontakty/\">форму обратной связи</a> или на почту news@gorod24.by — редакция отвечает в течение часа.</p></div></div>"
		. "<div class=\"row-item\"><div><h3>Как оформить премиум-подписку?</h3><p>Первый месяц бесплатно до 31.10.2026, далее — 9,90 BYN в месяц, отмена в любой момент.</p></div></div>"
		. "<div class=\"row-item\"><div><h3>Можно ли разместить рекламу?</h3><p>Да: баннеры и нативные статьи. Условия — по телефону редакции.</p></div></div>"
		. "</div>"
		. "<h2 style=\"margin-top:40px;\">Наша история</h2>"
		. "<p style=\"max-width:720px;\">В 2019 году мы начали как студенческий блог о жизни города: писали о ремонте дворов и расписании автобусов. За семь лет выросли в редакцию из 12 человек — и по-прежнему пишем о том, что волнует минчан каждый день.</p>",
) );

$city_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Город',
	'post_name'    => 'gorod-stranica',
	'post_content' => "<p style=\"max-width:720px;\">Всё полезное о Минске в одном месте: афиша выходных, прогноз погоды, транспорт и справочник городских организаций.</p>"
		. "<h2 style=\"margin-top:40px;\">Афиша выходных</h2>"
		. "<div class=\"row-list\">"
		. "<div class=\"row-item\"><div class=\"date-badge\">12<span>сб</span></div><div><h3>Ночь музеев</h3><p>Центр города • с 18:00 • вход свободный</p></div></div>"
		. "<div class=\"row-item\"><div class=\"date-badge\">13<span>вс</span></div><div><h3>Городской марафон «Минск-24»</h3><p>Старт у Дворца спорта • 09:00</p></div></div>"
		. "<div class=\"row-item\"><div class=\"date-badge\">14<span>пн</span></div><div><h3>Открытая лекция: архитектура Немиги</h3><p>Городская библиотека • 19:00</p></div></div>"
		. "</div>"
		. "<div class=\"section-tinted\" style=\"margin:40px -40px; padding:40px;\">"
		. "<h2>Погода в Минске</h2>"
		. "<div class=\"weather-grid\">"
		. "<div class=\"weather-card\"><div class=\"temp\">+18°</div><div class=\"cond\">Сегодня, облачно</div></div>"
		. "<div class=\"weather-card\"><div class=\"temp\">+16°</div><div class=\"cond\">Завтра, дождь</div></div>"
		. "<div class=\"weather-card\"><div class=\"temp\">+19°</div><div class=\"cond\">Воскресенье, ясно</div></div>"
		. "</div></div>"
		. "<h2 style=\"margin-top:40px;\">Транспорт</h2>"
		. "<div class=\"row-list\">"
		. "<div class=\"row-item\"><div class=\"date-badge\">М1</div><div><h3>Метро, первая линия</h3><p>Интервал 3–5 минут до 22:00, далее 8 минут</p></div></div>"
		. "<div class=\"row-item\"><div class=\"date-badge\">А1</div><div><h3>Автобус № 1</h3><p>Вокзал — Немига — Каменная Горка, каждые 10 минут</p></div></div>"
		. "<div class=\"row-item\"><div class=\"date-badge\">Т8</div><div><h3>Трамвай № 8</h3><p>Заводская — центр, каждые 12 минут</p></div></div>"
		. "</div>",
) );

$contacts_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Контакты',
	'post_name'    => 'kontakty',
	'post_content' => "<p style=\"max-width:720px;\">Адрес редакции: г. Минск, ул. Немига, 5, офис 312. Телефон: +375 (17) 305-24-24. Email: news@gorod24.by.</p>"
		. "<p style=\"max-width:720px;\">Предложить новость или задать вопрос можно через форму ниже.</p>"
		. "[contact-form-7 id=\"{$cf7_id}\" title=\"Обратная связь\"]",
) );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front_id );
update_option( 'page_for_posts', $news_id );
echo "страницы: front={$front_id}, news={$news_id}, about={$about_id}, city={$city_id}, contacts={$contacts_id}, cf7={$cf7_id}\n";

$main_menu = wp_create_nav_menu( 'Главное меню' );
foreach ( array(
	array( $front_id, 'Главная' ),
	array( $news_id, 'Новости' ),
	array( $about_id, 'О нас' ),
	array( $city_id, 'Город' ),
	array( $contacts_id, 'Контакты' ),
) as $item ) {
	wp_update_nav_menu_item( $main_menu, 0, array(
		'menu-item-title'     => $item[1],
		'menu-item-object'    => 'page',
		'menu-item-object-id' => $item[0],
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	) );
}

$foot_menu = wp_create_nav_menu( 'Меню подвала' );
foreach ( array(
	array( $about_id, 'О нас' ),
	array( $city_id, 'Город' ),
	array( $contacts_id, 'Контакты' ),
) as $item ) {
	wp_update_nav_menu_item( $foot_menu, 0, array(
		'menu-item-title'     => $item[1],
		'menu-item-object'    => 'page',
		'menu-item-object-id' => $item[0],
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	) );
}

set_theme_mod( 'nav_menu_locations', array(
	'primary' => $main_menu,
	'footer'  => $foot_menu,
) );
echo "меню: primary={$main_menu}, footer={$foot_menu}\n";

if ( $post_ids ) {
	wp_insert_comment( array(
		'comment_post_ID'      => $post_ids[0],
		'comment_author'       => 'Анна Соколова',
		'comment_author_email' => 'anna@gorod24.by',
		'comment_content'      => 'Спасибо за оперативную новость! Ждём подробностей о пересадочном узле.',
		'comment_approved'     => 1,
	) );
	echo "демо-комментарий добавлен к записи {$post_ids[0]}\n";
}

echo "== Готово ==\n";
