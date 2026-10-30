<?php
/**
 * Конфигурация ЛР9 «Работа с публикациями и меню в Drupal».
 * Сайт — городской новостной портал «Город24» (продолжение ЛР8).
 *
 * Скрипт идемпотентен: повторный запуск не создаёт дубликатов материалов,
 * блоков и пунктов меню. Требует базу ЛР8 (подключает lab8-config.php).
 * Запуск: drush php:script /opt/drupal/web/scripts/lab9-config.php
 *
 * Пункты задания labs/Drupal/Drupal_2.pdf:
 *  1 — регион с двумя блоками произвольного содержания;
 *  2 — материал типа Article и ссылка на него в меню;
 *  3 — три дополнительные страницы: история фирмы (текст + картинки + ссылки
 *      на официальный сайт), рабочая галерея на Colorbox, прайс-лист таблицей;
 *  4 — меню из страниц задания 3, пункт «Каталог» с пустой ссылкой и подпунктами
 *      терминов словаря, дополнительное меню в футере.
 */

$root = \Drupal::root();
// База ЛР8: тема, рубрики, новости, каталог, таксономия, алиасы, метатеги.
require $root . '/scripts/lab8-config.php';

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$display_repo = \Drupal::service('entity_display.repository');

/** Файл из scripts/assets в public://, возвращает file id. */
function lab9_file(string $rel, string $target_dir): int {
  $fs = \Drupal::service('file_system');
  $storage = \Drupal::entityTypeManager()->getStorage('file');
  $name = basename($rel);
  $uri = "public://{$target_dir}/{$name}";
  $directory = "public://{$target_dir}";
  $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $real = $fs->realpath($directory) . '/' . $name;
  file_put_contents($real, file_get_contents(\Drupal::root() . '/scripts/assets/' . $rel));
  $found = $storage->loadByProperties(['uri' => $uri]);
  $file = reset($found) ?: File::create(['uri' => $uri]);
  $file->setPermanent();
  $file->setSize(filesize($real));
  $file->setMimeType('image/png' === strtolower(pathinfo($name, PATHINFO_EXTENSION)) ? 'image/png' : 'image/jpeg');
  $file->save();
  return (int) $file->id();
}

/** Создаёт материал, если материала с таким заголовком ещё нет. */
function lab9_node(string $type, string $title, array $values = []): Node {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $found = $storage->loadByProperties(['type' => $type, 'title' => $title]);
  $node = reset($found) ?: Node::create(['type' => $type, 'title' => $title]);
  foreach ($values as $field => $value) {
    $node->set($field, $value);
  }
  $node->set('status', 1);
  $node->save();
  return $node;
}

/** Создаёт пункт меню, если такого пункта с таким заголовком ещё нет. */
function lab9_menu(string $menu, string $title, string $uri, string $parent = '', int $weight = 0): MenuLinkContent {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $found = $storage->loadByProperties(['menu_name' => $menu, 'title' => $title]);
  $link = reset($found) ?: MenuLinkContent::create(['menu_name' => $menu, 'title' => $title]);
  $link->set('link', ['uri' => $uri]);
  $link->set('weight', $weight);
  if ($parent !== '') {
    $link->set('parent', $parent);
  }
  $link->save();
  return $link;
}

/** Алиас внутреннего пути (для ссылок в блоках). */
function lab9_alias(string $path): string {
  return \Drupal::service('path_alias.manager')->getAliasByPath($path);
}

// Стили изображений: миниатюры для галереи и фото на странице истории.
foreach ([
  'gallery_thumb' => [240, 160],
  'history_photo' => [640, 400],
] as $style_name => $size) {
  if (!ImageStyle::load($style_name)) {
    ImageStyle::create([
      'name' => $style_name,
      'label' => "{$style_name} {$size[0]}×{$size[1]}",
      'effects' => [
        'image_scale_and_crop' => [
          'id' => 'image_scale_and_crop',
          'weight' => 0,
          'data' => ['width' => $size[0], 'height' => $size[1]],
        ],
      ],
    ])->save();
    echo "стиль изображения {$style_name}: создан\n";
  }
}

