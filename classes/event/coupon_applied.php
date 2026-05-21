<?php
// This file is part of Moodle - http://moodle.org/

namespace enrol_coupon_discount\event;

defined('MOODLE_INTERNAL') || die();

class coupon_applied extends \core\event\base { // NOSONAR - Moodle payment API requires snake_case class name for autoloading

    protected function init() {
        $this->data['objecttable'] = 'enrol_coupon_discount_codes';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('pluginname', 'enrol_coupon_discount') . ' - Coupon Applied';
    }

    public function get_description() {
        return "The user with id '{$this->userid}' applied the coupon '{$this->other['couponcode']}' " .
               "with a discount of '{$this->other['discount_percent']}%' to enrolment instance '{$this->other['instanceid']}'.";
    }

    public function get_url() {
        return new \moodle_url('/enrol/coupon_discount/verify_coupon.php', [
            'id' => $this->courseid,
            'instanceid' => $this->other['instanceid'],
        ]);
    }
}
