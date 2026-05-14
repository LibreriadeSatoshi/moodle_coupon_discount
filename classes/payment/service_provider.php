<?php
// This file is part of Moodle - http://moodle.org/

namespace enrol_coupon_discount\payment;

class service_provider implements \core_payment\local\callback\service_provider {

    public static function get_payable(string $paymentarea, int $instanceid): \core_payment\local\entities\payable {
        global $DB, $SESSION;

        $instance = $DB->get_record('enrol', ['enrol' => 'coupon_discount', 'id' => $instanceid], '*', MUST_EXIST);

        $cost = (float) $instance->cost;

        // Apply discount if there is a validated coupon in the session
        if (isset($SESSION->coupon_discount[$instanceid])) {
            $discount_percent = $SESSION->coupon_discount[$instanceid]['discount_percent'];
            $cost = $cost - ($cost * ($discount_percent / 100));
        }

        return new \core_payment\local\entities\payable($cost, $instance->currency, $instance->customint1);
    }

    public static function get_success_url(string $paymentarea, int $instanceid): \moodle_url {
        global $DB;
        $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'coupon_discount', 'id' => $instanceid], MUST_EXIST);
        return new \moodle_url('/course/view.php', ['id' => $courseid]);
    }

    public static function deliver_order(string $paymentarea, int $instanceid, int $paymentid, int $userid): bool {
        global $DB;

        $instance = $DB->get_record('enrol', ['enrol' => 'coupon_discount', 'id' => $instanceid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('coupon_discount');

        if ($instance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        $plugin->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend);

        return true;
    }
}
