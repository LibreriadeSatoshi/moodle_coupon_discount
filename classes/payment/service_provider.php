<?php
// This file is part of Moodle - http://moodle.org/

namespace enrol_coupon_discount\payment;

class service_provider implements \core_payment\local\callback\service_provider { // NOSONAR - Moodle payment API requires snake_case class name for autoloading

    /**
     * Returns the payable amount for the given payment area and instance.
     *
     * @param string $paymentarea
     * @param int $instanceid
     * @return \core_payment\local\entities\payable
     */
    public static function get_payable(string $paymentarea, int $instanceid): \core_payment\local\entities\payable {
        global $DB, $SESSION, $USER;

        $instance = $DB->get_record('enrol', ['enrol' => 'coupon_discount', 'id' => $instanceid], '*', MUST_EXIST);

        $cost = (float) $instance->cost;

        if (isset($SESSION->coupon_discount[$instanceid])) {
            $discount = $SESSION->coupon_discount[$instanceid]['discount_percent'];
            $cost     = $cost - ($cost * ($discount / 100));

        } elseif (isloggedin() && !isguestuser()) {
            $usage = $DB->get_record('enrol_coupon_discount_usage', [
                'instanceid' => $instanceid,
                'userid'     => $USER->id,
            ]);
            if ($usage) {
                $cost = $cost - ($cost * ($usage->discount_percent / 100));
            }
        }

        return new \core_payment\local\entities\payable($cost, $instance->currency, $instance->customint1);
    }

    /**
     * Returns the success URL after payment completion.
     *
     * @param string $paymentarea
     * @param int $instanceid
     * @return \moodle_url
     */
    public static function get_success_url(string $paymentarea, int $instanceid): \moodle_url {
        global $DB;
        $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'coupon_discount', 'id' => $instanceid], MUST_EXIST);
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }

    /**
     * Delivers the order after successful payment by enrolling the user.
     *
     * @param string $paymentarea
     * @param int $instanceid
     * @param int $paymentid
     * @param int $userid
     * @return bool
     */
    public static function deliver_order(string $paymentarea, int $instanceid, int $paymentid, int $userid): bool {
        global $DB, $SESSION;

        $instance = $DB->get_record('enrol', ['enrol' => 'coupon_discount', 'id' => $instanceid], '*', MUST_EXIST);

        $alreadyenrolled = $DB->record_exists('user_enrolments', [
            'userid'  => $userid,
            'enrolid' => $instanceid,
        ]);
        if ($alreadyenrolled) {
            return true;
        }

        $plugin = enrol_get_plugin('coupon_discount');

        if ($instance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        $plugin->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);

        $DB->delete_records('enrol_coupon_discount_usage', [
            'instanceid' => $instanceid,
            'userid'     => $userid,
        ]);

        if (isset($SESSION->coupon_discount[$instanceid])) {
            unset($SESSION->coupon_discount[$instanceid]);
        }

        return true;
    }
}
