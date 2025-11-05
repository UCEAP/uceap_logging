<?php

namespace Drupal\uceap_logging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    $form['sensitive_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sensitive Fields'),
      '#description' => $this->t('Enter field machine names (one per line) that should have their values masked in entity change logs. When these fields are modified, they will appear in logs with masked values (e.g., ***MASKED***) instead of actual values.'),
      '#default_value' => implode("\n", $config->get('sensitive_fields') ?? []),
      '#rows' => 10,
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
    // Convert textarea input to array.
    $sensitive_fields_raw = $form_state->getValue('sensitive_fields');
    $sensitive_fields = array_filter(
      array_map('trim', explode("\n", $sensitive_fields_raw)),
      function ($field) {
        return !empty($field);
      }
    );

    $this->config('uceap_logging.settings')
      ->set('sensitive_fields', array_values($sensitive_fields))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
