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
 * Webhook event processor for BTCPay Server.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_btcpay\webhook;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes verified webhook events and delivers orders when appropriate.
 */
class handler {

    /**
     * Process a verified webhook event (idempotent).
     *
     * @param array $eventdata Parsed JSON webhook payload
     * @param string $invoiceid Invoice ID from payload
     */
    public static function process_webhook(array $eventdata, string $invoiceid): void {
        global $DB;

        $txn = $DB->get_record('paygw_btcpay_txn', ['btcpay_invoice_id' => $invoiceid]);
        if (!$txn) {
            return;
        }

        $newstatus = self::extract_status_from_payload($eventdata);
        if ($newstatus !== null) {
            $txn->btcpay_status = $newstatus;
            $txn->timemodified = time();
            $DB->update_record('paygw_btcpay_txn', $txn);
        }

        if ((int) $txn->delivered === 1) {
            return;
        }

        try {
            $config = \core_payment\helper::get_gateway_configuration(
                $txn->component,
                $txn->paymentarea,
                $txn->itemid,
                'btcpay'
            );
        } catch (\Throwable $e) {
            return;
        }

        $fulfillstatus = $config['btcpay_fulfill_status'] ?? 'Settled';
        if ($newstatus === null) {
            $newstatus = $txn->btcpay_status;
        }
        if ($newstatus !== $fulfillstatus) {
            return;
        }

        try {
            \core_payment\helper::deliver_order(
                $txn->component,
                $txn->paymentarea,
                (int) $txn->itemid,
                (int) $txn->paymentid,
                (int) $txn->userid
            );
        } catch (\Throwable $e) {
            return;
        }

        $txn->delivered = 1;
        $txn->timemodified = time();
        $DB->update_record('paygw_btcpay_txn', $txn);
    }

    /**
     * Extract invoice status from webhook payload.
     *
     * BTCPay Server sends either data.status/status or a "type" (e.g. InvoiceSettled).
     *
     * @param array $eventdata Decoded webhook JSON
     * @return string|null Status (e.g. Settled, Processing) or null if not found
     */
    protected static function extract_status_from_payload(array $eventdata): ?string {
        if (isset($eventdata['data']['status']) && is_string($eventdata['data']['status'])) {
            return $eventdata['data']['status'];
        }
        if (isset($eventdata['status']) && is_string($eventdata['status'])) {
            return $eventdata['status'];
        }
        // BTCPay Greenfield webhook uses "type" (e.g. InvoiceSettled, InvoiceProcessing).
        if (isset($eventdata['type']) && is_string($eventdata['type'])) {
            return self::map_webhook_type_to_status($eventdata['type']);
        }
        return null;
    }

    /**
     * Map BTCPay webhook event type to invoice status string.
     *
     * @param string $type e.g. InvoiceSettled, InvoiceProcessing
     * @return string|null Status (Settled, Processing, New, Expired, Invalid) or null
     */
    protected static function map_webhook_type_to_status(string $type): ?string {
        $map = [
            'InvoiceSettled' => 'Settled',
            'InvoiceProcessing' => 'Processing',
            'InvoiceCreated' => 'New',
            'InvoiceExpired' => 'Expired',
            'InvoiceInvalid' => 'Invalid',
            'InvoicePaymentSettled' => 'Settled',
        ];
        return $map[$type] ?? null;
    }
}
