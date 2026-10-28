<?php
/**
 * Конфигурация ЛР8 «Знакомство с Drupal».
 *
 * Скрипт идемпотентен: повторный запуск не создаёт дубликатов.
 * Запуск: drush php:script /opt/drupal/web/scripts/lab8-config.php
 *
 * Что настраивает (по пунктам задания labs/Drupal/Drupal_1.pdf):
 *  3.1 — тема, логотип, название, фавикон, слоган;
 *  3.3 — модули Admin Toolbar, Pathauto, Token, Metatag, Webform, yoast_seo;
 *  3.4 — алиасы Pathauto (+ ctools, Token), метатеги Metatag + SEO-анализ yoast_seo;
 *  3.5 — словарь «Lenovo», тип материала «Ноутбук» с полем термина и фото,
 *        каталог из 10 ноутбуков.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\webform\Entity\Webform;
use Drupal\Core\Entity\EntityStorageInterface;

$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$asset_dir = \Drupal::root() . '/scripts/assets';

/** Путь к файлу-ассету внутри каталога scripts/assets. */
function lab8_asset(string $rel): string {
  $path = \Drupal::root() . '/scripts/assets/' . $rel;
  if (!file_exists($path)) {
    throw new \RuntimeException("Ассет не найден: {$path}");
  }
  return $path;
}

/** Кладёт файл в public:// и возвращает file id (повторно файл не дублируется). */
function lab8_file(string $rel, string $target_dir): int {
  $fs = \Drupal::service('file_system');
  $storage = \Drupal::entityTypeManager()->getStorage('file');
  $name = basename($rel);
  $uri = "public://{$target_dir}/{$name}";
  $directory = "public://{$target_dir}";
  $fs->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
  // Сам файл на диск.
  $real = $fs->realpath($directory) . '/' . $name;
  file_put_contents($real, file_get_contents(lab8_asset($rel)));
  // Запись в file_entity — одна и та же на повторных прогонах.
  $found = $storage->loadByProperties(['uri' => $uri]);
  $file = reset($found) ?: File::create(['uri' => $uri]);
  $file->setPermanent();
  $file->setSize(filesize($real));
  $file->setMimeType('image/png' === strtolower(pathinfo($name, PATHINFO_EXTENSION)) ? 'image/png' : 'image/jpeg');
  $file->save();
  return (int) $file->id();
}

// ---------------------------------------------------------------------------
// 1. Тема, логотип, фавикон, название, слоган (задание 2)
// ---------------------------------------------------------------------------
$theme = 'bootstrap5';
$theme_config = \Drupal::configFactory()->getEditable($theme . '.settings');

$logo_id = lab8_file('brand/logo.png', 'brand');
$favicon_id = lab8_file('brand/favicon.png', 'brand');
$logo_file = File::load($logo_id);
$favicon_file = File::load($favicon_id);
$logo_url = $logo_file->createFileUrl();
$favicon_url = $favicon_file->createFileUrl();

$theme_config
  ->set('features.logo', TRUE)
  ->set('logo.use_default', FALSE)
  ->set('logo.path', $logo_file->getFileUri())
  ->set('logo.url', $logo_url)
  ->set('features.favicon', TRUE)
  ->set('favicon.use_default', FALSE)
  ->set('favicon.path', $favicon_file->getFileUri())
  ->set('favicon.url', $favicon_url)
  ->set('favicon.mimetype', 'image/png')
  ->save();
echo "тема {$theme}: логотип {$logo_url}, фавикон {$favicon_url}\n";

\Drupal::configFactory()->getEditable('system.site')
  ->set('name', 'Город24')
  ->set('slogan', 'Город24 — каталог ноутбуков Lenovo')
  ->save();
echo "название сайта и слоган заданы\n";

// Стиль изображения для карточек каталога.
if (!ImageStyle::load('noutbuk_card')) {
  ImageStyle::create([
    'name' => 'noutbuk_card',
    'label' => 'Карточка ноутбука 400×250',
    'effects' => [
      'image_scale_and_crop' => [
        'id' => 'image_scale_and_crop',
        'weight' => 0,
        'data' => ['width' => 400, 'height' => 250],
      ],
    ],
  ])->save();
  echo "стиль изображения noutbuk_card: создан\n";
}
if (!ImageStyle::load('noutbuk_teaser')) {
  ImageStyle::create([
    'name' => 'noutbuk_teaser',
    'label' => 'Миниатюра каталога 240×150',
    'effects' => [
      'image_scale_and_crop' => [
        'id' => 'image_scale_and_crop',
        'weight' => 0,
        'data' => ['width' => 240, 'height' => 150],
      ],
    ],
  ])->save();
  echo "стиль изображения noutbuk_teaser: создан\n";
}

