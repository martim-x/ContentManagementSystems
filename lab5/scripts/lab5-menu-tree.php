<?php
/**
 * Пересборка деревьев для ЛР5: пункты меню и категории.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab5-menu-tree.php
 *
 * Зачем нужен: вложенность и в jos_menu, и в jos_categories задаётся
 * колонками lft/rgt. Скрипты лабораторной вставляют записи напрямую
 * через PDO с захардкоженными координатами, и они пересекаются с
 * позициями штатных записей (Uncategorised, админские пункты меню).
 * На пересечённом дереве Joomla перестаёт находить пункты меню и
 * категории: SEF-ссылки отдают 404. Пересборку делает штатный
 * Table::rebuild(), который считает lft/rgt вместе с path и level.
 *
 * Почему нельзя подключать cli/joomla.php: этот файл не только
 * загружает фреймворк, но и СРАЗУ запускает CLI-приложение — при
 * таком включении скрипт печатал справку по командам и завершался,
 * не выполнив ни одной пересборки. Достаточно includes/framework.php:
 * он поднимает автозагрузчик и фабрику контейнера без запуска
 * приложения, а Table::rebuild() работает только с базой.
 *
 * Скрипт идемпотентен: повторный запуск просто пересчитывает деревья.
 */

define( '_JEXEC', 1 );
define( 'JPATH_BASE', '/var/www/html' );

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;

$db = Factory::getContainer()->get( DatabaseInterface::class );

// Table::getInstance() внутри обращается к приложению, поэтому CLI-приложение
// нужно создать заранее. Блок скопирован из cli/joomla.php с одним отличием:
// execute() не вызывается, и справка по командам не печатается. Алиасы
// session.cli обязательны: без них контейнер не резолвит сервис сессии.
$container = Factory::getContainer();

$container->alias( 'session', 'session.cli' )
	->alias( 'JSession', 'session.cli' )
	->alias( \Joomla\CMS\Session\Session::class, 'session.cli' )
	->alias( \Joomla\Session\Session::class, 'session.cli' )
	->alias( \Joomla\Session\SessionInterface::class, 'session.cli' );

$app                   = $container->get( \Joomla\Console\Application::class );
Factory::$application = $app;

echo "пересборка дерева пунктов меню\n";

$menu_types = $db->setQuery( 'SELECT menutype FROM jos_menu_types' )->loadAssocList();

$total = 0;

foreach ( $menu_types as $menu_type ) {
	$menutype = $menu_type['menutype'];

	// Таблица пунктов меню сама пересчитывает lft/rgt и path.
	$table = Menu::getInstance( 'Menu' );

	if ( ! $table->rebuild() ) {
		echo "  {$menutype}: пересборка не удалась\n";

		continue;
	}

	$count = (int) $db->setQuery(
		'SELECT COUNT(*) FROM jos_menu WHERE menutype = ' . $db->quote( $menutype )
	)->loadResult();

	echo "  {$menutype}: пунктов {$count}\n";

	$total += $count;
}

echo "всего пунктов: {$total}\n";
echo "дерево пунктов меню перестроено\n";

// ---------------------------------------------------------------------------
// Пересборка дерева категорий.
//
// Категория «Новости» вставляется в jos_categories напрямую через PDO,
// с захардкоженными lft=2/rgt=3. В чистой установке эти же позиции заняты
// категорией Uncategorised, поэтому после вставки дерево пересекается:
// uncategorised [1,2] и novosti [2,3] делят позицию 2. SEF-роутер Joomla
// ищет материалы через путь категории, и на пересечённом дереве любой
// адрес кроме главной отдаёт 404. Штатная Table\Category::rebuild()
// пересчитывает lft/rgt, level и path по parent_id — дерево сходится.
// ---------------------------------------------------------------------------
echo "пересборка дерева категорий\n";

$category_table = new Category( $db );

if ( ! $category_table->rebuild() ) {
	echo "  пересборка дерева категорий не удалась\n";

	exit( 1 );
}

$cats = $db->setQuery(
	"SELECT id, lft, rgt, level, path FROM jos_categories WHERE extension = 'com_content' ORDER BY lft"
)->loadAssocList();

foreach ( $cats as $cat ) {
	echo "  категория {$cat['id']}: [{$cat['lft']},{$cat['rgt']}] уровень {$cat['level']} путь «{$cat['path']}»\n";
}

echo "дерево категорий перестроено\n";
