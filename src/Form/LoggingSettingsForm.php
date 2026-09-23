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

    $form['sensitive_query_parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sensitive Query Parameters'),
      '#description' => $this->t('Enter query parameter names (one per line) whose values should be masked wherever the request URI is logged: the request log line and the request URI attached to every other log record. Names are matched case-insensitively.'),
      '#default_value' => implode("\n", $config->get('sensitive_query_parameters') ?? []),
      '#rows' => 5,
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
    $this->config('uceap_logging.settings')
      ->set('sensitive_fields', $this->textareaToList($form_state->getValue('sensitive_fields')))
      ->set('sensitive_query_parameters', $this->textareaToList($form_state->getValue('sensitive_query_parameters')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Converts one-per-line textarea input to a list of non-empty values.
   *
   * @param string $raw
   *   The textarea input.
   *
   * @return string[]
   *   The trimmed, non-empty lines.
   */
  protected function textareaToList(string $raw): array {
    return array_values(array_filter(
      array_map('trim', explode("\n", $raw)),
      function ($value) {
        return !empty($value);
      }
    ));
  }

}
