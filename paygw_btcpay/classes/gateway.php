<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * BTCPay Server payment gateway.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_btcpay;

defined('MOODLE_INTERNAL') || die();

/**
 * Gateway class for BTCPay Server (static methods only).
 */
class gateway extends \core_payment\gateway {

    /**
     * Returns the list of currencies that the payment gateway supports.
     *
     * @return string[] ISO 4217 currency codes
     */
    public static function get_supported_currencies(): array {
        return [
            'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NOK', 'DKK',
            'PLN', 'BRL', 'MXN', 'INR', 'KRW', 'SGD', 'HKD', 'NZD', 'ZAR',
        ];
    }

    /**
     * Configuration form for the gateway instance.
     *
     * @param \core_payment\form\account_gateway $form The gateway form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'btcpay_base_url', get_string('btcpay_base_url', 'paygw_btcpay'));
        $mform->setType('btcpay_base_url', PARAM_URL);
        $mform->addHelpButton('btcpay_base_url', 'btcpay_base_url', 'paygw_btcpay');
        $mform->addRule('btcpay_base_url', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'btcpay_store_id', get_string('btcpay_store_id', 'paygw_btcpay'));
        $mform->setType('btcpay_store_id', PARAM_TEXT);
        $mform->addHelpButton('btcpay_store_id', 'btcpay_store_id', 'paygw_btcpay');
        $mform->addRule('btcpay_store_id', get_string('required'), 'required', null, 'client');

        $mform->addElement('passwordunmask', 'btcpay_api_key', get_string('btcpay_api_key', 'paygw_btcpay'));
        $mform->setType('btcpay_api_key', PARAM_TEXT);
        $mform->addHelpButton('btcpay_api_key', 'btcpay_api_key', 'paygw_btcpay');
        $mform->addRule('btcpay_api_key', get_string('required'), 'required', null, 'client');

        $mform->addElement('passwordunmask', 'btcpay_webhook_secret', get_string('btcpay_webhook_secret', 'paygw_btcpay'));
        $mform->setType('btcpay_webhook_secret', PARAM_TEXT);
        $mform->addHelpButton('btcpay_webhook_secret', 'btcpay_webhook_secret', 'paygw_btcpay');
        $mform->addRule('btcpay_webhook_secret', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'btcpay_fulfill_status', get_string('btcpay_fulfill_status', 'paygw_btcpay'), [
            'Settled' => get_string('fulfill_status_settled', 'paygw_btcpay'),
            'Processing' => get_string('fulfill_status_processing', 'paygw_btcpay'),
        ]);
        $mform->setDefault('btcpay_fulfill_status', 'Settled');
        $mform->addHelpButton('btcpay_fulfill_status', 'btcpay_fulfill_status', 'paygw_btcpay');

        $mform->addElement('text', 'btcpay_invoice_expiration_minutes',
            get_string('btcpay_invoice_expiration_minutes', 'paygw_btcpay'));
        $mform->setType('btcpay_invoice_expiration_minutes', PARAM_INT);
        $mform->setDefault('btcpay_invoice_expiration_minutes', 60);
        $mform->addHelpButton('btcpay_invoice_expiration_minutes', 'btcpay_invoice_expiration_minutes', 'paygw_btcpay');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form The form
     * @param \stdClass $data Submitted data
     * @param array $files Uploaded files
     * @param array $errors Errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
            \stdClass $data, array $files, array &$errors): void {
        if (empty($data->enabled)) {
            return;
        }
        if (empty($data->btcpay_base_url)) {
            $errors['btcpay_base_url'] = get_string('required');
            return;
        }
        $url = $data->btcpay_base_url;
        if (strpos($url, 'https://') !== 0) {
            $errors['btcpay_base_url'] = get_string('btcpay_base_url_desc', 'paygw_btcpay');
        }
        if (empty($data->btcpay_store_id)) {
            $errors['btcpay_store_id'] = get_string('required');
        }
        if (empty($data->btcpay_api_key)) {
            $errors['btcpay_api_key'] = get_string('required');
        }
        if (empty($data->btcpay_webhook_secret)) {
            $errors['btcpay_webhook_secret'] = get_string('required');
        }
        $exp = isset($data->btcpay_invoice_expiration_minutes) ? (int) $data->btcpay_invoice_expiration_minutes : 0;
        if ($exp < 1 || $exp > 1440) {
            $errors['btcpay_invoice_expiration_minutes'] = get_string('btcpay_invoice_expiration_minutes_desc', 'paygw_btcpay');
        }
    }
}
