<?php

namespace Drupal\webform_paypal_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Client;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Handles PayPal payment responses.
 */
class PaypalController extends ControllerBase {

  /**
   * Handles PayPal success callback.
   */
  public function success(Request $request) {
    $orderId = $request->query->get('token'); // PayPal order ID
    $session = \Drupal::service('session'); // Get session service
    $submissionId = $session->get('paypal_submission_id'); // Fetch from session
    $webform_url =  $session->get('webform_url'); // Fetch from session

    $transaction_field = $request->query->get('transaction_field'); // PayPal order ID

    if (!$orderId) {
      $this->messenger()->addError('Invalid payment response.');
      return new RedirectResponse('/webform/rally/test'); // Change to your webform URL
    }

    // Fetch PayPal credentials from settings
    $config = \Drupal::config('webform_paypal_integration.settings');
    $client_id = $config->get('paypal_client_id');
    $client_secret = $config->get('paypal_secret');
    $paypal_mode = $config->get('paypal_mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
  
    // Get PayPal access token
    $client = \Drupal::httpClient();
    $response = $client->post("$paypal_mode/v1/oauth2/token", [
      'auth' => [$client_id, $client_secret],
      'form_params' => ['grant_type' => 'client_credentials'],
    ]);
    $data = json_decode($response->getBody(), TRUE);
    $access_token = $data['access_token'];

    $paypalMode = $config->get('paypal_mode') === 'live'
      ? 'https://api-m.paypal.com'
      : 'https://api-m.sandbox.paypal.com';

    $client = new Client();
    try {
      $response = $client->post("$paypalMode/v2/checkout/orders/$orderId/capture", [
        'headers' => [
          'Authorization' => "Bearer " . $access_token,
          'Content-Type' => 'application/json',
          'Prefer' => 'return=representation',
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);

      if (!empty($data['status']) && $data['status'] === 'COMPLETED') {
        $transactionId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? NULL;

        if ($transactionId) {
          // Load webform submission
          $submission = WebformSubmission::load($submissionId);
          if ($submission) {
            $values = $submission->getData();
            $values[$transaction_field] = $transactionId; // Store Transaction ID
            $submission->setData($values);
            $submission->save();
  
            // Clear session after use
            $session->remove('paypal_submission_id');
          }
        }
        $this->messenger()->addMessage('Payment completed successfully.');
      } else {
        $this->messenger()->addError('Payment was not completed.');
      }
    } catch (\Exception $e) {
      \Drupal::logger('paypal')->error($e->getMessage());
      $this->messenger()->addError('Error capturing payment.');
    }

    $response = new RedirectResponse($webform_url);
    $response->send(); 
  }

  /**
   * Handles PayPal cancellation callback.
   */
  public function cancel(Request $request) {
    $session = \Drupal::service('session'); // Get session service
    $webform_url =  $session->get('webform_url'); // Fetch from session
    $this->messenger()->addWarning('Payment was canceled.');
    $response = new RedirectResponse($webform_url);
    $response->send(); 
  }
}
