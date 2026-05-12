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
 * Webhook receiver for BTCPay Server. Public endpoint; verification is by signature.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/lib.php');
paygw_btcpay_require_config();

$rawbody = file_get_contents('php://input');
if ($rawbody === false) {
    http_response_code(400);
    exit;
}

$signatureheader = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'btcpay-sig') {
            $signatureheader = $value;
            break;
        }
    }
}
if ($signatureheader === '' && !empty($_SERVER['HTTP_BTCPAY_SIG'])) {
    $signatureheader = $_SERVER['HTTP_BTCPAY_SIG'];
}
if ($signatureheader === '') {
    http_response_code(403);
    exit;
}

$eventdata = json_decode($rawbody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit;
}

$invoiceid = $eventdata['invoiceId'] ?? $eventdata['data']['id'] ?? $eventdata['data']['invoiceId'] ?? null;
if ($invoiceid === null || $invoiceid === '') {
    http_response_code(400);
    exit;
}

global $DB;

$txn = $DB->get_record('paygw_btcpay_txn', ['btcpay_invoice_id' => $invoiceid]);
if (!$txn) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

try {
    $config = \core_payment\helper::get_gateway_configuration(
        $txn->component,
        $txn->paymentarea,
        $txn->itemid,
        'btcpay'
    );
} catch (\Throwable $e) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

$webhooksecret = $config['btcpay_webhook_secret'] ?? '';
if ($webhooksecret === '' || !\paygw_btcpay\client::verify_webhook_signature($rawbody, $signatureheader, $webhooksecret)) {
    http_response_code(403);
    exit;
}

\paygw_btcpay\webhook\handler::process_webhook($eventdata, $invoiceid);

http_response_code(200);
echo json_encode(['status' => 'ok']);