// ---------------------------------------------------------------------------
// 2. Таксономия «Lenovo» и тип материала «Ноутбук» (задание 5)
// ---------------------------------------------------------------------------
if (!Vocabulary::load('lenovo')) {
  Vocabulary::create([
    'vid' => 'lenovo',
    'name' => 'Lenovo',
    'description' => 'Серии ноутбуков Lenovo',
  ])->save();
  echo "словарь «Lenovo»: создан\n";
}

$series = ['серия IdeaPad', 'серия ThinkPad', 'серия Yoga', 'серия V'];
$term_ids = [];
foreach ($series as $name) {
  $found = $etm->getStorage('taxonomy_term')->loadByProperties(['name' => $name, 'vid' => 'lenovo']);
  $term = reset($found);
  if (!$term) {
    $term = Term::create(['vid' => 'lenovo', 'name' => $name]);
    $term->save();
    echo "  термин «{$name}»: создан\n";
  }
  $term_ids[$name] = (int) $term->id();
}

if (!NodeType::load('noutbuk')) {
  NodeType::create([
    'type' => 'noutbuk',
    'name' => 'Ноутбук',
    'description' => 'Карточка ноутбука каталога',
    'display_submitted' => FALSE,
  ])->save();
  echo "тип материала «Ноутбук»: создан\n";
}
else {
  $type = NodeType::load('noutbuk');
  if ($type->displaySubmitted()) {
    $type->set('display_submitted', FALSE)->save();
  }
}

// Удаляем термины прошлых прогонов, которых больше нет в словаре и на которые
// не ссылается ни один материал: иначе они попадут в меню «Каталог» в ЛР9.
$used = [];
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'noutbuk']) as $n) {
  foreach ($n->get('field_series')->getValue() as $v) {
    $used[(int) $v['target_id']] = TRUE;
  }
}
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'lenovo']) as $term) {
  if (!in_array($term->getName(), $series, TRUE) && empty($used[(int) $term->id()])) {
    $term->delete();
    echo "  устаревший термин «{$term->getName()}»: удалён\n";
  }
}

// Основное поле body: в Drupal 11 это обычное настраиваемое поле, а тип материала
// создан кодом — значит и хранилище, и привязку к типу надо создать самим.
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'type' => 'text_with_summary',
  ])->save();
  echo "  хранилище поля body: создано\n";
}
if (!FieldConfig::loadByName('node', 'noutbuk', 'body')) {
  FieldConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'bundle' => 'noutbuk',
    'label' => 'Описание',
    'required' => TRUE,
  ])->save();
  echo "  поле «Описание» (body): прикреплено\n";
}

