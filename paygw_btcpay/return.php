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
 * User return page after payment attempt. Informational only; does not trigger fulfillment.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/lib.php');
paygw_btcpay_require_config();
require_login();

$paymentid = required_param('paymentid', PARAM_INT);

global $DB, $USER, $PAGE, $OUTPUT;

$txn = $DB->get_record('paygw_btcpay_txn', ['paymentid' => $paymentid, 'userid' => $USER->id]);
if (!$txn) {
    throw new \moodle_exception('error_payment_not_found', 'paygw_btcpay');
}

$PAGE->set_url(new \moodle_url('/payment/gateway/btcpay/return.php', ['paymentid' => $paymentid]));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('pluginname', 'paygw_btcpay'));
$PAGE->set_heading(get_string('pluginname', 'paygw_btcpay'));

if ((int) $txn->delivered === 1) {
    $message = get_string('payment_received', 'paygw_btcpay');
} else if ($txn->btcpay_status === 'Settled') {
    $message = get_string('payment_received_processing', 'paygw_btcpay');
} else if ($txn->btcpay_status === 'Processing') {
    $message = get_string('payment_processing', 'paygw_btcpay');
} else if ($txn->btcpay_status === 'Expired') {
    $message = get_string('payment_expired', 'paygw_btcpay');
} else {
    $message = get_string('payment_pending', 'paygw_btcpay');
}

$successurl = \core_payment\helper::get_success_url($txn->component, $txn->paymentarea, $txn->itemid);

// If payment is already delivered, redirect immediately
if ((int) $txn->delivered === 1) {
    redirect($successurl, get_string('payment_received', 'paygw_btcpay'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($message);
echo html_writer::div(
    html_writer::link($successurl, get_string('returntocourse', 'paygw_btcpay'), ['class' => 'btn btn-primary']),
    'mt-3'
);
// Show note about webhook processing if payment is settled but not yet delivered
if ($txn->btcpay_status === 'Settled' && (int) $txn->delivered === 0) {
    echo html_writer::div(
        get_string('payment_webhook_processing', 'paygw_btcpay'),
        'alert alert-info mt-3'
    );
}
echo $OUTPUT->footer();
