<?php
/**
 * Конфигурация ЛР10 «Права пользователей, карта сайта, безопасность и
 * эффективность» — labs/Drupal/Drupal_3.pdf.
 *
 * Скрипт идемпотентен: повторный запуск не создаёт дубликатов.
 * Запуск: drush php:script /opt/drupal/web/scripts/lab10-config.php
 *
 * Что настраивает:
 *  1 — роль «Редактор» и пользователь redaktor с ограниченными правами;
 *  2 — контактная форма «Обратная связь» (ядро Contact) для администрации;
 *  3 — карта сайта simple_sitemap, микроразметка Schema.org (schema_metatag),
 *      защита формы от спама (Honeypot + CAPTCHA);
 *  4 — ежедневное резервное копирование (Backup and Migrate) и статистика
 *      посещаемости (contrib-модуль statistics, в ядре Drupal 11 удалён);
 *  5 — кэширование: страничный кэш для анонимных, агрегация CSS/JS, TTL.
 */

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\contact\Entity\ContactForm;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/** Пункт меню по заголовку: создаёт или обновляет существующий. */
function lab10_menu(string $menu, string $title, string $uri, int $weight = 0): void {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $found = $storage->loadByProperties(['menu_name' => $menu, 'title' => $title]);
  $link = reset($found) ?: MenuLinkContent::create(['menu_name' => $menu, 'title' => $title]);
  $link->set('link', ['uri' => $uri]);
  $link->set('weight', $weight);
  $link->save();
}

// ---------------------------------------------------------------------------
// 1. Пользователь и роль (задание 1)
// ---------------------------------------------------------------------------
$role = Role::load('redaktor');
if (!$role) {
  $role = Role::create(['id' => 'redaktor', 'label' => 'Редактор']);
  echo "роль «Редактор»: создана\n";
}
$permissions = [
  'access content',
  'access content overview',
  'access toolbar',
  'view own unpublished content',
  'create news content',
  'edit own news content',
  'delete own news content',
  'use text format basic_html',
];
foreach ($permissions as $permission) {
  $role->grantPermission($permission);
}
$role->save();
echo "роль «Редактор»: права на новости и статьи, без доступа к конфигурации\n";

$editor = user_load_by_name('redaktor');
if (!$editor) {
  $editor = User::create([
    'name' => 'redaktor',
    'mail' => 'redaktor@gorod24.local',
    'status' => 1,
  ]);
  $editor->setPassword('Redaktor!2026');
  echo "пользователь redaktor: создан\n";
}
$editor->addRole('redaktor');
$editor->save();
echo "пользователь redaktor: роль «Редактор» назначена\n";

// ---------------------------------------------------------------------------
// 2. Контактная форма администрации (задание 2)
// ---------------------------------------------------------------------------
$contact = ContactForm::load('feedback');
if (!$contact) {
  $contact = ContactForm::create(['id' => 'feedback']);
  echo "контактная форма «Обратная связь»: создана\n";
}
$contact
  ->set('label', 'Обратная связь')
  ->set('recipients', ['redakciya@gorod24.local'])
  ->set('reply', '')
  ->set('weight', 0)
  ->set('message', 'Напишите в редакцию портала «Город24»: предложение новости, вопрос или замечание. Поля со звёздочкой обязательны.')
  ->save();
lab10_menu('main', 'Обратная связь', 'internal:/contact', 3);
echo "контактная форма: /contact, получатель redakciya@gorod24.local\n";

// Форма доступна гостям и зарегистрированным пользователям.
foreach (['anonymous', 'authenticated'] as $rid) {
  $r = Role::load($rid);
  if ($r) {
    $r->grantPermission('access site-wide contact form')->save();
  }
}

// ---------------------------------------------------------------------------
// 3. Карта сайта, микроразметка, антиспам (задание 3)
// ---------------------------------------------------------------------------
// 3.1. Карта сайта: материалы, термины и пункты меню.
\Drupal::configFactory()->getEditable('simple_sitemap.settings')
  ->set('enabled_entity_types', ['node', 'taxonomy_term', 'menu_link_content'])
  ->set('cron_generate', TRUE)
  ->set('xsl', TRUE)
  ->set('base_url', 'http://localhost:8108')
  ->save();

// Включение типов материалов и словарей в карту: у сущностей с бандлами
// настройка хранится отдельно для каждого бандла.
foreach (['news', 'noutbuk'] as $bundle) {
  \Drupal::configFactory()->getEditable("simple_sitemap.bundle_settings.default.node.$bundle")
    ->set('index', 1)
    ->set('priority', '0.5')
    ->set('changefreq', 'daily')
    ->set('include_images', 0)
    ->save();
}
foreach (['rubriki', 'lenovo'] as $vocabulary) {
  \Drupal::configFactory()->getEditable("simple_sitemap.bundle_settings.default.taxonomy_term.$vocabulary")
    ->set('index', 1)
    ->set('priority', '0.3')
    ->set('changefreq', 'weekly')
    ->set('include_images', 0)
    ->save();
}
echo "simple_sitemap: карта сайта /sitemap.xml, материалы и рубрики включены\n";

