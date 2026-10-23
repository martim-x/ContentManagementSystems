<?php
/**
 * Конфигурация ЛР8: таксономия «Lenovo», тип материала «Ноутбук»,
 * каталог из 10 ноутбуков.
 */
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\pathauto\Entity\PathautoPattern;

// 1. Словарь Lenovo
if (!Vocabulary::load('lenovo')) {
    $vocab = Vocabulary::create([
        'vid' => 'lenovo',
        'name' => 'Lenovo',
        'description' => 'Серии ноутбуков Lenovo',
    ]);
    $vocab->save();
    echo "словарь «Lenovo»: создан\n";
} else {
    echo "словарь «Lenovo»: уже есть\n";
}

$series_names = ['серия G', 'серия N', 'серия V'];
$term_ids = [];
foreach ($series_names as $name) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $name, 'vid' => 'lenovo']);
    $term = reset($terms);
    if (!$term) {
        $term = Term::create(['vid' => 'lenovo', 'name' => $name]);
        $term->save();
        echo "  термин «{$name}»: создан\n";
    }
    $term_ids[$name] = $term->id();
}

// 2. Тип материала «Ноутбук»
if (!NodeType::load('noutbuk')) {
    $type = NodeType::create([
        'type' => 'noutbuk',
        'name' => 'Ноутбук',
        'description' => 'Карточка ноутбука',
    ]);
    $type->save();
    echo "тип материала «Ноутбук»: создан\n";
} else {
    echo "тип материала «Ноутбук»: уже есть\n";
}

// Поле серии
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
        'settings' => ['handler_settings' => ['target_bundles' => ['lenovo' => 'lenovo']]],
    ])->save();
    echo "  поле «Серия»: создано\n";
}

// Поле фотографии
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
        'settings' => ['file_extensions' => 'png jpg jpeg gif', 'alt_field' => TRUE],
    ])->save();
    echo "  поле «Фотография»: создано\n";
}

// 3. Каталог: 10 ноутбуков
$existing = \Drupal::entityQuery('node')
    ->condition('type', 'noutbuk')
    ->accessCheck(FALSE)
    ->count()
    ->execute();
echo "уже создано ноутбуков: {$existing}\n";

if ($existing < 10) {
    $series_list = ['серия G', 'серия N', 'серия V'];
    for ($i = $existing + 1; $i <= 10; $i++) {
        $series = $series_list[$i % 3];
        $node = \Drupal\node\Entity\Node::create([
            'type' => 'noutbuk',
            'title' => "Lenovo IdeaPad {$series} №{$i}",
            'body' => [
                'value' => "Ноутбук Lenovo IdeaPad, {$series}, модель №{$i}. Процессор, память, накопитель — по спецификации производителя.",
                'format' => 'basic_html',
            ],
            'field_series' => ['target_id' => $term_ids[$series]],
            'status' => 1,
        ]);
        $node->save();
        echo "  ноутбук #{$node->id()}: {$node->getTitle()}\n";
    }
}

// Слоган сайта
\Drupal::configFactory()->getEditable('system.site')->set('slogan', 'Город24 — новости города')->save();

// Паттерн алиасов Pathauto для ноутбуков
if (!\Drupal\pathauto\Entity\PathautoPattern::load('noutbuk')) {
    $pattern = \Drupal\pathauto\Entity\PathautoPattern::create([
        'id' => 'noutbuk',
        'label' => 'Ноутбуки',
        'type' => 'canonical_entities:node',
        'pattern' => 'noutbuki/[node:title]',
        'selection_criteria' => [
            'entity_bundle:node' => [
                'id' => 'entity_bundle:node',
                'bundles' => ['noutbuk' => 'noutbuk'],
                'negate' => FALSE,
                'context_mapping' => ['node' => 'node'],
            ],
        ],
        'selection_logic' => 'and',
        'weight' => 0,
    ]);
    $pattern->save();
    echo "паттерн алиасов Pathauto: создан\n";
}

// Генерация алиасов для созданных ноутбуков
$nodes = \Drupal::entityTypeManager()->getStorage('node')
    ->loadByProperties(['type' => 'noutbuk']);
$gen = \Drupal::service('pathauto.generator');
foreach ($nodes as $n) {
    $gen->updateEntityAlias($n, 'create');
}
echo "алиасы Pathauto: сгенерированы (" . count($nodes) . ")\n";

echo "ЛР8: конфигурация завершена\n";
