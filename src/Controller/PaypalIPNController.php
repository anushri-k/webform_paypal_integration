<?php

namespace Drupal\webform_paypal_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\webform\Entity\WebformSubmission;

class PaypalIPNController extends ControllerBase {

  /**
   * Handle PayPal IPN callback.
   */
  public function ipn(Request $request) {
    $data = $request->request->all();

    // Verify PayPal IPN data
    $verify_url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
    $data['cmd'] = '_notify-validate';

    $ch = curl_init($verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);

    if (strcmp($response, "VERIFIED") === 0) {
      // Transaction is verified
      $transaction_id = $data['txn_id'] ?? NULL;
      $custom_submission_id = $data['custom'] ?? NULL;  // Use custom field for submission ID reference

      if ($transaction_id && $custom_submission_id) {
        // Load the Webform submission
        $submission = WebformSubmission::load($custom_submission_id);
        if ($submission) {
          $submission->setElementData('transaction_id', $transaction_id);
          $submission->save();
        }
      }
    }

    return new Response('IPN received');
  }
}
