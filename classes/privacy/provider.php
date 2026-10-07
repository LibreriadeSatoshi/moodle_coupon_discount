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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace enrol_coupon_discount\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy support for coupon usage, global email restrictions, and enrolment payments.
 *
 * @package enrol_coupon_discount
 * @copyright 2026 Bitcoin Diploma
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_payment\privacy\consumer_provider {

    /**
     * Describe stored personal data.
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('enrol_coupon_discount_usage', [
            'userid' => 'privacy:metadata:usage:userid',
            'instanceid' => 'privacy:metadata:usage:instanceid',
            'couponid' => 'privacy:metadata:usage:couponid',
            'discount_percent' => 'privacy:metadata:usage:discount_percent',
            'payment_status' => 'privacy:metadata:usage:payment_status',
            'timecreated' => 'privacy:metadata:usage:timecreated',
        ], 'privacy:metadata:usage');
        $collection->add_database_table('enrol_coupon_discount_codes', [
            'allowed_emails' => 'privacy:metadata:codes:allowed_emails',
        ], 'privacy:metadata:codes');
        $collection->add_subsystem_link('core_payment', [], 'privacy:metadata:payments');
        return $collection;
    }

    /**
     * Locate usage and payments in courses, with orphaned data and restrictions in the system context.
     * @param int $userid User ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contexts = new contextlist();
        foreach ([false, true] as $payments) {
            [$sql, $params] = self::record_query('COALESCE(c.id, ' . SYSCONTEXTID . ') AS contextid',
                null, [$userid], $payments);
            $contexts->add_from_sql($sql, $params);
        }
        $email = $DB->get_field('user', 'email', ['id' => $userid]);
        if ($email && self::coupons_for_email($email)) {
            $contexts->add_system_context();
        }
        return $contexts;
    }

    /**
     * Map a payment to its enrolment course.
     * @param string $paymentarea Payment area
     * @param int $itemid Enrolment instance ID
     * @return int|null
     */
    public static function get_contextid_for_payment(string $paymentarea, int $itemid): ?int {
        global $DB;
        if ($paymentarea !== 'coupon_discount') {
            return null;
        }
        $id = $DB->get_field_sql("SELECT c.id FROM {enrol} e
            JOIN {context} c ON c.instanceid = e.courseid AND c.contextlevel = :level
            WHERE e.id = :id AND e.enrol = :enrol", [
                'level' => CONTEXT_COURSE, 'id' => $itemid, 'enrol' => 'coupon_discount',
            ]);
        return $id ? (int) $id : null;
    }

    /**
     * Find users with data in the requested context.
     * @param userlist $userlist Requested context and collected users
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!self::supported_context($context)) {
            return;
        }
        foreach ([false, true] as $payments) {
            [$sql, $params] = self::record_query('d.userid', $context, null, $payments);
            $userlist->add_from_sql('userid', $sql, $params);
        }
        if ($context instanceof \context_system) {
            $coupons = $DB->get_recordset('enrol_coupon_discount_codes');
            foreach ($coupons as $coupon) {
                $emails = self::email_list($coupon->allowed_emails);
                if ($emails) {
                    [$insql, $params] = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED);
                    $userlist->add_from_sql('id', 'SELECT id FROM {user} WHERE LOWER(email) ' . $insql, $params);
                }
            }
            $coupons->close();
        }
    }

    /**
     * Export approved user data without revealing other coupon recipients.
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        $path = [get_string('pluginname', 'enrol_coupon_discount')];
        foreach ($contextlist as $context) {
            if (!self::supported_context($context)) {
                continue;
            }
            [$sql, $params] = self::record_query('d.*', $context, [$user->id]);
            $usage = array_values($DB->get_records_sql($sql, $params));
            foreach ($usage as $record) {
                $record->timecreated = transform::datetime($record->timecreated);
            }
            $coupons = [];
            if ($context instanceof \context_system) {
                foreach (self::coupons_for_email($user->email) as $coupon) {
                    $coupons[] = (object) [
                        'code' => $coupon->code,
                        'allowed_email' => \core_text::strtolower(trim($user->email)),
                        'discount_percent' => $coupon->discount_percent,
                        'expirydate' => $coupon->expirydate ? transform::datetime($coupon->expirydate) : null,
                    ];
                }
            }
            if ($usage || $coupons) {
                writer::with_context($context)->export_data($path, (object) ['usage' => $usage, 'coupons' => $coupons]);
            }
            [$sql, $params] = self::record_query('d.*', $context, [$user->id], true);
            $payments = $DB->get_recordset_sql($sql, $params);
            foreach ($payments as $payment) {
                \core_payment\privacy\provider::export_payment_data_for_user_in_context($context, $path, $user->id,
                    'enrol_coupon_discount', $payment->paymentarea, $payment->itemid);
            }
            $payments->close();
        }
    }

    /**
     * Delete all personal data in a context.
     * @param \context $context Approved context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        self::delete_context_data($context, null);
    }

    /**
     * Delete one user's data in approved contexts.
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist as $context) {
            self::delete_context_data($context, [$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete only the approved users in one context.
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_userids()) {
            self::delete_context_data($userlist->get_context(), $userlist->get_userids());
        }
    }

    /**
     * Delete scoped usage, delegated payments, and global email restrictions.
     * @param \context $context Context to delete
     * @param array|null $userids User IDs, or null for every user
     */
    private static function delete_context_data(\context $context, ?array $userids): void {
        global $DB;
        if (!self::supported_context($context)) {
            return;
        }
        [$sql, $params] = self::record_query('d.id', $context, $userids);
        $DB->delete_records_subquery('enrol_coupon_discount_usage', 'id', 'id', $sql, $params);
        [$sql, $params] = self::record_query('d.id', $context, $userids, true);
        \core_payment\privacy\provider::delete_data_for_payment_sql($sql, $params);
        if (!$context instanceof \context_system) {
            return;
        }
        $emails = null;
        if ($userids !== null) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $emails = array_map(fn($email) => \core_text::strtolower(trim($email)),
                $DB->get_fieldset_select('user', 'email', 'id ' . $insql, $params));
        }
        $coupons = $DB->get_recordset('enrol_coupon_discount_codes');
        foreach ($coupons as $coupon) {
            $allowed = self::email_list($coupon->allowed_emails);
            $remaining = $emails === null ? [] : array_values(array_diff($allowed, $emails));
            if ($allowed !== $remaining) {
                $update = (object) ['id' => $coupon->id, 'allowed_emails' => implode(',', $remaining)];
                if (!$remaining) {
                    // An empty restriction allows everyone. Expire this coupon before clearing the last address.
                    $update->expirydate = 1;
                }
                $DB->update_record('enrol_coupon_discount_codes', $update);
            }
        }
        $coupons->close();
    }

    /**
     * Build a scoped query, preserving records whose enrolment or course has been removed.
     * @param string $fields Selected fields
     * @param \context|null $context Optional context filter
     * @param array|null $userids Optional user filter
     * @param bool $payments Select delegated payments instead of coupon usage
     * @return array SQL and parameters
     */
    private static function record_query(string $fields, ?\context $context, ?array $userids, bool $payments = false): array {
        global $DB;
        $table = $payments ? 'payments' : 'enrol_coupon_discount_usage';
        $instancefield = $payments ? 'itemid' : 'instanceid';
        $sql = "SELECT $fields FROM {{$table}} d
            LEFT JOIN {enrol} e ON e.id = d.$instancefield AND e.enrol = :enrol
            LEFT JOIN {context} c ON c.instanceid = e.courseid AND c.contextlevel = :level
            WHERE 1 = 1";
        $params = ['enrol' => 'coupon_discount', 'level' => CONTEXT_COURSE];
        if ($payments) {
            $sql .= ' AND d.component = :component';
            $params['component'] = 'enrol_coupon_discount';
        }
        if ($context !== null) {
            $sql .= ' AND COALESCE(c.id, ' . SYSCONTEXTID . ') = :contextid';
            $params['contextid'] = $context->id;
        }
        if ($userids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $sql .= " AND d.userid $insql";
            $params += $inparams;
        }
        return [$sql, $params];
    }

    /**
     * Whether the context can contain coupon data.
     * @param \context $context Context to inspect
     * @return bool
     */
    private static function supported_context(\context $context): bool {
        return $context instanceof \context_course || $context instanceof \context_system;
    }

    /**
     * Parse complete normalized email addresses, never substrings.
     * @param string|null $value Comma separated addresses
     * @return array
     */
    private static function email_list(?string $value): array {
        return array_values(array_filter(array_map(fn($email) => \core_text::strtolower(trim($email)),
            explode(',', $value ?? ''))));
    }

    /**
     * Find coupons listing this email without leaking the other recipients.
     * @param string $email User email
     * @return array
     */
    private static function coupons_for_email(string $email): array {
        global $DB;
        $matches = [];
        $coupons = $DB->get_recordset('enrol_coupon_discount_codes');
        foreach ($coupons as $coupon) {
            if (in_array(\core_text::strtolower(trim($email)), self::email_list($coupon->allowed_emails), true)) {
                $matches[] = $coupon;
            }
        }
        $coupons->close();
        return $matches;
    }
}