// ---------------------------------------------------------------------------
// Поля страницы: картинки истории и галерея (задание 3)
// ---------------------------------------------------------------------------
foreach (['field_images' => 'Фотографии страницы', 'field_gallery' => 'Галерея'] as $field_name => $label) {
  if (!FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'image',
      'settings' => ['uri_scheme' => 'public'],
      'cardinality' => -1,
    ])->save();
    echo "хранилище поля {$field_name}: создано\n";
  }
  if (!FieldConfig::loadByName('node', 'page', $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => $label,
      'settings' => ['file_extensions' => 'png jpg jpeg', 'alt_field' => TRUE, 'alt_field_required' => FALSE],
    ])->save();
    echo "поле «{$label}» у типа «Страница»: создано\n";
  }
}

// Вывод: обычные фото — картинками, галерея — модулем Colorbox.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
$page_form = $display_repo->getFormDisplay('node', 'page', 'default');
$page_form
  ->setComponent('field_images', ['type' => 'image_image', 'weight' => 10])
  ->setComponent('field_gallery', ['type' => 'image_image', 'weight' => 11])
  ->save();

$page_view = $display_repo->getViewDisplay('node', 'page', 'default');
$page_view
  ->setComponent('field_images', [
    'type' => 'image',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['image_style' => 'history_photo', 'image_link' => 'file'],
  ])
  ->setComponent('field_gallery', [
    'type' => 'colorbox',
    'label' => 'hidden',
    'weight' => 1,
    'settings' => [
      'colorbox_node_style' => 'gallery_thumb',
      'colorbox_node_style_first' => '',
      'colorbox_image_style' => '',
      'colorbox_gallery' => 'post',
      'colorbox_gallery_custom' => '',
      'colorbox_caption' => 'auto',
      'colorbox_caption_custom' => '',
    ],
  ])
  ->save();

// Свежие определения полей после создания полей страницы.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('entity_type.bundle.info')->clearCachedBundles();

// ---------------------------------------------------------------------------
// Задание 1. Регион с двумя блоками произвольного содержания
// ---------------------------------------------------------------------------
// Блок «О портале» — о редакции и форме для читателей.
$about = $etm->getStorage('block_content')->loadByProperties(['info' => 'О портале']);
$about_block = reset($about) ?: BlockContent::create(['type' => 'basic', 'info' => 'О портале']);
$about_block->set('body', [
  'value' => '<p>«Город24» — городской новостной портал: город, транспорт, происшествия, культура, спорт и технологии. '
    . 'Редакция работает ежедневно, новости обновляются в течение дня.</p>'
    . '<p><a href="/form/prislat-novost">Прислать новость в редакцию</a></p>',
  'format' => 'basic_html',
]);
$about_block->save();

// Блок «Рубрики» — ссылки на разделы портала (из словаря «Рубрики новостей»).
$rubric_links = '';
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'rubriki']) as $term) {
  $rubric_links .= '<li><a href="' . lab9_alias('/taxonomy/term/' . $term->id()) . '">' . $term->getName() . '</a></li>';
}
$rubrics = $etm->getStorage('block_content')->loadByProperties(['info' => 'Рубрики']);
$rubrics_block = reset($rubrics) ?: BlockContent::create(['type' => 'basic', 'info' => 'Рубрики']);
$rubrics_block->set('body', [
  'value' => '<ul class="list-unstyled">' . $rubric_links . '</ul>',
  'format' => 'basic_html',
]);
$rubrics_block->save();

$sidebar_blocks = [
  ['id' => 'lab9_block_1', 'label' => 'О портале', 'content' => $about_block, 'weight' => 0],
  ['id' => 'lab9_block_2', 'label' => 'Рубрики', 'content' => $rubrics_block, 'weight' => 1],
];
foreach ($sidebar_blocks as $spec) {
  $block = Block::load($spec['id']) ?: Block::create([
    'id' => $spec['id'],
    'plugin' => 'block_content:' . $spec['content']->uuid(),
    'theme' => 'bootstrap5',
  ]);
  $block
    ->set('plugin', 'block_content:' . $spec['content']->uuid())
    ->set('region', 'sidebar_first')
    ->set('weight', $spec['weight'])
    ->set('settings', [
      'id' => 'block_content:' . $spec['content']->uuid(),
      'label' => $spec['label'],
      'label_display' => 'visible',
      'provider' => 'block_content',
      'status' => TRUE,
      'info' => $spec['content']->get('info')->value,
      'view_mode' => 'full',
    ])
    ->save();
  echo "блок «{$spec['label']}»: размещён в sidebar_first (тема bootstrap5)\n";
}

