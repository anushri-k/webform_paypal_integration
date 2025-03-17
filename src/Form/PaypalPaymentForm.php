<?php

namespace Drupal\webform_paypal_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * PayPal Payment Form.
 */
class PaypalPaymentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paypal_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount ($)'),
      '#required' => TRUE,
      '#default_value' => 12.00,
      '#step' => 0.01,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pay Now'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');

    // PayPal sandbox URL
    $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    // PayPal payment parameters
    $post_data = [
      'cmd' => '_xclick',
      'business' => 'sb-8e2qm34833550@business.example.com', // Replace with your PayPal sandbox business email
      'item_name' => 'Webform',
      'amount' => '12.00',
      'currency_code' => 'USD',
      'return' => \Drupal::request()->getSchemeAndHttpHost() . '/paypal/success',
      'cancel_return' => \Drupal::request()->getSchemeAndHttpHost() . '/paypal/cancel',
      'notify_url' => \Drupal::request()->getSchemeAndHttpHost() . '/paypal/ipn',
      'custom' => uniqid(), // Unique identifier for tracking payments
    ];

    // Redirect to PayPal
    $query = http_build_query($post_data, '', '&', PHP_QUERY_RFC3986);

    $response = new RedirectResponse($paypal_url . '?' . $query);

    $response->send();
  }
}
