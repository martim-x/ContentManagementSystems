<?php

namespace Drupal\gorod24_price\Plugin\ComputedField;

use Drupal\computed_field\Attribute\ComputedField;
use Drupal\computed_field\Field\ComputedFieldDefinitionWithValuePluginInterface;
use Drupal\computed_field\Plugin\ComputedField\ComputedFieldBase;
use Drupal\computed_field\Plugin\ComputedField\SingleValueTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Поле «Цена со скидкой»: 90 % от значения поля field_price.
 */
#[ComputedField(
  id: 'gorod24_price_discount',
  label: new TranslatableMarkup('Цена со скидкой 10 %'),
  field_type: 'decimal',
  attach: [
    'scope' => 'bundle',
    'field_name' => 'field_price_discount',
    'entity_types' => ['node' => ['noutbuk']],
  ],
)]
class PriceDiscount extends ComputedFieldBase {

  use SingleValueTrait;

  /**
   * {@inheritdoc}
   */
  public function singleComputeValue(EntityInterface $host_entity, ComputedFieldDefinitionWithValuePluginInterface $computed_field_definition): mixed {
    if (!$host_entity->hasField('field_price') || $host_entity->get('field_price')->isEmpty()) {
      return NULL;
    }
    $price = (float) $host_entity->get('field_price')->value;
    return round($price * 0.9, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageDefinitionSettings(): array {
    return [
      'precision' => 10,
      'scale' => 2,
    ];
  }

}