// 3.2. Микроразметка Schema.org: Article для материалов, Organization для сайта.
$node_defaults = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.node');
$node_defaults->set('tags.schema_article_type', 'Article')
  ->set('tags.schema_article_headline', '[node:title]')
  ->set('tags.schema_article_description', '[node:summary]')
  ->set('tags.schema_article_date_published', '[node:created:html_datetime]')
  ->set('tags.schema_article_date_modified', '[node:changed:html_datetime]')
  ->set('tags.schema_article_author', '[node:author]')
  ->set('tags.schema_article_publisher', '[site:name]')
  ->set('tags.schema_article_main_entity_of_page', '[node:url]')
  ->save();

$global_defaults = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.global');
$global_defaults->set('tags.schema_organization_type', 'Organization')
  ->set('tags.schema_organization_name', '[site:name]')
  ->set('tags.schema_organization_url', '[site:url]')
  ->set('tags.schema_organization_description', 'Городской новостной портал Минска')
  ->save();
echo "schema_metatag: JSON-LD Article и Organization включены\n";

// 3.3. Антиспам: скрытое поле Honeypot на всех формах + математическая CAPTCHA
// на контактной форме.
\Drupal::configFactory()->getEditable('honeypot.settings')
  ->set('protect_all_forms', TRUE)
  ->set('time_limit', 5)
  ->set('element_name', 'url')
  ->set('log', TRUE)
  ->save();

$point = CaptchaPoint::load('contact_message_feedback_form');
if (!$point) {
  $point = CaptchaPoint::create(['formId' => 'contact_message_feedback_form']);
}
$point
  ->set('label', 'Контактная форма «Обратная связь»')
  ->set('captchaType', 'captcha/Math')
  ->set('status', TRUE)
  ->save();

\Drupal::configFactory()->getEditable('captcha.settings')
  ->set('default_challenge', 'captcha/Math')
  ->set('title', 'Проверка на робота')
  ->set('description', 'Решите простой пример, чтобы отправить сообщение.')
  ->set('default_validation', 1)
  ->set('persistence', 1)
  ->save();
echo "антиспам: Honeypot на всех формах, CAPTCHA (математика) на /contact\n";

// ---------------------------------------------------------------------------
// 4. Резервное копирование и статистика (задание 4)
// ---------------------------------------------------------------------------
// 4.1. Ежедневное резервное копирование БД в private://backup_migrate.
$schedule = \Drupal::entityTypeManager()->getStorage('backup_migrate_schedule')->load('daily_schedule');
if ($schedule) {
  $schedule
    ->set('enabled', TRUE)
    ->set('keep', 5)
    ->set('period', 86400)
    ->set('source_id', 'default_db')
    ->set('destination_id', 'private_files')
    ->save();
  echo "backup_migrate: ежедневное копирование БД, хранить 5 копий\n";
}

// 4.2. Статистика посещаемости: счётчик просмотров материалов и отчёты.
\Drupal::configFactory()->getEditable('statistics.settings')
  ->set('count_content_views', 1)
  ->set('display_max_age', 3600)
  ->save();
echo "statistics: счётчик просмотров включён\n";

// В contrib-порт statistics отчёты ядра не перенесены: страницу
// «Посещаемость материалов» даёт собственный модуль gorod24_analytics
// (scripts/modules/gorod24_analytics), он ставится setup-скриптом.
if ($old_view = \Drupal\views\Entity\View::load('statistika_poseshchaemosti')) {
  $old_view->delete();
  echo "устаревшее представление статистики удалено\n";
}

// ---------------------------------------------------------------------------
// 5. Кэширование (задание 5)
// ---------------------------------------------------------------------------
\Drupal::configFactory()->getEditable('system.performance')
  ->set('cache.page.max_age', 3600)
  ->set('css.preprocess', TRUE)
  ->set('js.preprocess', TRUE)
  ->save();

// Блок веб-формы «Прислать новость» из ЛР8 не кэшируется (форма с токеном
// CSRF), из-за чего весь ответ становится UNCACHEABLE. Для ЛР10 блок
// отключается: страничный кэш анонимных должен реально работать.
$webform_block = \Drupal\block\Entity\Block::load('lab8_webform_block');
if ($webform_block && $webform_block->status()) {
  $webform_block->setStatus(FALSE)->save();
  echo "блок веб-формы отключён: иначе страничный кэш не работает\n";
}
echo "кэш: страницы для анонимных 3600 с, агрегация CSS и JS включена\n";

echo "ЛР10: конфигурация завершена\n";
