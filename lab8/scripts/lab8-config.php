<?php
/**
 * Конфигурация ЛР8 «Знакомство с Drupal» — городской новостной портал «Город24».
 *
 * Скрипт идемпотентен: повторный запуск не создаёт дубликатов.
 * Запуск: drush php:script /opt/drupal/web/scripts/lab8-config.php
 *
 * Что настраивает (по пунктам задания labs/Drupal/Drupal_1.pdf):
 *  3.1 — тема, логотип, название, фавикон, слоган;
 *  3.3 — модули Admin Toolbar, Pathauto, Token, Metatag, Webform, yoast_seo;
 *  3.4 — алиасы Pathauto (+ ctools, Token), метатеги Metatag + SEO-анализ yoast_seo;
 *  3.5 — словарь «Lenovo», тип материала «Ноутбук» с полем термина и фото,
 *        каталог из 10 ноутбуков (раздел портала «Техника»).
 *
 * Тематика сайта: городской новостной портал. Лента новостей — главная страница,
 * каталог ноутбуков — отдельный раздел (требование задания 5).
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
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\views\Entity\View;
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

/** Создаёт словарь таксономии, если его нет. */
function lab8_vocabulary(string $vid, string $name, string $description): void {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $name, 'description' => $description])->save();
    echo "словарь «{$name}»: создан\n";
  }
}

/** Создаёт термин словаря и возвращает его id. */
function lab8_term(string $vid, string $name): int {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $found = $storage->loadByProperties(['name' => $name, 'vid' => $vid]);
  $term = reset($found);
  if (!$term) {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    echo "  термин «{$name}»: создан\n";
  }
  return (int) $term->id();
}

/** Создаёт пункт меню, если такого пункта с таким заголовком ещё нет. */
function lab8_menu(string $menu, string $title, string $uri, string $parent = '', int $weight = 0): MenuLinkContent {
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

/** Создаёт стиль изображения с кропом. */
function lab8_image_style(string $name, string $label, int $width, int $height): void {
  if (!ImageStyle::load($name)) {
    ImageStyle::create([
      'name' => $name,
      'label' => $label,
      'effects' => [
        'image_scale_and_crop' => [
          'id' => 'image_scale_and_crop',
          'weight' => 0,
          'data' => ['width' => $width, 'height' => $height],
        ],
      ],
    ])->save();
    echo "стиль изображения {$name}: создан\n";
  }
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
  ->set('slogan', 'Новости Минска: город, транспорт, культура')
  ->save();
echo "название сайта и слоган заданы\n";

// Стили изображений: карточки каталога и иллюстрации новостей.
lab8_image_style('noutbuk_card', 'Карточка ноутбука 400×250', 400, 250);
lab8_image_style('noutbuk_teaser', 'Миниатюра каталога 240×150', 240, 150);
lab8_image_style('news_teaser', 'Иллюстрация новости в ленте 360×220', 360, 220);
lab8_image_style('news_full', 'Иллюстрация новости на странице 760×430', 760, 430);

// ---------------------------------------------------------------------------
// 2. Таксономия: рубрики новостей и серии ноутбуков
// ---------------------------------------------------------------------------
lab8_vocabulary('rubriki', 'Рубрики новостей', 'Рубрики городского портала');
$rubrics = ['Город', 'Транспорт', 'Происшествия', 'Культура', 'Спорт', 'Технологии'];
$rubric_ids = [];
foreach ($rubrics as $name) {
  $rubric_ids[$name] = lab8_term('rubriki', $name);
}
// Термины прошлых прогонов, которых больше нет в списке рубрик.
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'rubriki']) as $term) {
  if (!in_array($term->getName(), $rubrics, TRUE)) {
    $term->delete();
    echo "  устаревшая рубрика «{$term->getName()}»: удалена\n";
  }
}

lab8_vocabulary('lenovo', 'Lenovo', 'Серии ноутбуков Lenovo');
$series = ['серия IdeaPad', 'серия ThinkPad', 'серия Yoga', 'серия V'];
$term_ids = [];
foreach ($series as $name) {
  $term_ids[$name] = lab8_term('lenovo', $name);
}

