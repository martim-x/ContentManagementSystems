<?php
/**
 * Аудит фактического состояния стенда ЛР10.
 * Запуск: drush php:script /opt/drupal/web/scripts/audit-lab10.php
 */

$etm = \Drupal::entityTypeManager();

echo "== Типы материалов ==\n";
foreach ($etm->getStorage('node_type')->loadMultiple() as $id => $type) {
  echo $id . ' — ' . $type->label() . "\n";
}

echo "\n== Права, связанные со статьями и форматами ==\n";
foreach (\Drupal::service('user.permissions')->getPermissions() as $name => $info) {
  if (str_contains($name, 'article') || str_contains($name, 'text format')) {
    echo $name . "\n";
  }
}

echo "\n== Роль redaktor ==\n";
$role = \Drupal\user\Entity\Role::load('redaktor');
if ($role) {
  echo 'строк: ' . count($role->getPermissions()) . "\n";
  foreach ($role->getPermissions() as $p) {
    echo '  ' . $p . "\n";
  }
}

echo "\n== Пользователь redaktor ==\n";
$user = user_load_by_name('redaktor');
echo $user ? ('uid=' . $user->id() . ', роли: ' . implode(',', $user->getRoles()) . "\n") : "нет\n";

echo "\n== Контактные формы ==\n";
foreach ($etm->getStorage('contact_form')->loadMultiple() as $id => $form) {
  echo $id . ' — ' . $form->label() . ' → ' . implode(',', $form->getRecipients()) . "\n";
}

echo "\n== Настройки ==\n";
foreach (['statistics.settings', 'simple_sitemap.settings', 'honeypot.settings', 'captcha.settings', 'system.performance'] as $name) {
  $config = \Drupal::config($name);
  echo $name . ': ' . json_encode($config->getRawData(), JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== Точка CAPTCHA ==\n";
$point = $etm->getStorage('captcha_point')->load('contact_message_feedback_form');
echo $point ? ($point->get('captchaType') . ', status=' . $point->get('status') . "\n") : "нет\n";

echo "\n== Резервное копирование ==\n";
$schedule = $etm->getStorage('backup_migrate_schedule')->load('daily_schedule');
echo $schedule ? ('включено=' . $schedule->get('enabled') . ', период=' . $schedule->get('period') . ', хранить=' . $schedule->get('keep') . "\n") : "нет\n";
$dir = \Drupal::service('file_system')->realpath('private://backup_migrate');
echo 'каталог копий: ' . $dir . "\n";
if ($dir && is_dir($dir)) {
  foreach (scandir($dir) as $file) {
    if ($file !== '.' && $file !== '..') {
      echo '  ' . $file . ' (' . filesize($dir . '/' . $file) . " б)\n";
    }
  }
}

echo "\n== Статистика посещаемости ==\n";
$count = \Drupal::database()->select('node_counter', 's')->countQuery()->execute()->fetchField();
echo 'записей о просмотрах: ' . $count . "\n";
foreach (\Drupal::database()->query('SELECT nid, totalcount FROM {node_counter} ORDER BY totalcount DESC LIMIT 5') as $row) {
  echo '  node/' . $row->nid . ': ' . $row->totalcount . "\n";
}

echo "\n== Метатеги: Schema.org ==\n";
$tags = \Drupal::config('metatag.metatag_defaults.node')->get('tags') ?: [];
foreach ($tags as $key => $value) {
  if (str_starts_with($key, 'schema_')) {
    echo $key . ' = ' . $value . "\n";
  }
}
