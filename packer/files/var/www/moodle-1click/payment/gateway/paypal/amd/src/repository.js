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
 * PayPal repository module to encapsulate all of the AJAX requests that can be sent for PayPal.
 *
 * @module     paygw_paypal/repository
 * @package    paygw_paypal
 * @copyright  2020 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Return the PayPal JavaScript SDK URL.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<{clientid: string, brandname: string, cost: number, currency: string}>}
 */
export const getConfigForJs = (component, paymentArea, itemId) => {
    const request = {
        methodname: 'paygw_paypal_get_config_for_js',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
        },
    };

    return Ajax.call([request])[0];
};

/**
 * Call server to validate and capture payment for order.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} orderId The order id coming back from PayPal
 * @returns {*}
 */
export const markTransactionComplete = (component, paymentArea, itemId, orderId) => {
    const request = {
        methodname: 'paygw_paypal_create_transaction_complete',
        args: {
            component,
            paymentarea: paymentArea,
            itemid: itemId,
            orderid: orderId,
        },
    };

    return Ajax.call([request])[0];
};
