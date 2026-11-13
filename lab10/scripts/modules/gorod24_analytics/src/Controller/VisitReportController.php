<?php

namespace Drupal\gorod24_analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Отчёт «Посещаемость материалов» по таблице node_counter.
 *
 * Модуль Statistics в Drupal 11 вынесен в contrib и не содержит отчётов ядра,
 * поэтому страница собрана отдельным контроллером.
 */
class VisitReportController extends ControllerBase {

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
   * Строит таблицу просмотров материалов.
   */
  public function report() {
    $query = $this->database->select('node_counter', 'c');
    $query->join('node_field_data', 'n', 'n.nid = c.nid');
    $query->fields('n', ['nid', 'title']);
    $query->fields('c', ['totalcount', 'timestamp']);
    $query->condition('n.status', 1);
    $query->orderBy('c.totalcount', 'DESC');
    $query->range(0, 50);

    $rows = [];
    foreach ($query->execute() as $record) {
      $rows[] = [
        ['data' => [
          '#type' => 'link',
          '#title' => $record->title,
          '#url' => Url::fromRoute('entity.node.canonical', ['node' => $record->nid]),
        ]],
        ['data' => ['#markup' => (int) $record->totalcount]],
        ['data' => ['#markup' => \Drupal::service('date.formatter')->format((int) $record->timestamp, 'short')]],
      ];
    }

    return [
      'summary' => [
        '#markup' => '<p>Материалов со статистикой: <strong>' . count($rows) . '</strong>. '
          . 'Данные собирает модуль Statistics: счётчик отправляет AJAX-запрос при просмотре материала.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Материал', 'Всего просмотров', 'Последний просмотр'],
        '#rows' => $rows,
        '#empty' => 'Просмотров пока нет.',
      ],
    ];
  }

}
