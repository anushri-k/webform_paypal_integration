# PayPal Webform Integration Module

## Description
This module integrates PayPal with Drupal Webforms, allowing users to process payments based on form submissions. Users can map webform fields to price, quantity, transaction ID, and currency fields for seamless payment processing.

## Features
- Map webform fields for price, quantity, and transaction ID.
- Supports multiple currencies using real-time data from the REST Countries API.
- Enables users to select currency options dynamically.
- Secure and configurable payment processing.

## Requirements
- Drupal 9 or higher
- Webform module
- GuzzleHTTP library (included in Drupal Core)

## Installation
1. Download and place this module in the `modules/custom/webform_paypal_integration` directory.
2. Enable the module via `drush en webform_paypal_integration` or through the Drupal admin UI (`/admin/modules`).

## Configuration
1. Navigate to `Admin > Structure > Webforms > Settings`.
2. Click on **Handlers** and add the **PayPal Webform Handler**.
3. Configure the following fields:
   - **Price Field**: Select the webform field containing the payment amount.
   - **Quantity Field**: Select the webform field containing the quantity.
   - **Transaction ID Field**: Select the webform field to store the PayPal transaction ID.
   - **Currency Field**: Choose a currency from the dynamically fetched list.
4. Save the configuration.

## Usage
Once configured, whenever a user submits the form, the module will process the payment via PayPal using the provided details.

## Troubleshooting
- If currency options are not loading, ensure that the REST Countries API is accessible.
- Check the Drupal logs (`/admin/reports/dblog`) for any module-specific errors.
- Ensure Webform and PayPal settings are correctly configured.

## Credits
Developed by Anushri Kumari (anushrikumari)