// ---------------------------------------------------------------------------
// 3. Тип материала «Новость» — основа ленты портала
// ---------------------------------------------------------------------------
if (!NodeType::load('news')) {
  NodeType::create([
    'type' => 'news',
    'name' => 'Новость',
    'description' => 'Новость городского портала: лид, рубрика, иллюстрация',
    'display_submitted' => FALSE,
  ])->save();
  echo "тип материала «Новость»: создан\n";
}
else {
  $news_type = NodeType::load('news');
  if ($news_type->displaySubmitted()) {
    $news_type->set('display_submitted', FALSE)->save();
  }
}

if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'type' => 'text_with_summary',
  ])->save();
  echo "  хранилище поля body: создано\n";
}

// Поля новости: лид, рубрика, иллюстрация.
$news_fields = [
  'body' => [
    'type' => 'text_with_summary',
    'label' => 'Текст новости',
    'required' => TRUE,
  ],
  'field_lead' => [
    'type' => 'string',
    'label' => 'Лид',
    'required' => TRUE,
    'storage' => ['settings' => ['max_length' => 255]],
  ],
  'field_rubric' => [
    'type' => 'entity_reference',
    'label' => 'Рубрика',
    'required' => TRUE,
    'storage' => ['settings' => ['target_type' => 'taxonomy_term']],
    'settings' => ['handler_settings' => ['target_bundles' => ['rubriki' => 'rubriki']]],
  ],
  'field_image' => [
    'type' => 'image',
    'label' => 'Иллюстрация',
    'required' => TRUE,
    'storage' => ['settings' => ['uri_scheme' => 'public']],
    'settings' => ['file_extensions' => 'png jpg jpeg', 'alt_field' => TRUE, 'alt_field_required' => FALSE],
  ],
];
foreach ($news_fields as $field_name => $spec) {
  if ($field_name !== 'body' && !FieldStorageConfig::loadByName('node', $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $spec['type'],
      'cardinality' => 1,
    ] + ($spec['storage'] ?? []))->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  if (!FieldConfig::loadByName('node', 'news', $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'news',
      'label' => $spec['label'],
      'required' => $spec['required'],
    ] + ($spec['settings'] ?? []))->save();
    echo "  поле «{$spec['label']}» у типа «Новость»: создано\n";
  }
}

// Форма и вывод новости.
$display_repo = \Drupal::service('entity_display.repository');
$news_form = $display_repo->getFormDisplay('node', 'news', 'default');
$news_form
  ->setComponent('field_lead', ['type' => 'string_textfield', 'weight' => -5])
  ->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 0])
  ->setComponent('field_rubric', ['type' => 'options_select', 'weight' => 1])
  ->setComponent('field_image', ['type' => 'image_image', 'weight' => 2])
  ->save();

$news_view = $display_repo->getViewDisplay('node', 'news', 'default');
$news_view
  ->setComponent('field_image', [
    'type' => 'image',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['image_style' => 'news_full', 'image_link' => ''],
  ])
  ->setComponent('field_lead', ['type' => 'string', 'label' => 'hidden', 'weight' => 1])
  ->setComponent('field_rubric', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 2])
  ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 3])
  ->save();

$news_teaser = $display_repo->getViewDisplay('node', 'news', 'teaser');
$news_teaser
  ->setComponent('field_image', [
    'type' => 'image',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['image_style' => 'news_teaser', 'image_link' => 'content'],
  ])
  ->setComponent('field_lead', ['type' => 'string', 'label' => 'hidden', 'weight' => 1])
  ->setComponent('field_rubric', ['type' => 'entity_reference_label', 'label' => 'inline', 'weight' => 2])
  ->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'weight' => 3])
  ->save();

