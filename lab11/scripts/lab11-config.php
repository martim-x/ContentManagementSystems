<?php
/**
 * Конфигурация ЛР11 «Расширение функциональности: модули и Views» —
 * labs/Drupal/Drupal_4.pdf.
 *
 * Скрипт идемпотентен: повторный запуск не создаёт дубликатов.
 * Запуск: drush php:script /opt/drupal/web/scripts/lab11-config.php
 *
 * Что настраивает:
 *  2 — слоган и его программный вывод блоком модуля gorod24_slogan;
 *  3 — размещение блока модуля news: только главная, только авторизованным;
 *  4 — тип материала «Статья», поле цены и вычисляемое поле «Цена со скидкой»;
 *  5 — представление статей таблицей с фильтром по дате публикации.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;

$etm = \Drupal::entityTypeManager();
$theme = 'bootstrap5';

// ---------------------------------------------------------------------------
// 2. Слоган: смена конфигурации и программный вывод (задание 2)
// ---------------------------------------------------------------------------
\Drupal::configFactory()->getEditable('system.site')
  ->set('slogan', 'Город24: новости Минска каждый день')
  ->save();
echo "слоган сайта изменён модулем: «Город24: новости Минска каждый день»\n";

$slogan_block = Block::load('gorod24_slogan_block');
if (!$slogan_block) {
  $slogan_block = Block::create([
    'id' => 'gorod24_slogan_block',
    'theme' => $theme,
    'plugin' => 'gorod24_slogan_block',
  ]);
}
$slogan_block
  ->set('region', 'sidebar_first')
  ->set('weight', 0)
  ->set('status', TRUE)
  ->set('settings', [
    'id' => 'gorod24_slogan_block',
    'label' => 'Слоган портала',
    'label_display' => 'visible',
    'provider' => 'gorod24_slogan',
  ])
  ->set('visibility', [])
  ->save();
echo "блок слогана: регион sidebar_first\n";

// ---------------------------------------------------------------------------
// 3. Модуль news: блок только на главной и только авторизованным (задание 3)
// ---------------------------------------------------------------------------
$news_block = Block::load('news_news_block');
if (!$news_block) {
  $news_block = Block::create([
    'id' => 'news_news_block',
    'theme' => $theme,
    'plugin' => 'news_block',
  ]);
}
$news_block
  ->set('region', 'content')
  ->set('weight', -10)
  ->set('status', TRUE)
  ->set('settings', [
    'id' => 'news_block',
    'label' => 'News block',
    'label_display' => 'visible',
    'provider' => 'news',
  ])
  ->set('visibility', [
    'request_path' => [
      'id' => 'request_path',
      'negate' => FALSE,
      'context_mapping' => [],
      'pages' => '<front>',
    ],
    'user_role' => [
      'id' => 'user_role',
      'negate' => FALSE,
      'context_mapping' => ['user' => '@user.current_user_context:current_user'],
      'roles' => ['authenticated' => 'authenticated'],
    ],
  ])
  ->save();
echo "блок news: только <front>, только authenticated\n";

// ---------------------------------------------------------------------------
// 4. Тип «Статья», цена и вычисляемое поле (задание 4)
// ---------------------------------------------------------------------------
if (!NodeType::load('article')) {
  NodeType::create([
    'type' => 'article',
    'name' => 'Статья',
    'description' => 'Публикации и материалы портала «Город24».',
    'new_revision' => FALSE,
    'display_submitted' => TRUE,
  ])->save();
  echo "тип материала «Статья»: создан\n";
}
if (!FieldConfig::loadByName('node', 'article', 'body')) {
  FieldConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Текст',
    'required' => FALSE,
  ])->save();
  echo "поле body типа «Статья»: создано\n";
}
$article_form = EntityFormDisplay::load('node.article.default');
if (!$article_form) {
  $article_form = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$article_form->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 1])->save();
$article_view = EntityViewDisplay::load('node.article.default');
if (!$article_view) {
  $article_view = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$article_view->setComponent('body', ['type' => 'text_default', 'weight' => 1])->save();

if (!FieldStorageConfig::loadByName('node', 'field_price')) {
  FieldStorageConfig::create([
    'field_name' => 'field_price',
    'entity_type' => 'node',
    'type' => 'decimal',
    'settings' => ['precision' => 10, 'scale' => 2],
  ])->save();
  echo "поле field_price (decimal): создано\n";
}
if (!FieldConfig::loadByName('node', 'noutbuk', 'field_price')) {
  FieldConfig::create([
    'field_name' => 'field_price',
    'entity_type' => 'node',
    'bundle' => 'noutbuk',
    'label' => 'Цена, руб.',
    'required' => FALSE,
  ])->save();
}
$noutbuk_form = $etm->getStorage('entity_form_display')->load('node.noutbuk.default');
$noutbuk_form->setComponent('field_price', ['type' => 'number', 'weight' => 4])->save();

$prices = [
  'Lenovo IdeaPad Flex 14' => 45990,
  'Lenovo IdeaPad Yoga 13' => 38990,
  'Lenovo ThinkPad E520' => 24990,
  'Lenovo ThinkPad T490' => 54990,
  'Lenovo ThinkPad X1 Carbon' => 89990,
  'Lenovo V330-15IKB' => 32990,
  'Lenovo Yoga 3 Pro' => 41990,
  'Lenovo Yoga 2 Pro' => 36990,
  'Lenovo Yoga 530' => 39990,
  'Lenovo ThinkPad X220' => 19990,
];
$updated = 0;
$nodes = $etm->getStorage('node')->loadByProperties(['type' => 'noutbuk']);
foreach ($nodes as $node) {
  if (isset($prices[$node->label()])) {
    $node->set('field_price', $prices[$node->label()]);
    $node->save();
    $updated++;
  }
}
echo "цены проставлены для {$updated} ноутбуков\n";

// Вычисляемое поле: значение 90 % от field_price (модуль gorod24_price).
$field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'noutbuk');
if (!isset($field_definitions['field_price_discount'])) {
  echo "ВНИМАНИЕ: вычисляемое поле field_price_discount не найдено — проверь модуль gorod24_price\n";
}
else {
  foreach (['default', 'teaser'] as $view_mode) {
    $display = $etm->getStorage('entity_view_display')->load("node.noutbuk.$view_mode");
    if (!$display) {
      continue;
    }
    $display->setComponent('field_price', [
      'type' => 'number_decimal',
      'weight' => 5,
      'settings' => [
        'thousand_separator' => ' ',
        'decimal_separator' => ',',
        'scale' => 0,
        'prefix' => '',
        'suffix' => ' руб.',
      ],
    ]);
    $display->setComponent('field_price_discount', [
      'type' => 'number_decimal',
      'weight' => 6,
      'settings' => [
        'thousand_separator' => ' ',
        'decimal_separator' => ',',
        'scale' => 0,
        'prefix' => '',
        'suffix' => ' руб. (скидка 10 %)',
      ],
    ]);
    $display->save();
  }
}
echo "вычисляемое поле «Цена со скидкой» выведено в карточке ноутбука\n";

// ---------------------------------------------------------------------------
// 4.1. Три статьи, в том числе старше месяца для проверки фильтра.
// ---------------------------------------------------------------------------
$articles = [
  [
    'title' => 'Как работает редакция «Город24»',
    'body' => '<p>Редакция портала собирает городские новости, проверяет источники и готовит ежедневную ленту. Материалы выходят в шести рубриках: город, транспорт, происшествия, культура, спорт и технологии.</p>',
    'created' => time() - 5 * 86400,
  ],
  [
    'title' => 'Маршрут по Верхнему городу: что посмотреть за один день',
    'body' => '<p>Короткий маршрут по историческому центру Минска: площадь Свободы, ратуша, Бернардинский монастырь и дворик с городскими скульптурами. Маршрут рассчитан на три часа спокойной прогулки.</p>',
    'created' => time() - 12 * 86400,
  ],
  [
    'title' => 'Архив: старый Минск в фотографиях 1970-х',
    'body' => '<p>Подборка архивных снимков из городских коллекций: проспект, рынок, трамвайные остановки и дворы, которых уже нет на карте современного города.</p>',
    'created' => time() - 60 * 86400,
  ],
];
foreach ($articles as $data) {
  $storage = $etm->getStorage('node');
  $found = $storage->loadByProperties(['type' => 'article', 'title' => $data['title']]);
  $node = reset($found) ?: Node::create(['type' => 'article', 'title' => $data['title']]);
  $node->set('body', ['value' => $data['body'], 'format' => 'basic_html']);
  $node->set('status', 1);
  $node->setCreatedTime($data['created']);
  $node->save();
  echo "  статья: {$data['title']} (node/{$node->id()})\n";
}

// ---------------------------------------------------------------------------
// 5. View «Статьи портала»: таблица и фильтр по дате (задание 5)
// ---------------------------------------------------------------------------
$view = View::load('articles_table');
if (!$view) {
  $view = View::create([
    'id' => 'articles_table',
    'label' => 'Статьи портала',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'status' => TRUE,
  ]);
}
$view->set('description', 'Все статьи типа «Статья» за последний месяц, таблицей.');
$default_options = [
  'title' => 'Статьи портала',
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 10]],
  'exposed_form' => ['type' => 'basic', 'options' => []],
  'style' => [
    'type' => 'table',
    'options' => [
      'columns' => [
        'title' => 'title',
        'uid' => 'uid',
        'created' => 'created',
      ],
      'default' => 'created',
      'info' => [
        'title' => ['sortable' => TRUE, 'align' => ''],
        'uid' => ['sortable' => TRUE, 'align' => ''],
        'created' => ['sortable' => TRUE, 'align' => ''],
      ],
      'override' => TRUE,
      'sticky' => FALSE,
      'caption' => 'Статьи за последний месяц',
      'summary' => '',
    ],
  ],
  'row' => ['type' => 'fields', 'options' => ['inline' => []]],
  'fields' => [
    'title' => [
      'id' => 'title',
      'table' => 'node_field_data',
      'field' => 'title',
      'entity_type' => 'node',
      'entity_field' => 'title',
      'plugin_id' => 'node',
      'label' => 'Заголовок',
      'link_to_entity' => TRUE,
    ],
    'uid' => [
      'id' => 'uid',
      'table' => 'node_field_data',
      'field' => 'uid',
      'entity_type' => 'node',
      'entity_field' => 'uid',
      'plugin_id' => 'field',
      'label' => 'Автор',
    ],
    'created' => [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'entity_type' => 'node',
      'entity_field' => 'created',
      'plugin_id' => 'field',
      'label' => 'Дата публикации',
      'type' => 'timestamp',
      'settings' => ['date_format' => 'short'],
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
      'value' => ['article' => 'article'],
      'group' => 1,
      'expose' => ['operator' => ''],
    ],
    'created' => [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'entity_type' => 'node',
      'entity_field' => 'created',
      'plugin_id' => 'date',
      'operator' => '>=',
      'value' => [
        'value' => '-30 days',
        'min' => '',
        'max' => '',
        'type' => 'offset',
      ],
      'group' => 1,
      'expose' => ['operator' => ''],
    ],
  ],
  'sorts' => [
    'created' => [
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'entity_type' => 'node',
      'entity_field' => 'created',
      'plugin_id' => 'date',
      'order' => 'DESC',
      'expose' => ['operator' => ''],
    ],
  ],
  'access' => ['type' => 'perm', 'perm' => 'access content'],
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
    'display_title' => 'Страница статей',
    'position' => 1,
    'display_options' => [
      'path' => 'articles',
      'menu' => [
        'type' => 'normal',
        'title' => 'Статьи',
        'description' => 'Все статьи портала.',
        'parent' => 'system.admin_content',
        'weight' => 0,
      ],
    ],
  ],
]);
$view->save();
echo "View «Статьи портала»: страница /articles, таблица + фильтр за 30 дней\n";

echo "ЛР11: конфигурация завершена\n";