// Серия — ссылка на термин словаря Lenovo, обязательная.
if (!FieldStorageConfig::loadByName('node', 'field_series')) {
  FieldStorageConfig::create([
    'field_name' => 'field_series',
    'entity_type' => 'node',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'noutbuk', 'field_series')) {
  FieldConfig::create([
    'field_name' => 'field_series',
    'entity_type' => 'node',
    'bundle' => 'noutbuk',
    'label' => 'Серия',
    'required' => TRUE,
    'settings' => ['handler_settings' => ['target_bundles' => ['lenovo' => 'lenovo']]],
  ])->save();
  echo "  поле «Серия»: создано\n";
}
else {
  $field = FieldConfig::loadByName('node', 'noutbuk', 'field_series');
  if (!$field->isRequired()) {
    $field->setRequired(TRUE)->save();
  }
}

// Фотография.
if (!FieldStorageConfig::loadByName('node', 'field_photo')) {
  FieldStorageConfig::create([
    'field_name' => 'field_photo',
    'entity_type' => 'node',
    'type' => 'image',
    'settings' => ['uri_scheme' => 'public'],
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'noutbuk', 'field_photo')) {
  FieldConfig::create([
    'field_name' => 'field_photo',
    'entity_type' => 'node',
    'bundle' => 'noutbuk',
    'label' => 'Фотография',
    'required' => TRUE,
    'settings' => ['file_extensions' => 'png jpg jpeg gif', 'alt_field' => TRUE, 'alt_field_required' => FALSE],
  ])->save();
  echo "  поле «Фотография»: создано\n";
}

// Номер модели отдельным полем — по заданию каталог идёт «с указанием номера модели».
if (!FieldStorageConfig::loadByName('node', 'field_model')) {
  FieldStorageConfig::create([
    'field_name' => 'field_model',
    'entity_type' => 'node',
    'type' => 'string',
    'settings' => ['max_length' => 64],
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'noutbuk', 'field_model')) {
  FieldConfig::create([
    'field_name' => 'field_model',
    'entity_type' => 'node',
    'bundle' => 'noutbuk',
    'label' => 'Номер модели',
    'required' => TRUE,
  ])->save();
  echo "  поле «Номер модели»: создано\n";
}

// Форма и вывод: без явных компонентов добавленные кодом поля не отображаются.
$display_repo = \Drupal::service('entity_display.repository');
$form_display = $display_repo->getFormDisplay('node', 'noutbuk', 'default');
$form_display
  ->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 0])
  ->setComponent('field_model', ['type' => 'string_textfield', 'weight' => 1])
  ->setComponent('field_series', ['type' => 'options_select', 'weight' => 2])
  ->setComponent('field_photo', ['type' => 'image_image', 'weight' => 3])
  ->save();

$view_display = $display_repo->getViewDisplay('node', 'noutbuk', 'default');
$view_display
  ->setComponent('field_photo', [
    'type' => 'image',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['image_style' => 'noutbuk_card', 'image_link' => 'content'],
  ])
  ->setComponent('field_model', ['type' => 'string', 'label' => 'inline', 'weight' => 1])
  ->setComponent('field_series', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 2])
  ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 3])
  ->save();

$teaser_display = $display_repo->getViewDisplay('node', 'noutbuk', 'teaser');
$teaser_display
  ->setComponent('field_photo', [
    'type' => 'image',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['image_style' => 'noutbuk_teaser', 'image_link' => 'content'],
  ])
  ->setComponent('field_model', ['type' => 'string', 'label' => 'inline', 'weight' => 1])
  ->setComponent('field_series', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 2])
  ->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'weight' => 3])
  ->save();

