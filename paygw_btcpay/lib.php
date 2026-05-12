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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Helper to load Moodle config from pay.php, return.php, webhook.php (they run before Moodle bootstrap).
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Find and require Moodle config.php. Use from pay.php, return.php, webhook.php.
 *
 * Tries: (1) MOODLE_CONFIG_PATH env, (2) standard plugin path ../../../config.php,
 * (3) repo layout ../../../.moodle/config.php.
 *
 * @throws \Exception if config not found or not readable
 */
function paygw_btcpay_require_config(): void {
    $pluginroot = __DIR__;
    $configpath = getenv('MOODLE_CONFIG_PATH');
    if (!$configpath || !is_readable($configpath)) {
        $configpath = $pluginroot . '/../../../config.php';
    }
    if (!is_readable($configpath)) {
        $configpath = $pluginroot . '/../../../.moodle/config.php';
    }
    if (!is_readable($configpath)) {
        throw new \Exception(
            'Cannot find Moodle config.php. Set MOODLE_CONFIG_PATH or ensure the plugin is at moodle_root/payment/gateway/btcpay/.'
        );
    }
    require_once($configpath);
}
