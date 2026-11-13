<?php
/**
 * Аудит фактического состояния стенда ЛР11.
 * Запуск: drush php:script /opt/drupal/web/scripts/audit-lab11.php
 */

$etm = \Drupal::entityTypeManager();

echo "== Типы материалов ==\n";
foreach ($etm->getStorage('node_type')->loadMultiple() as $id => $type) {
  echo $id . ' — ' . $type->label() . "\n";
}

echo "\n== Собственная таблица gorod24_registry ==\n";
$count = \Drupal::database()->select('gorod24_registry', 'r')->countQuery()->execute()->fetchField();
echo 'записей: ' . $count . "\n";
foreach (\Drupal::database()->query('SELECT id, model, series FROM {gorod24_registry} ORDER BY id') as $row) {
  echo '  ' . $row->id . ' | ' . $row->model . ' | ' . $row->series . "\n";
}

echo "\n== Слоган и блок слогана ==\n";
echo 'system.site.slogan: ' . \Drupal::config('system.site')->get('slogan') . "\n";
$slogan_block = $etm->getStorage('block')->load('gorod24_slogan_block');
echo $slogan_block ? ('регион: ' . $slogan_block->getRegion() . ', статус: ' . $slogan_block->status() . "\n") : "блок не найден\n";

echo "\n== Блок news ==\n";
$news_block = $etm->getStorage('block')->load('news_news_block');
if ($news_block) {
  $path = $news_block->getVisibilityCondition('request_path');
  $roles = $news_block->getVisibilityCondition('user_role');
  echo 'регион: ' . $news_block->getRegion() . "\n";
  echo 'страницы: ' . ($path ? $path->getConfiguration()['pages'] : '—') . "\n";
  echo 'роли: ' . ($roles ? implode(',', array_keys($roles->getConfiguration()['roles'])) : '—') . "\n";
}
else {
  echo "блок не найден\n";
}

echo "\n== Поля цены и вычисляемое ==\n";
$definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'noutbuk');
foreach (['field_price', 'field_price_discount'] as $name) {
  echo $name . ': ' . (isset($definitions[$name]) ? $definitions[$name]->getType() : 'НЕТ') . "\n";
}
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'noutbuk']) as $node) {
  $price = $node->hasField('field_price') ? $node->get('field_price')->value : '';
  $discount = $node->hasField('field_price_discount') ? $node->get('field_price_discount')->value : '';
  echo '  ' . $node->label() . ': ' . $price . ' → ' . $discount . "\n";
}

echo "\n== View articles_table ==\n";
$view = $etm->getStorage('view')->load('articles_table');
if ($view) {
  $display = $view->get('display');
  echo 'страница: /' . $display['page_1']['display_options']['path'] . "\n";
  echo 'колонки: ' . implode(', ', array_keys($display['default']['display_options']['style']['options']['columns'])) . "\n";
  $filter = $display['default']['display_options']['filters']['created'];
  echo 'фильтр created: ' . $filter['operator'] . ' ' . $filter['value']['value'] . ' (' . $filter['value']['type'] . ")\n";
}
else {
  echo "представление не найдено\n";
}

echo "\n== Статьи ==\n";
foreach ($etm->getStorage('node')->loadByProperties(['type' => 'article']) as $node) {
  echo '  ' . $node->label() . ': ' . \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'short') . "\n";
}