// Метатеги и SEO-анализ на форме материала: поля-виджеты надо добавить в отображение.
// Поле metatag создаётся модулем Metatag, поле Yoast SEO — обычная сущность.
if (\Drupal::moduleHandler()->moduleExists('metatag')) {
  // Внимание: имя 'metatag' занято вычисляемым базовым полем модуля, поэтому
  // редактируемое поле называется field_metatag (так же его создаёт Field UI.
  if (!FieldStorageConfig::loadByName('node', 'field_metatag')) {
    FieldStorageConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => 'node',
      'type' => 'metatag',
      'cardinality' => 1,
    ])->save();
    echo "  поле «Метатеги» (Metatag): создано\n";
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  if (!FieldConfig::loadByName('node', 'noutbuk', 'field_metatag')) {
    FieldConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => 'node',
      'bundle' => 'noutbuk',
      'label' => 'Метатеги',
      'required' => FALSE,
    ])->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  $form_display->setComponent('field_metatag', [
    'type' => 'metatag_firehose',
    'weight' => 20,
    'settings' => ['sidebar' => TRUE, 'use_details' => TRUE],
  ]);
}
if (\Drupal::moduleHandler()->moduleExists('yoast_seo')) {
  if (!FieldStorageConfig::loadByName('node', 'field_yoast_seo')) {
    FieldStorageConfig::create([
      'field_name' => 'field_yoast_seo',
      'entity_type' => 'node',
      'type' => 'yoast_seo',
      'cardinality' => 1,
    ])->save();
    echo "  поле Yoast SEO (Real-time SEO): создано\n";
  }
  // Определения полей кешируются: без сброса FieldConfig::create не видит хранилище.
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  if (!FieldConfig::loadByName('node', 'noutbuk', 'field_yoast_seo')) {
    FieldConfig::create([
      'field_name' => 'field_yoast_seo',
      'entity_type' => 'node',
      'bundle' => 'noutbuk',
      'label' => 'SEO-анализ',
      'required' => FALSE,
    ])->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  $form_display->setComponent('field_yoast_seo', [
    'type' => 'yoast_seo_widget',
    'weight' => 21,
    'settings' => ['edit_title' => TRUE, 'edit_description' => TRUE],
  ]);
}
$form_display->save();

// ---------------------------------------------------------------------------
// 3. Каталог из 10 ноутбуков с фотографиями (задание 5)
// ---------------------------------------------------------------------------
// Свежие определения полей: без сброса set() на только что созданных полях падает.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('entity_type.bundle.info')->clearCachedBundles();
$catalog = [
  ['photo' => '01_lenovo_ideapad_flex14.jpg', 'title' => 'Lenovo IdeaPad Flex 14', 'model' => 'Flex 14 (82B8)', 'series' => 'серия IdeaPad',
   'body' => 'Трансформер с поворотным экраном 14″ Full HD и сенсорным вводом. Процессор Intel Core i5-10210U, 8 ГБ ОЗУ, SSD 256 ГБ. Вес 1,5 кг.'],
  ['photo' => '02_lenovo_ideapad_yoga13.jpg', 'title' => 'Lenovo IdeaPad Yoga 13', 'model' => 'Yoga 13 (20172)', 'series' => 'серия IdeaPad',
   'body' => 'Один из первых ультрабуков-трансформеров: экран 13,3″ 1600×900 откидывается на 360°. Core i5, 4 ГБ ОЗУ, SSD 128 ГБ.'],
  ['photo' => '03_lenovo_thinkpad_e520.jpg', 'title' => 'Lenovo ThinkPad E520', 'model' => 'E520 (1143)', 'series' => 'серия ThinkPad',
   'body' => 'Рабочий ноутбук с матовым экраном 15,6″. Процессор Core i3-2310M, 4 ГБ ОЗУ, HDD 320 ГБ, полноразмерная клавиатура с цифровым блоком.'],
  ['photo' => '04_lenovo_thinkpad_t490.jpg', 'title' => 'Lenovo ThinkPad T490', 'model' => 'T490 (20N2)', 'series' => 'серия ThinkPad',
   'body' => 'Бизнес-серия T: экран 14″ IPS, Core i5-8265U, 16 ГБ ОЗУ, SSD 512 ГБ, сканер отпечатка и шторка камеры ThinkShutter.'],
  ['photo' => '05_lenovo_thinkpad_x1carbon.jpg', 'title' => 'Lenovo ThinkPad X1 Carbon', 'model' => 'X1 Carbon (3443)', 'series' => 'серия ThinkPad',
   'body' => 'Флагманский ультрабук в карбоновом корпусе, вес 1,36 кг. Экран 14″ 1600×900, Core i5, 8 ГБ ОЗУ, SSD 256 ГБ, подсветка клавиатуры.'],
  ['photo' => '06_lenovo_v330_15ikb.jpg', 'title' => 'Lenovo V330-15IKB', 'model' => 'V330-15IKB (81AX)', 'series' => 'серия V',
   'body' => 'Ноутбук для офиса и учёбы: 15,6″ Full HD, Core i5-8250U, 8 ГБ ОЗУ, SSD 256 ГБ, отдельный блок цифровых клавиш.'],
  ['photo' => '07_lenovo_yoga_3pro.jpg', 'title' => 'Lenovo Yoga 3 Pro', 'model' => 'Yoga 3 Pro (80HE)', 'series' => 'серия Yoga',
   'body' => 'Тонкий трансформер с шарниром-«часами»: 13,3″ QHD+, Core M-5Y71, 8 ГБ ОЗУ, SSD 256 ГБ. Работает в четырёх режимах.'],
  ['photo' => '08_lenovo_yoga_2pro.jpg', 'title' => 'Lenovo Yoga 2 Pro', 'model' => 'Yoga 2 Pro (20266)', 'series' => 'серия Yoga',
   'body' => 'Экран 13,3″ QHD+ 3200×1800, Core i7-4500U, 8 ГБ ОЗУ, SSD 512 ГБ. Режимы: ноутбук, планшет, подставка, палатка.'],
  ['photo' => '09_lenovo_yoga_530.jpg', 'title' => 'Lenovo Yoga 530', 'model' => 'Yoga 530-14ARR', 'series' => 'серия Yoga',
   'body' => 'Трансформер 14″ Full HD с сенсорным экраном, Ryzen 5 2500U, 8 ГБ ОЗУ, SSD 256 ГБ, встроенный стилус.'],
  ['photo' => '10_lenovo_thinkpad_x220.jpg', 'title' => 'Lenovo ThinkPad X220', 'model' => 'X220 (4291)', 'series' => 'серия ThinkPad',
   'body' => 'Классика бизнес-серии: 12,5″ IPS, Core i5-2520M, 4 ГБ ОЗУ, SSD 128 ГБ, легендарная семирядная клавиатура и трекпоинт.'],
];

foreach ($catalog as $i => $item) {
  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['title']));
  $found = $etm->getStorage('node')->loadByProperties(['type' => 'noutbuk', 'title' => $item['title']]);
  $node = reset($found) ?: Node::create(['type' => 'noutbuk', 'title' => $item['title']]);
  $is_new = $node->isNew();
  $photo_id = lab8_file('laptops/' . $item['photo'], 'laptops');
  // Соседняя модель для внутренней ссылки — SEO-анализ требует ссылки в тексте.
  $next = $catalog[($i + 1) % count($catalog)];
  $next_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $next['title']));
  $body_html = '<p><strong>' . $item['title'] . '</strong> — ' . $item['body'] . '</p>'
    . '<p>Смотрите также <a href="/noutbuki/' . $next_slug . '">' . $next['title'] . '</a> '
    . 'и официальный сайт <a href="https://www.lenovo.com/by/ru/">Lenovo</a>.</p>';
  $node->set('body', [
    'value' => $body_html,
    'format' => 'basic_html',
    'summary' => mb_substr($item['body'], 0, 120) . '…',
  ]);
  $node->set('field_model', $item['model']);
  $node->set('field_series', ['target_id' => $term_ids[$item['series']]]);
  $node->set('field_photo', ['target_id' => $photo_id, 'alt' => $item['title']]);
  $node->set('path', ['alias' => '/noutbuki/' . $slug]);
  $node->set('uid', 1);
  $node->set('status', 1);
  $node->set('promote', 1);
  // Метатеги и SEO-данные конкретной карточки — чтобы на форме были заполнены
  // поля Metatag и Yoast, а SEO-анализ считал оценку (демонстрация задания 4).
  if ($node->hasField('field_metatag')) {
    // Схема поля: единственная колонка value, внутри — карта «тег → значение».
    $node->set('field_metatag', [
      'value' => [
        'title' => $item['title'] . ' — каталог Город24',
        'description' => mb_substr($item['body'], 0, 150),
      ],
    ]);
  }
  if ($node->hasField('field_yoast_seo')) {
    $node->set('field_yoast_seo', [
      'focus_keyword' => $item['title'],
      'title' => $item['title'] . ' — каталог ноутбуков Город24',
      'description' => mb_substr($item['body'], 0, 150),
    ]);
  }
  $node->save();
  echo ($is_new ? '  ноутбук добавлен: ' : '  ноутбук обновлён: ') . $node->getTitle() . " (node/{$node->id()})\n";
}

