<?php

namespace Drupal\gorod24_slogan\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Блок программного вывода слогана сайта.
 */
#[Block(
  id: 'gorod24_slogan_block',
  admin_label: new TranslatableMarkup('Слоган портала (программно)'),
)]
class SloganBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $slogan = \Drupal::config('system.site')->get('slogan');
    return [
      '#markup' => '<p class="gorod24-slogan">' . $slogan . '</p>',
      '#cache' => [
        'tags' => ['config:system.site'],
      ],
    ];
  }

}