// ---------------------------------------------------------------------------
// Задание 2. Материал статьи и ссылка в меню
// ---------------------------------------------------------------------------
$article = lab9_node('article', 'Как работает редакция «Город24»', [
  'body' => [
    'value' => '<p>Редакция городского портала работает в три смены: утренняя выпускает подборку за ночь, '
      . 'дневная ведёт городские события, вечерняя собирает итоги дня. Материал создан как Article, '
      . 'чтобы показать его настройки: публикация, продвижение на главную, закрепление, метатеги и SEO-анализ.</p>'
      . '<p>Читатели присылают новости через форму <a href="/form/prislat-novost">«Прислать новость»</a>, '
      . 'дежурный редактор проверяет факты и публикует материал в соответствующей рубрике.</p>',
    'format' => 'basic_html',
    'summary' => 'Как устроена работа редакции «Город24»: смены, проверка фактов, рубрики и форма для читателей.',
  ],
  'promote' => 0,
]);
echo "статья: node/{$article->id()} «{$article->getTitle()}»\n";
lab9_menu('main', 'О редакции', 'internal:/node/' . $article->id(), '', 8);
echo "пункт меню «О редакции»: создан\n";

// ---------------------------------------------------------------------------
// Задание 3.1. История фирмы: текст, картинки, ссылки на официальный сайт
// ---------------------------------------------------------------------------
$history_photos = [];
foreach (['history/hq.jpg' => 'Штаб-квартира Lenovo', 'history/x1.jpg' => 'Lenovo ThinkPad X1 Carbon'] as $rel => $alt) {
  $history_photos[] = ['target_id' => lab9_file($rel, 'history'), 'alt' => $alt];
}
$history = lab9_node('page', 'История фирмы Lenovo', [
  'body' => [
    'value' => '<p>Материал раздела «Техника»: как китайская компания Legend стала производителем ноутбуков Lenovo, '
      . 'модели которых собраны в каталоге портала.</p>'
      . '<p>Компания основана в 1984 году в Пекине группой из одиннадцати инженеров во главе с Лю Чуаньчжи. '
      . 'Первоначальное название — Legend (Lianxiang), первый крупный продукт — плата расширения для IBM PC, '
      . 'позволившая печатать иероглифы на обычном персональном компьютере.</p>'
      . '<p>В 2004 году Legend приобрела подразделение персональных компьютеров IBM и вышла на мировой рынок '
      . 'под маркой Lenovo. С 2011 года сам бренд используется и на китайском рынке. В 2013 году компания стала '
      . 'крупнейшим производителем персональных компьютеров в мире.</p>'
      . '<p>Сегодня Lenovo выпускает ноутбуки четырёх основных линеек: IdeaPad (массовые модели), ThinkPad '
      . '(бизнес-класс с 1992 года), Yoga (трансформеры с шарниром на 360°) и V-серию для офиса и учёбы.</p>'
      . '<p>Официальные сайты: <a href="https://www.lenovo.com/by/ru/">lenovo.com/by — белорусский сайт</a>, '
      . '<a href="https://www.lenovo.com/ru/ru/">lenovo.com/ru — российский сайт</a>, '
      . '<a href="https://news.lenovo.com/">news.lenovo.com — пресс-центр</a>.</p>',
    'format' => 'basic_html',
  ],
  'field_images' => $history_photos,
]);
echo "страница «История фирмы»: node/{$history->id()} (картинок: " . count($history_photos) . ")\n";

// ---------------------------------------------------------------------------
// Задание 3.2. Рабочая галерея на Colorbox
// ---------------------------------------------------------------------------
$gallery_photos = [];
for ($i = 1; $i <= 8; $i++) {
  $gallery_photos[] = [
    'target_id' => lab9_file(sprintf('gallery/foto%02d.jpg', $i), 'gallery'),
    'alt' => "Ноутбук Lenovo, фотография {$i}",
  ];
}
$gallery = lab9_node('page', 'Галерея ноутбуков Lenovo', [
  'body' => [
    'value' => '<p>Фотогалерея ноутбуков из каталога портала. Щелчок по миниатюре открывает снимок '
      . 'в лайтбоксе Colorbox с навигацией по галерее стрелками.</p>'
      . '<p class="photo-credits">Фотографии для раздела «Техника» взяты с Wikimedia Commons (лицензии CC BY-SA 3.0/4.0); авторы и файлы перечислены в CREDITS.md в репозитории лабораторной.</p>',
    'format' => 'basic_html',
  ],
  'field_gallery' => $gallery_photos,
]);
echo "страница «Галерея»: node/{$gallery->id()} (фотографий: " . count($gallery_photos) . ")\n";

