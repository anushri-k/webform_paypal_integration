<?php

namespace Drupal\webform_paypal_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * PayPal settings form.
 */
class PaypalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_paypal_integration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['webform_paypal_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webform_paypal_integration.settings');

    $form['paypal_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PayPal Business Email'),
      '#default_value' => $config->get('paypal_email'),
      '#required' => TRUE,
    ];

    $form['paypal_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'live' => $this->t('Live'),
        'sandbox' => $this->t('Sandbox (Test Mode)'),
      ],
      '#default_value' => $config->get('paypal_mode') ?? 'sandbox',
    ];

    $form['paypal_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PayPal Client ID'),
      '#default_value' => $config->get('paypal_client_id'),
      '#required' => TRUE,
    ];

    $form['paypal_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PayPal Secret Key'),
      '#default_value' => $config->get('paypal_secret'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('webform_paypal_integration.settings')
      ->set('paypal_email', $form_state->getValue('paypal_email'))
      ->set('paypal_mode', $form_state->getValue('paypal_mode'))
      ->set('paypal_client_id', $form_state->getValue('paypal_client_id'))
      ->set('paypal_secret', $form_state->getValue('paypal_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