// Материалы прошлых прогонов, которых нет в каталоге (например «Lenovo IdeaPad серия N №1»),
// удаляем: иначе в каталоге остаются пустые карточки без фото.
$titles = array_column($catalog, 'title');
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'noutbuk']) as $node) {
  if (!in_array($node->getTitle(), $titles, TRUE)) {
    $node->delete();
    echo '  устаревший материал удалён: ' . $node->getTitle() . "\n";
  }
}

// Главная страница — каталог (тизеры с фотографиями).
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node')->save();

// ---------------------------------------------------------------------------
// 4. Pathauto: паттерны алиасов (задание 4)
// ---------------------------------------------------------------------------
$patterns = [
  'noutbuk' => ['label' => 'Ноутбуки', 'bundle' => 'noutbuk', 'pattern' => 'noutbuki/[node:title]'],
  'page' => ['label' => 'Страницы', 'bundle' => 'page', 'pattern' => 'stranicy/[node:title]'],
  'article' => ['label' => 'Статьи', 'bundle' => 'article', 'pattern' => 'novosti/[node:title]'],
];

foreach ($patterns as $id => $p) {
  $pattern = PathautoPattern::load($id);
  if (!$pattern) {
    $pattern = PathautoPattern::create([
      'id' => $id,
      'label' => $p['label'],
      'type' => 'canonical_entities:node',
      'pattern' => $p['pattern'],
      'selection_criteria' => [
        'entity_bundle:node' => [
          'id' => 'entity_bundle:node',
          'bundles' => [$p['bundle'] => $p['bundle']],
          'negate' => FALSE,
          'context_mapping' => ['node' => 'node'],
        ],
      ],
      'selection_logic' => 'and',
      'weight' => 0,
    ]);
    $pattern->save();
    echo "паттерн Pathauto «{$p['label']}»: {$p['pattern']}\n";
  }
}

