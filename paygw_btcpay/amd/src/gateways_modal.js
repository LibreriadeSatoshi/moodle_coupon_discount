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
 * BTCPay Server gateway modal: create payment and redirect to pay.php.
 *
 * @module     paygw_btcpay/gateways_modal
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

/**
 * Process the payment by creating a payment record and redirecting to BTCPay checkout.
 *
 * @param {string} component Name of the component
 * @param {string} paymentArea Payment area
 * @param {number} itemId Item id
 * @param {string} description Description (unused but required by core modal)
 * @returns {Promise<string>} Resolves with success message (after redirect, so rarely shown)
 */
export const process = async(component, paymentArea, itemId, description) => {
    let result;
    try {
        result = await Ajax.call([{
            methodname: 'paygw_btcpay_create_payment',
            args: {
                component,
                paymentarea: paymentArea,
                itemid: itemId,
            },
        }])[0];
    } catch (e) {
        const msg = (e && (e.message || e.error)) ? String(e.message || e.error) : null;
        const fallback = await getString('error_invoice_creation_failed', 'paygw_btcpay');
        return Promise.reject(msg || fallback);
    }
    if (result && result.redirecturl && typeof result.redirecturl === 'string' && result.redirecturl.trim() !== '') {
        // Use replace() instead of href to prevent back button issues and ensure immediate navigation
        window.location.replace(result.redirecturl);
        // Return a promise that never resolves to prevent core modal from redirecting to successurl
        return new Promise(() => {});
    }
    const err = await getString('error_invoice_creation_failed', 'paygw_btcpay');
    return Promise.reject(err);
};
