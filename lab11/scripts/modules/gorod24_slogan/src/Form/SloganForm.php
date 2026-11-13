<?php

namespace Drupal\gorod24_slogan\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Форма изменения слогана сайта.
 */
class SloganForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['system.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gorod24_slogan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('system.site');
    $form['slogan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Слоган сайта'),
      '#default_value' => $config->get('slogan'),
      '#required' => TRUE,
      '#description' => $this->t('Значение хранится в конфигурации system.site и выводится блоком «Слоган портала».'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('system.site')
      ->set('slogan', $form_state->getValue('slogan'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
