<?php

namespace Drupal\news\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Блок с демонстрационной строкой.
 */
#[Block(
  id: 'news_block',
  admin_label: new TranslatableMarkup('News block'),
)]
class NewsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '<p class="news-block">This is a news block</p>',
      '#cache' => [
        'contexts' => ['user.roles'],
      ],
    ];
  }

}
