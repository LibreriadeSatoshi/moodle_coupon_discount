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
 * External API to create a payment record and return redirect URL to pay.php.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_btcpay\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * create_payment external function.
 */
class create_payment extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area'),
            'itemid' => new external_value(PARAM_INT, 'Item id'),
        ]);
    }

    /**
     * Create payment and return redirect URL to pay.php.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array { redirecturl: string }
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), compact('component', 'paymentarea', 'itemid'));

        $payable = \core_payment\helper::get_payable($component, $paymentarea, $itemid);
        $accountid = $payable->get_account_id();
        $amount = \core_payment\helper::get_rounded_cost(
            $payable->get_amount(),
            $payable->get_currency(),
            \core_payment\helper::get_gateway_surcharge('btcpay')
        );
        $currency = $payable->get_currency();

        $paymentid = \core_payment\helper::save_payment(
            $accountid,
            $component,
            $paymentarea,
            $itemid,
            (int) $USER->id,
            $amount,
            $currency,
            'btcpay'
        );

        $url = new \moodle_url('/payment/gateway/btcpay/pay.php', [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'paymentid' => $paymentid,
        ]);

        return ['redirecturl' => $url->out(false)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'redirecturl' => new external_value(PARAM_URL, 'Redirect URL to pay.php'),
        ]);
    }
}