// Пересчитать алиасы у существующих материалов (идемпотентно).
$generator = \Drupal::service('pathauto.generator');
foreach ($etm->getStorage('node')->loadMultiple() as $node) {
  $generator->updateEntityAlias($node, 'bulkupdate');
}
echo "алиасы Pathauto: пересчитаны\n";

// ---------------------------------------------------------------------------
// 5. Метатеги Metatag + SEO-анализ yoast_seo (задание 4)
// ---------------------------------------------------------------------------
$metatag = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.node');
$metatag->set('tags.title', '[node:title] | [site:name]')
  ->set('tags.description', '[node:summary]')
  ->set('tags.abstract', '[node:summary]')
  ->save();
echo "метатеги по умолчанию для материалов: title/description заданы\n";

$metatag_global = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.global');
$metatag_global->set('tags.description', 'Город24 — каталог ноутбуков Lenovo: характеристики, фотографии, цены.')
  ->set('tags.keywords', 'Lenovo, ноутбуки, каталог, IdeaPad, ThinkPad, Yoga')
  ->save();

// ---------------------------------------------------------------------------
// 6. Webform: форма обратной связи — демонстрация работы модуля (задание 3)
// ---------------------------------------------------------------------------
if (\Drupal::moduleHandler()->moduleExists('webform') && !Webform::load('obratnaya_svyaz')) {
  $elements = <<<'YAML'
name:
  '#type': textfield
  '#title': 'Ваше имя'
  '#required': true
email:
  '#type': email
  '#title': 'E-mail'
  '#required': true
message:
  '#type': textarea
  '#title': 'Сообщение'
  '#rows': 5
  '#required': true
actions:
  '#type': webform_actions
  '#title': 'Отправить'
YAML;
  Webform::create([
    'id' => 'obratnaya_svyaz',
    'title' => 'Обратная связь',
    'description' => 'Вопрос по каталогу ноутбуков Lenovo',
    'elements' => $elements,
    'status' => 'open',
  ])->save();
  echo "веб-форма «Обратная связь»: создана (/form/obratnaya-svyaz)\n";
}

// Блок с формой на главной — чтобы форма была видна без перехода.
if (\Drupal::moduleHandler()->moduleExists('webform')) {
  $webform_block = \Drupal\block\Entity\Block::load('lab8_webform_block');
  if (!$webform_block) {
    $webform_block = \Drupal\block\Entity\Block::create([
      'id' => 'lab8_webform_block',
      'plugin' => 'webform_block',
      'region' => 'footer',
      'theme' => $theme,
      'weight' => 0,
      'visibility' => [],
    ]);
  }
  $webform_block
    ->set('settings', [
      'id' => 'webform_block',
      'label' => 'Обратная связь',
      'label_display' => 'visible',
      'provider' => 'webform',
      'webform_id' => 'obratnaya_svyaz',
      'default_data' => '',
      'redirect' => FALSE,
      'lazy' => FALSE,
      'webform' => 'obratnaya_svyaz',
    ])
    ->save();
  echo "блок веб-формы: размещён в регионе footer\n";
}

// Файлы созданы от root (скрипт запускается из drush), а веб-сервер работает
// под www-data: без смены владельца производные картинки отдают 500.
$files_dir = $fs->realpath('public://');
exec('chown -R www-data:www-data ' . escapeshellarg($files_dir));

echo "ЛР8: конфигурация завершена\n";