// ---------------------------------------------------------------------------
// Задание 3.3. Прайс-лист лучших ноутбуков простой таблицей
// ---------------------------------------------------------------------------
$price_rows = [
  ['Lenovo ThinkPad X1 Carbon (3443)', 'серия ThinkPad', '14″ 1600×900, Core i5, 8 ГБ, SSD 256 ГБ', '3 150'],
  ['Lenovo Yoga 2 Pro (20266)', 'серия Yoga', '13,3″ 3200×1800, Core i7, 8 ГБ, SSD 512 ГБ', '2 640'],
  ['Lenovo Yoga 3 Pro (80HE)', 'серия Yoga', '13,3″ QHD+, Core M, 8 ГБ, SSD 256 ГБ', '2 380'],
  ['Lenovo ThinkPad T490 (20N2)', 'серия ThinkPad', '14″ IPS, Core i5, 16 ГБ, SSD 512 ГБ', '2 250'],
  ['Lenovo Yoga 530-14ARR', 'серия Yoga', '14″ Full HD, Ryzen 5, 8 ГБ, SSD 256 ГБ', '1 890'],
  ['Lenovo IdeaPad Flex 14 (82B8)', 'серия IdeaPad', '14″ Full HD, Core i5, 8 ГБ, SSD 256 ГБ', '1 740'],
  ['Lenovo V330-15IKB (81AX)', 'серия V', '15,6″ Full HD, Core i5, 8 ГБ, SSD 256 ГБ', '1 560'],
  ['Lenovo IdeaPad Yoga 13 (20172)', 'серия IdeaPad', '13,3″ 1600×900, Core i5, 4 ГБ, SSD 128 ГБ', '1 120'],
  ['Lenovo ThinkPad E520 (1143)', 'серия ThinkPad', '15,6″ 1366×768, Core i3, 4 ГБ, HDD 320 ГБ', '870'],
  ['Lenovo ThinkPad X220 (4291)', 'серия ThinkPad', '12,5″ IPS, Core i5, 4 ГБ, SSD 128 ГБ', '640'],
];

$rows = '';
foreach ($price_rows as $i => $row) {
  $rows .= '<tr><td>' . ($i + 1) . '</td><td>' . $row[0] . '</td><td>' . $row[1] . '</td><td>' . $row[2] . '</td><td>' . $row[3] . '</td></tr>';
}
$price_html = '<table border="1" cellpadding="6" cellspacing="0">'
  . '<thead><tr><th>№</th><th>Модель</th><th>Серия</th><th>Характеристики</th><th>Цена, BYN</th></tr></thead>'
  . '<tbody>' . $rows . '</tbody></table>'
  . '<p>Цены указаны на 2026 год и носят учебный характер.</p>';

$price = lab9_node('page', 'Прайс-лист ноутбуков Lenovo', [
  // Формат full_html, иначе фильтр basic_html вырезает теги <table>.
  'body' => ['value' => $price_html, 'format' => 'full_html'],
]);
echo "страница «Прайс-лист»: node/{$price->id()} (строк: " . count($price_rows) . ")\n";

// ---------------------------------------------------------------------------
// Задание 4. Меню: разделы портала, «Каталог» с пустой ссылкой и терминами, футер
// ---------------------------------------------------------------------------
$menu_items = [
  ['История фирмы', $history->id(), 4],
  ['Галерея', $gallery->id(), 5],
  ['Прайс-лист', $price->id(), 6],
];
foreach ($menu_items as $item) {
  lab9_menu('main', $item[0], 'internal:/node/' . $item[1], '', $item[2]);
  echo "пункт меню «{$item[0]}»\n";
}

// «Рубрики» — пустая ссылка, подпункты — термины словаря «Рубрики новостей».
$rubrics_menu = lab9_menu('main', 'Рубрики', 'route:<nolink>', '', 1);
$rubrics_parent = 'menu_link_content:' . $rubrics_menu->uuid();
$weight = 0;
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'rubriki']) as $term) {
  lab9_menu('main', $term->getName(), 'internal:/taxonomy/term/' . $term->id(), $rubrics_parent, $weight++);
  echo "  подпункт рубрики «{$term->getName()}»\n";
}

