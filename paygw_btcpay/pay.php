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
 * Payment initiation: create BTCPay invoice and redirect user to checkout.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/lib.php');
paygw_btcpay_require_config();

// Ensure curl class is loaded (needed for BTCPay API client)
require_once($CFG->libdir . '/filelib.php');

require_login();

$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);
$paymentid = required_param('paymentid', PARAM_INT);

global $DB, $USER;

$payment = $DB->get_record('payments', ['id' => $paymentid]);
if (!$payment || (int) $payment->userid !== (int) $USER->id) {
    throw new \moodle_exception('error_payment_not_found', 'paygw_btcpay');
}

$config = \core_payment\helper::get_gateway_configuration($component, $paymentarea, $itemid, 'btcpay');
if (empty($config['btcpay_base_url']) || empty($config['btcpay_api_key']) || empty($config['btcpay_store_id'])) {
    throw new \moodle_exception('error_invalid_config', 'paygw_btcpay');
}

$existing = $DB->get_record('paygw_btcpay_txn', ['paymentid' => $paymentid]);
if ($existing && !empty($existing->btcpay_checkout_url)) {
    redirect($existing->btcpay_checkout_url, '', 0);
}

$successurl = \core_payment\helper::get_success_url($component, $paymentarea, $itemid);
$returnurl = new \moodle_url('/payment/gateway/btcpay/return.php', ['paymentid' => $paymentid]);
$redirecturl = $returnurl->out(false);

$expiration = isset($config['btcpay_invoice_expiration_minutes']) ? (int) $config['btcpay_invoice_expiration_minutes'] : 60;
$expiration = max(1, min(1440, $expiration));

try {
    $client = new \paygw_btcpay\client(
        $config['btcpay_base_url'],
        $config['btcpay_api_key'],
        $config['btcpay_store_id']
    );
    $invoice = $client->create_invoice(
        (float) $payment->amount,
        $payment->currency,
        ['orderId' => 'moodle:' . $paymentid],
        $redirecturl,
        $expiration
    );
} catch (\Throwable $e) {
    throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null, $e->getMessage());
}

$txn = (object) [
    'timecreated' => time(),
    'timemodified' => time(),
    'paymentid' => $paymentid,
    'component' => $payment->component,
    'paymentarea' => $payment->paymentarea,
    'itemid' => $payment->itemid,
    'userid' => $payment->userid,
    'amount' => (string) $payment->amount,
    'currency' => $payment->currency,
    'btcpay_invoice_id' => $invoice['id'],
    'btcpay_checkout_url' => $invoice['checkoutLink'] ?? '',
    'btcpay_status' => $invoice['status'] ?? 'New',
    'delivered' => 0,
];
$DB->insert_record('paygw_btcpay_txn', $txn);

redirect($invoice['checkoutLink'] ?? $successurl, '', 0);