// Метатеги и SEO-анализ у новости (задание 4).
if (\Drupal::moduleHandler()->moduleExists('metatag')) {
  if (!FieldStorageConfig::loadByName('node', 'field_metatag')) {
    FieldStorageConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => 'node',
      'type' => 'metatag',
      'cardinality' => 1,
    ])->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  if (!FieldConfig::loadByName('node', 'news', 'field_metatag')) {
    FieldConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => 'node',
      'bundle' => 'news',
      'label' => 'Метатеги',
      'required' => FALSE,
    ])->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  $news_form->setComponent('field_metatag', [
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
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  if (!FieldConfig::loadByName('node', 'news', 'field_yoast_seo')) {
    FieldConfig::create([
      'field_name' => 'field_yoast_seo',
      'entity_type' => 'node',
      'bundle' => 'news',
      'label' => 'SEO-анализ',
      'required' => FALSE,
    ])->save();
  }
  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  $news_form->setComponent('field_yoast_seo', [
    'type' => 'yoast_seo_widget',
    'weight' => 21,
    'settings' => ['edit_title' => TRUE, 'edit_description' => TRUE],
  ]);
}
$news_form->save();

// ---------------------------------------------------------------------------
// 4. Тип материала «Ноутбук» — раздел портала «Техника» (задание 5)
// ---------------------------------------------------------------------------
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

// Термины серий, на которые не ссылается ни один материал, не нужны.
$used = [];
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'noutbuk']) as $n) {
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

if (\Drupal::moduleHandler()->moduleExists('metatag')) {
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
// 5. Лента новостей портала (12 материалов в шести рубриках)
// ---------------------------------------------------------------------------
// Свежие определения полей: без сброса set() на только что созданных полях падает.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('entity_type.bundle.info')->clearCachedBundles();

$news = [
  [
    'title' => 'Минск переводит ещё 40 автобусов на электропривод',
    'rubric' => 'Транспорт',
    'image' => 'news04.jpg',
    'lead' => 'Обновление парка завершат до конца года: новые машины выйдут на маршруты, связывающие спальные районы с центром.',
    'body' => 'Городской парк пополнится 40 электробусами до конца года. Машины распределят по маршрутам, которые проходят через центр и связывают спальные районы с вокзалом. Зарядные станции установят на конечных остановках, время подзарядки — около 20 минут. По расчётам транспортного управления, новая техника снизит расходы на обслуживание на четверть.',
  ],
  [
    'title' => 'На Свислочи открыли сезон лодочных прогулок',
    'rubric' => 'Город',
    'image' => 'news10.jpg',
    'lead' => 'Прокат лодок и катамаранов работает ежедневно с 10:00 до 21:00, на набережной оборудовали три новых причала.',
    'body' => 'Сезон прогулок по Свислочи открыт: на набережной работают три причала, где можно взять лодку или катамаран. Прокат идёт ежедневно с 10:00 до 21:00, для школьников действует скидка. Спасатели напоминают о жилетах и просят не выходить на воду в ветреную погоду.',
  ],
  [
    'title' => 'Национальная библиотека продлила вечерние часы',
    'rubric' => 'Культура',
    'image' => 'news01.jpg',
    'lead' => 'С октября читальные залы открыты до 22:00, а по субботам проходит бесплатная экскурсия по смотровой площадке.',
    'body' => 'С октября Национальная библиотека работает до 22:00 — продление связано с нагрузкой в сессию. По субботам в 15:00 проходит бесплатная экскурсия, включая смотровую площадку. Запись на экскурсию открыта на сайте библиотеки, группы до 25 человек.',
  ],
  [
    'title' => 'В Верхнем городе отреставрировали фасады трёх кварталов',
    'rubric' => 'Город',
    'image' => 'news12.jpg',
    'lead' => 'Реставраторы вернули исторический цвет фасадам и восстановили лепнину, работы шли полтора года.',
    'body' => 'В Верхнем городе завершена реставрация фасадов трёх кварталов: восстановлена лепнина, возвращён исторический цвет, отремонтированы балконы. Работы шли полтора года по архивным фотографиям. Пешеходную часть улиц замостили заново, сохранив рисунок исторической мостовой.',
  ],
  [
    'title' => 'БГУ открыл набор на курсы анализа данных',
    'rubric' => 'Технологии',
    'image' => 'news02.jpg',
    'lead' => 'Программа рассчитана на 4 месяца, занятия проходят вечером, часть мест — бюджетная для студентов.',
    'body' => 'Факультет прикладной математики открыл набор на курсы анализа данных. Программа длится четыре месяца: Python, статистика, визуализация и работа с большими таблицами. Занятия вечером два раза в неделю, часть мест бюджетная для студентов университета. Заявки принимают до конца месяца.',
  ],
  [
    'title' => 'В парке у библиотеки высадили 120 деревьев',
    'rubric' => 'Город',
    'image' => 'news08.jpg',
    'lead' => 'Акция собрала больше трёхсот участников: липы и клёны высаживали сотрудники парка вместе с горожанами.',
    'body' => 'В парке у Национальной библиотеки высадили 120 лип и клёнов. В акции участвовали более трёхсот человек — сотрудники парка, студенты и местные жители. Молодые деревья закрепили на растяжках и обеспечили автополив на первый сезон, уход продолжится два года.',
  ],
  [
    'title' => 'Проспект Рокоссовского: новая схема движения',
    'rubric' => 'Транспорт',
    'image' => 'news09.jpg',
    'lead' => 'Выделенную полосу для общественного транспорта продлили до кольцевой, парковку у остановок убрали.',
    'body' => 'На проспекте Рокоссовского изменилась организация движения: выделенная полоса для автобусов продлена до кольцевой дороги, на трёх перекрёстках добавлен поворот направо с отдельной секцией. Парковку у остановок убрали, чтобы не мешать общественному транспорту. Схема действует с начала недели, первые дни на месте дежурят инспекторы.',
  ],
  [
    'title' => 'Городской театр готовит премьеру по документальным историям',
    'rubric' => 'Культура',
    'image' => 'news03.jpg',
    'lead' => 'В основу спектакля легли интервью с жителями минских дворов, премьера назначена на середину ноября.',
    'body' => 'Городской театр выпускает премьеру по документальным историям: в основу легли интервью с жителями минских дворов, записанные за полгода. В постановке заняты восемь актёров и хор, сценография повторяет планировку типовой хрущёвки. Премьера — в середине ноября, билеты уже в продаже.',
  ],
  [
    'title' => 'Коммунальщики устранили прорыв трубы на Немиге',
    'rubric' => 'Происшествия',
    'image' => 'news06.jpg',
    'lead' => 'Авария на теплотрассе оставила без горячей воды два квартала, движение по улице восстановили вечером.',
    'body' => 'Утром на Немиге произошёл прорыв трубы горячего водоснабжения: без горячей воды остались два квартала, движение по одной полосе перекрыли. Ремонтные бригады заменили участок трубопровода длиной 12 метров. К вечеру подача воды восстановлена, движение открыто, причиной назвали износ трубы.',
  ],
  [
    'title' => 'Минские школьники собрали робота-курьера для школьной столовой',
    'rubric' => 'Технологии',
    'image' => 'news07.jpg',
    'lead' => 'Проект кружка робототехники: аппарат развозит подносы по залу и объезжает препятствия по разметке.',
    'body' => 'Ученики инженерного класса собрали робота-курьера для школьной столовой. Аппарат весом 18 кг развозит подносы по залу, ориентируется по нанесённой разметке и объезжает препятствия с помощью ультразвуковых датчиков. Проект занял второе место на городском конкурсе технического творчества.',
  ],
  [
    'title' => 'На Немиге заливают каток',
    'rubric' => 'Спорт',
    'image' => 'news11.jpg',
    'lead' => 'Ледовую площадку обещают открыть к концу месяца, прокат коньков будет бесплатным для школьников.',
    'body' => 'На Немиге начали заливать открытый каток: площадку готовят к концу месяца, рядом обустраивают тёплую раздевалку и пункт проката. Для школьников прокат коньков будет бесплатным по будням до 16:00. Вечером планируют массовые катания с музыкой и работу инструкторов.',
  ],
  [
    'title' => 'Старый город: пешеходные экскурсии по выходным',
    'rubric' => 'Культура',
    'image' => 'news05.jpg',
    'lead' => 'Двухчасовой маршрут проходит по дворам Троицкого предместья, экскурсии ведут городские гиды бесплатно.',
    'body' => 'По выходным в Старом городе проходят бесплатные пешеходные экскурсии: двухчасовой маршрут начинается у ратуши и идёт через дворы Троицкого предместья. Группы собираются в 11:00 и 15:00, записываться заранее не нужно. Гиды рассказывают о послевоенной застройке и о том, как менялся облик кварталов.',
  ],
  [
    'title' => 'Свислочь очистили после паводка: собрали 12 тонн мусора',
    'rubric' => 'Происшествия',
    'image' => 'news11.jpg',
    'lead' => 'Коммунальные службы работали две недели, на береговой линии восстановили газоны и дорожки.',
    'body' => 'После весеннего паводка с берегов Свислочи собрали 12 тонн мусора и принесённых течением веток. Коммунальные службы работали две недели: чистили русло у мостов, восстанавливали газоны и пешеходные дорожки. Часть набережной открыли заново, купальный сезон начнётся по графику.',
  ],
];

$news_titles = [];
foreach ($news as $i => $item) {
  $news_titles[] = $item['title'];
  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['title']));
  $image_id = lab8_file('news/' . $item['image'], 'news');
  // Внутренняя и внешняя ссылки в тексте — этого требует SEO-анализ и логика портала.
  $next = $news[($i + 1) % count($news)];
  $next_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $next['title']));
  $body_html = '<p>' . $item['body'] . '</p>'
    . '<p>Читайте также: <a href="/novosti/' . $next_slug . '">' . $next['title'] . '</a>. '
    . 'Источник городских данных: <a href="https://minsk.gov.by/">Минский городской исполнительный комитет</a>.</p>';

  $found = $etm->getStorage('node')->loadByProperties(['type' => 'news', 'title' => $item['title']]);
  $node = reset($found) ?: Node::create(['type' => 'news', 'title' => $item['title']]);
  $is_new = $node->isNew();
  $node->set('body', [
    'value' => $body_html,
    'format' => 'basic_html',
    'summary' => $item['lead'],
  ]);
  $node->set('field_lead', $item['lead']);
  $node->set('field_rubric', ['target_id' => $rubric_ids[$item['rubric']]]);
  $node->set('field_image', ['target_id' => $image_id, 'alt' => $item['title']]);
  $node->set('uid', 1);
  $node->set('status', 1);
  // Лента на главной — это промотированные новости.
  $node->set('promote', 1);
  $node->set('sticky', 0);
  if ($node->hasField('field_metatag')) {
    $node->set('field_metatag', [
      'value' => [
        'title' => $item['title'] . ' — Город24',
        'description' => mb_substr($item['lead'], 0, 160),
      ],
    ]);
  }
  if ($node->hasField('field_yoast_seo')) {
    $node->set('field_yoast_seo', [
      'focus_keyword' => $item['title'],
      'title' => $item['title'] . ' — новости Город24',
      'description' => mb_substr($item['lead'], 0, 160),
    ]);
  }
  $node->save();
  echo ($is_new ? '  новость добавлена: ' : '  новость обновлена: ') . $node->getTitle() . " (node/{$node->id()})\n";
}

