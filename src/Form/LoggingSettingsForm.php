<?php

namespace Drupal\uceap_logging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Configure UCEAP Logging settings.
 */
class LoggingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uceap_logging_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['uceap_logging.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('uceap_logging.settings');

    $fields = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->loadMultiple();

    $field_names = [];
    foreach($fields as $field_key => $field_value) {
      $field_names[$field_value->getName()] = $field_value->getName();
    }

    $form['sensitive_fields'] = [
      '#type' => 'select2',
      '#title' => $this->t('Selectable Sensitive Fields'),
      '#options' => $field_names,
      '#multiple' => TRUE,
      '#default_value' => $config->get('sensitive_fields') ?? [],
    ];

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Examples'),
      '#open' => FALSE,
    ];

    $form['help']['examples'] = [
      '#markup' => $this->t('<p>Common sensitive fields include:</p>
        <ul>
          <li><code>field_ssn</code> - Social Security Numbers</li>
          <li><code>pass</code> - User passwords</li>
          <li><code>field_bank_account</code> - Banking information</li>
          <li><code>field_credit_card</code> - Payment information</li>
          <li><code>field_api_key</code> - API keys or tokens</li>
        </ul>
        <p><strong>Note:</strong> The following fields are automatically excluded from logging: <code>changed</code>, <code>revision_timestamp</code>, <code>revision_uid</code>, <code>revision_log</code>. Additionally, computed and internal fields are never logged.</p>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selectable_sensitive_fields_raw = $form_state->getValue('sensitive_fields');

    $this->config('uceap_logging.settings')
      ->set('sensitive_fields', array_values($selectable_sensitive_fields_raw))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