// «Каталог» — пустая ссылка (route:<nolink>), подпункты — термины словаря Lenovo.
$catalog_menu = lab9_menu('main', 'Каталог', 'route:<nolink>', '', 2);
$catalog_parent = 'menu_link_content:' . $catalog_menu->uuid();
$weight = 0;
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'lenovo']) as $term) {
  lab9_menu('main', $term->getName(), 'internal:/taxonomy/term/' . $term->id(), $catalog_parent, $weight++);
  echo "  подпункт каталога «{$term->getName()}»\n";
}

// «Техника» — страница каталога из представления Views (ЛР8).
lab9_menu('main', 'Техника', 'internal:/noutbuki', '', 3);

// Дополнительное меню в футере.
lab9_menu('footer', 'Главная', 'internal:/', '', 0);
foreach ($menu_items as $i => $item) {
  lab9_menu('footer', $item[0], 'internal:/node/' . $item[1], '', $i + 1);
}
lab9_menu('footer', 'Прислать новость', 'internal:/form/prislat-novost', '', 4);
// Название меню, которое видно в подвале и в админке.
\Drupal::configFactory()->getEditable('system.menu.footer')
  ->set('label', 'Дополнительное меню')
  ->set('description', 'Дополнительное меню портала в подвале страницы')
  ->save();
echo "меню footer: 5 пунктов\n";

// Меню футера выводит штатный блок темы bootstrap5 (system_menu_block:footer),
// поэтому собственный блок не создаём, а лишний от прошлых прогонов убираем.
if ($extra_footer_block = Block::load('lab9_footer_menu')) {
  $extra_footer_block->delete();
}
// Заголовок блока меню в подвале — «Дополнительное меню», а не служебное «Footer».
if ($theme_footer_block = Block::load('bootstrap5_footer')) {
  $settings = $theme_footer_block->get('settings');
  $settings['label'] = 'Дополнительное меню';
  $theme_footer_block->set('settings', $settings)->save();
}
echo "меню footer: выводится штатным блоком темы в регионе footer\n";

// Пересчитать алиасы новых страниц по паттернам Pathauto из ЛР8.
$generator = \Drupal::service('pathauto.generator');
$alias_storage = $etm->getStorage('path_alias');
foreach ([$history, $gallery, $price, $article] as $node) {
  if ($node->hasField('path')) {
    $node->set('path', ['pathauto' => 1]);
  }
  foreach ($alias_storage->loadByProperties(['path' => '/node/' . $node->id()]) as $alias) {
    $alias->delete();
  }
  $generator->updateEntityAlias($node, 'bulkupdate');
}

// ---------------------------------------------------------------------------
// Чистка следов прошлых прогонов: материалы и пункты меню с другими заголовками
// ---------------------------------------------------------------------------
$allowed_nodes = [
  'article' => [$article->getTitle()],
  'page' => [$history->getTitle(), $gallery->getTitle(), $price->getTitle()],
];
foreach ($etm->getStorage('node')->loadByProperties(['type' => ['article', 'page']]) as $node) {
  if (!in_array($node->getTitle(), $allowed_nodes[$node->bundle()] ?? [], TRUE)) {
    echo '  устаревший материал удалён: ' . $node->getTitle() . "\n";
    $node->delete();
  }
}

$allowed_links = [
  'main' => array_merge(
    ['Главная', 'Рубрики', 'Каталог', 'Техника', 'О редакции'],
    array_column($menu_items, 0),
    ['Город', 'Транспорт', 'Происшествия', 'Культура', 'Спорт', 'Технологии'],
    ['серия IdeaPad', 'серия ThinkPad', 'серия Yoga', 'серия V']
  ),
  'footer' => array_merge(['Главная', 'Прислать новость'], array_column($menu_items, 0)),
];
foreach ($allowed_links as $menu_name => $titles) {
  foreach ($etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_name]) as $link) {
    if (!in_array($link->getTitle(), $titles, TRUE)) {
      echo "  устаревший пункт меню «{$link->getTitle()}» удалён ({$menu_name})\n";
      $link->delete();
    }
  }
}

\Drupal::service('router.builder')->rebuild();
echo "ЛР9: конфигурация завершена\n";