// Новости прошлых прогонов, которых нет в списке, удаляем.
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'news']) as $node) {
  if (!in_array($node->getTitle(), $news_titles, TRUE)) {
    $node->delete();
    echo '  устаревшая новость удалена: ' . $node->getTitle() . "\n";
  }
}

// ---------------------------------------------------------------------------
// 6. Каталог из 10 ноутбуков — раздел «Техника» (задание 5)
// ---------------------------------------------------------------------------
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
  $node->set('uid', 1);
  $node->set('status', 1);
  // Каталог — отдельный раздел, в ленту новостей он не попадает.
  $node->set('promote', 0);
  if ($node->hasField('field_metatag')) {
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

// Материалы прошлых прогонов, которых нет в каталоге, удаляем.
$titles = array_column($catalog, 'title');
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'noutbuk']) as $node) {
  if (!in_array($node->getTitle(), $titles, TRUE)) {
    $node->delete();
    echo '  устаревший материал удалён: ' . $node->getTitle() . "\n";
  }
}

// Главная страница — лента новостей (промотированные материалы).
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node')->save();

// ---------------------------------------------------------------------------
// 7. Views: страница раздела «Техника» со всеми 10 карточками
// ---------------------------------------------------------------------------
$view = View::load('katalog');
if (!$view) {
  $view = View::create([
    'id' => 'katalog',
    'label' => 'Каталог ноутбуков',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'status' => TRUE,
  ]);
}
$view->set('description', 'Каталог ноутбуков Lenovo — раздел портала «Техника».');
$default_options = [
  'title' => 'Каталог ноутбуков Lenovo',
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 10]],
  'exposed_form' => ['type' => 'basic', 'options' => []],
  'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => TRUE]],
  'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser', 'relationship' => 'none']],
  'access' => ['type' => 'perm', 'perm' => 'access content'],
  'sorts' => [
    'title' => [
      'id' => 'title',
      'table' => 'node_field_data',
      'field' => 'title',
      'entity_type' => 'node',
      'entity_field' => 'title',
      'plugin_id' => 'standard',
      'order' => 'ASC',
      'expose' => ['operator' => ''],
    ],
  ],
  'filters' => [
    'status' => [
      'id' => 'status',
      'table' => 'node_field_data',
      'field' => 'status',
      'entity_type' => 'node',
      'entity_field' => 'status',
      'plugin_id' => 'boolean',
      'value' => '1',
      'group' => 1,
      'expose' => ['operator' => ''],
    ],
    'type' => [
      'id' => 'type',
      'table' => 'node_field_data',
      'field' => 'type',
      'entity_type' => 'node',
      'entity_field' => 'type',
      'plugin_id' => 'bundle',
      'value' => ['noutbuk' => 'noutbuk'],
      'group' => 1,
      'expose' => ['operator' => ''],
    ],
  ],
];
$view->set('display', [
  'default' => [
    'display_plugin' => 'default',
    'id' => 'default',
    'display_title' => 'Default',
    'position' => 0,
    'display_options' => $default_options,
  ],
  'page_1' => [
    'display_plugin' => 'page',
    'id' => 'page_1',
    'display_title' => 'Страница каталога',
    'position' => 1,
    'display_options' => [
      'path' => 'noutbuki',
      'menu' => ['type' => 'none'],
    ],
  ],
]);
$view->save();
echo "представление katalog: страница /noutbuki (10 карточек)\n";

