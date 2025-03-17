<?php

namespace Drupal\webform_paypal_integration\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * PayPal Integration Webform Handler.
 *
 * @WebformHandler(
 *   id = "paypal_webform_handler",
 *   label = @Translation("PayPal Integration"),
 *   category = @Translation("Payment"),
 *   description = @Translation("Integrates PayPal for payments."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class PaypalWebformHandler extends WebformHandlerBase {

  /**
 * {@inheritdoc}
 */
public function defaultConfiguration() {
  return [
    'price_field' => '',
    'quantity_field' => '',
    'transaction_id_field' => '',
  ] + parent::defaultConfiguration();
}

/**
 * {@inheritdoc}
 */
public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
  $form = parent::buildConfigurationForm($form, $form_state);

  // Fetch the Webform's fields to allow mapping.
  $webform = $this->getWebform();
  $webform_fields = $webform->getElementsDecodedAndFlattened();

  $field_options = [];
  foreach ($webform_fields as $key => $field) {
    $field_options[$key] = $field['#title'] ?? $key;
  }

  $form['price_field'] = [
    '#type' => 'select',
    '#title' => $this->t('Price Field'),
    '#options' => $field_options,
    '#required' => TRUE,
    '#default_value' => $this->configuration['price_field'] ?? '',
  ];

  $form['quantity_field'] = [
    '#type' => 'select',
    '#title' => $this->t('Quantity Field'),
    '#options' => $field_options,
    '#required' => TRUE,
    '#default_value' => $this->configuration['quantity_field'] ?? '',
  ];

  $form['transaction_id_field'] = [
    '#type' => 'select',
    '#title' => $this->t('Transaction ID Field'),
    '#options' => $field_options,
    '#required' => TRUE,
    '#default_value' => $this->configuration['transaction_id_field'] ?? '',
  ];

  $form['currency_field'] = [
    '#type' => 'select',
    '#title' => $this->t('Currency'),
    '#options' => $this->getCountryIsoCodes(),
    '#required' => TRUE,
    '#default_value' => $this->configuration['currency_field'] ?? 'GBP',
  ];

  return $form;
}

public function getCountryIsoCodes() {
  $client = \Drupal::httpClient();
  $response = $client->get('https://restcountries.com/v3.1/all?fields=currencies');
  $countries = json_decode($response->getBody(), TRUE);
  $currency_options = [];
  foreach ($countries as $country) {
    if (isset($country['currencies'])) {
      foreach ($country['currencies'] as $code => $currency) {
        $currency_options[$code] = $currency['name'] . ' (' . $code . ')';
      }
    }
  }
  ksort($currency_options); // Sort by currency code
  return $currency_options;  
}
/**
 * {@inheritdoc}
 */
public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  parent::submitConfigurationForm($form, $form_state);

  $this->configuration['price_field'] = $form_state->getValue('price_field');
  $this->configuration['quantity_field'] = $form_state->getValue('quantity_field');
  $this->configuration['transaction_id_field'] = $form_state->getValue('transaction_id_field');
  $this->configuration['currency_field'] = $form_state->getValue('currency_field');
}


/**
 * {@inheritdoc}
 */
public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
  $submission_data = $form_state->getValues();
  \Drupal::logger('submission_data')->notice(json_encode($submission_data));

  // Retrieve mapped fields from the handler's configuration
  $price_field = $this->configuration['price_field'];
  $quantity_field = $this->configuration['quantity_field'];
  $transaction_field = $this->configuration['transaction_id_field'];

  // Calculate total amount
  $amount = (float) ($submission_data[$price_field] ?? 0) * (int) ($submission_data[$quantity_field] ?? 1);

  $config = \Drupal::config('webform_paypal_integration.settings');
  $client_id = $config->get('paypal_client_id');
  $client_secret = $config->get('paypal_secret');
  $paypal_mode = $config->get('paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

  try {
    // Get PayPal access token
    $client = \Drupal::httpClient();
    $response = $client->post("$paypal_mode/v1/oauth2/token", [
      'auth' => [$client_id, $client_secret],
      'form_params' => ['grant_type' => 'client_credentials'],
    ]);
    $data = json_decode($response->getBody(), TRUE);
    $access_token = $data['access_token'];

    $session = \Drupal::service('session'); // Get session service
    $relative_url = \Drupal::service('path.current')->getPath();
    $session->set('webform_url', $relative_url);

    $returnUrl = \Drupal::request()->getSchemeAndHttpHost() . '/paypal/success?&transaction_field=' . $transaction_field;
    $cancelUrl = \Drupal::request()->getSchemeAndHttpHost() . "/paypal/cancel";

    // Create PayPal order
    $response = $client->post("$paypal_mode/v2/checkout/orders", [
      'headers' => [
        'Authorization' => "Bearer " . $access_token,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=representation',
      ],
      'json' => [
        'intent' => 'CAPTURE',
        'purchase_units' => [
          [
            'amount' => [
              'currency_code' => $this->configuration['currency_field'] ?? 'GBP',
              'value' => number_format($amount, 2, '.', '') ?? '0.00',
            ],
          ],
        ],
        'application_context' => [
          'return_url' => $returnUrl,
          'cancel_url' => $cancelUrl,
        ],
      ],
    ]);

    $order = json_decode($response->getBody(), TRUE);
    if (!empty($order['id'])) {
      // Store the PayPal transaction ID in the webform submission
      $webform_submission->save();
      $session->set('paypal_submission_id', $webform_submission->id());
      // Redirect user to PayPal approval URL
      foreach ($order['links'] as $link) {
        if ($link['rel'] === 'approve') {
          $response = new RedirectResponse($link['href']);
          $response->send();
        }
      }
    }

  } catch (RequestException $e) {
    \Drupal::logger('webform_paypal_integration')->error($e->getMessage());
    \Drupal::messenger()->addError('Error connecting to PayPal.');
  }
}

  
}