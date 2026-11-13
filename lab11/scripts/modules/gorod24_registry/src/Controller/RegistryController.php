<?php

namespace Drupal\gorod24_registry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Показывает содержимое таблицы gorod24_registry.
 */
class RegistryController extends ControllerBase {

  /**
   * Соединение с базой данных.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Конструктор контроллера.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Строит таблицу записей реестра.
   */
  public function report() {
    $rows = [];
    $query = $this->database->select('gorod24_registry', 'r')
      ->fields('r', ['id', 'model', 'series', 'created'])
      ->orderBy('id', 'ASC');
    foreach ($query->execute() as $record) {
      $rows[] = [
        $record->id,
        $record->model,
        $record->series,
        \Drupal::service('date.formatter')->format((int) $record->created, 'short'),
      ];
    }

    return [
      'summary' => [
        '#markup' => '<p>Таблица <code>gorod24_registry</code> создана модулем '
          . '<code>gorod24_registry</code> через <code>hook_schema()</code> при установке. '
          . 'Записей: <strong>' . count($rows) . '</strong>.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['ID', 'Модель', 'Серия', 'Создана'],
        '#rows' => $rows,
        '#empty' => 'Таблица пуста.',
      ],
    ];
  }

}