// ---------------------------------------------------------------------------
// 8. Pathauto: паттерны алиасов (задание 4)
// ---------------------------------------------------------------------------
$patterns = [
  'news' => ['label' => 'Новости', 'bundle' => 'news', 'pattern' => 'novosti/[node:title]'],
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

// Алиас рубрики: /rubrika/<название>.
$rubric_pattern = PathautoPattern::load('rubrika');
if (!$rubric_pattern) {
  PathautoPattern::create([
    'id' => 'rubrika',
    'label' => 'Рубрики',
    'type' => 'canonical_entities:taxonomy_term',
    'pattern' => 'rubrika/[term:name]',
    'selection_criteria' => [
      'entity_bundle:taxonomy_term' => [
        'id' => 'entity_bundle:taxonomy_term',
        'bundles' => ['rubriki' => 'rubriki'],
        'negate' => FALSE,
        'context_mapping' => ['taxonomy_term' => 'taxonomy_term'],
      ],
    ],
    'selection_logic' => 'and',
    'weight' => 0,
  ])->save();
  echo "паттерн Pathauto «Рубрики»: rubrika/[term:name]\n";
}

// Пересчитать алиасы у существующих материалов (идемпотентно).
// Алиасы, выставленные вручную (в том числе прошлыми прогонами), Pathauto не
// трогает, поэтому сначала удаляем их, потом генератор строит алиас из шаблона.
$generator = \Drupal::service('pathauto.generator');
$alias_storage = $etm->getStorage('path_alias');
foreach ($etm->getStorage('node')->loadMultiple() as $node) {
  // Флаг pathauto: если материал сохранялся с ручным алиасом, Pathauto его
  // пропускает — возвращаем состояние «алиас генерировать».
  if ($node->hasField('path')) {
    $node->set('path', ['pathauto' => 1]);
  }
  foreach ($alias_storage->loadByProperties(['path' => '/node/' . $node->id()]) as $alias) {
    $alias->delete();
  }
  $generator->updateEntityAlias($node, 'bulkupdate');
}
foreach ($etm->getStorage('taxonomy_term')->loadMultiple() as $term) {
  $generator->updateEntityAlias($term, 'bulkupdate');
}
echo "алиасы Pathauto: пересчитаны\n";

// ---------------------------------------------------------------------------
// 9. Метатеги Metatag + SEO-анализ yoast_seo (задание 4)
// ---------------------------------------------------------------------------
$metatag = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.node');
$metatag->set('tags.title', '[node:title] | [site:name]')
  ->set('tags.description', '[node:summary]')
  ->set('tags.abstract', '[node:summary]')
  ->save();
echo "метатеги по умолчанию для материалов: title/description заданы\n";

$metatag_global = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.global');
$metatag_global->set('tags.description', 'Город24 — новости Минска: город, транспорт, культура, спорт и технологии.')
  ->set('tags.keywords', 'Минск, новости, город, транспорт, культура, спорт, технологии')
  ->save();

// ---------------------------------------------------------------------------
// 10. Webform: «Прислать новость» — демонстрация работы модуля (задание 3)
// ---------------------------------------------------------------------------
if (\Drupal::moduleHandler()->moduleExists('webform') && !Webform::load('prislat_novost')) {
  $elements = <<<'YAML'
name:
  '#type': textfield
  '#title': 'Ваше имя'
  '#required': true
email:
  '#type': email
  '#title': 'E-mail для связи'
  '#required': true
rubric:
  '#type': select
  '#title': 'Рубрика'
  '#options':
    gorod: Город
    transport: Транспорт
    proisshestviya: Происшествия
    kultura: Культура
    sport: Спорт
    tehnologii: Технологии
  '#required': true
message:
  '#type': textarea
  '#title': 'Текст новости'
  '#rows': 6
  '#required': true
actions:
  '#type': webform_actions
  '#title': 'Отправить в редакцию'
YAML;
  Webform::create([
    'id' => 'prislat_novost',
    'title' => 'Прислать новость',
    'description' => 'Форма для читателей портала «Город24»',
    'elements' => $elements,
    'status' => 'open',
  ])->save();
  echo "веб-форма «Прислать новость»: создана (/form/prislat-novost)\n";
}
// Форма из первой версии лабы больше не нужна.
if ($old = Webform::load('obratnaya_svyaz')) {
  $old->delete();
  echo "веб-форма «Обратная связь»: удалена\n";
}

// Блок с формой в футере — чтобы форма была видна без перехода.
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
      'label' => 'Прислать новость',
      'label_display' => 'visible',
      'provider' => 'webform',
      'webform_id' => 'prislat_novost',
      'default_data' => '',
      'redirect' => FALSE,
      'lazy' => FALSE,
      'webform' => 'prislat_novost',
    ])
    ->save();
  echo "блок веб-формы: размещён в регионе footer\n";
}

// ---------------------------------------------------------------------------
// 11. Навигация портала: главное меню (рубрики и раздел «Техника»)
// ---------------------------------------------------------------------------
lab8_menu('main', 'Главная', 'internal:/', '', 0);
$rubrics_menu = lab8_menu('main', 'Рубрики', 'route:<nolink>', '', 1);
$rubrics_parent = 'menu_link_content:' . $rubrics_menu->uuid();
$weight = 0;
foreach ($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'rubriki']) as $term) {
  lab8_menu('main', $term->getName(), 'internal:/taxonomy/term/' . $term->id(), $rubrics_parent, $weight++);
}
lab8_menu('main', 'Техника', 'internal:/noutbuki', '', 2);
echo "главное меню: главная, рубрики (6 терминов), раздел «Техника»\n";

// Файлы созданы от root (скрипт запускается из drush), а веб-сервер работает
// под www-data: без смены владельца производные картинки отдают 500.
$files_dir = $fs->realpath('public://');
exec('chown -R www-data:www-data ' . escapeshellarg($files_dir));

echo "ЛР8: конфигурация завершена\n";
