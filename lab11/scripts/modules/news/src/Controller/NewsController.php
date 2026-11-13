<?php

namespace Drupal\news\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Контроллер страницы News.
 */
class NewsController extends ControllerBase {

  /**
   * Пустая страница с заголовком News.
   */
  public function page() {
    return [
      '#markup' => '',
    ];
  }

}
