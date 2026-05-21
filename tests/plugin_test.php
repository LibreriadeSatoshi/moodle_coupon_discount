<?php
// This file is part of Moodle - http://moodle.org/

namespace enrol_coupon_discount;

use enrol_coupon_discount\payment\service_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/enrol/coupon_discount/lib.php';

class plugin_test extends \advanced_testcase { // NOSONAR

    public function test_roles_protected_returns_true() {
        $plugin = new \enrol_coupon_discount_plugin();
        $this->assertTrue($plugin->roles_protected());
    }

    public function test_get_name() {
        $plugin = new \enrol_coupon_discount_plugin();
        $this->assertEquals('coupon_discount', $plugin->get_name());
    }

    public function test_get_possible_currencies_returns_array() {
        $plugin = new \enrol_coupon_discount_plugin();
        $currencies = $plugin->get_possible_currencies();
        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);
    }

    public function test_coupon_crud() {
        $this->resetAfterTest();
        global $DB;

        $coupon = new \stdClass();
        $coupon->code = 'TEST10';
        $coupon->discount_percent = 10.00;
        $coupon->allowed_emails = '';
        $coupon->expirydate = 0;
        $coupon->description = 'Test coupon';
        $coupon->max_uses = 5;
        $coupon->timecreated = time();

        $id = $DB->insert_record('enrol_coupon_discount_codes', $coupon);
        $this->assertGreaterThan(0, $id);

        $record = $DB->get_record('enrol_coupon_discount_codes', ['id' => $id]);
        $this->assertEquals('TEST10', $record->code);
        $this->assertEquals(10.00, $record->discount_percent);
        $this->assertEquals(5, $record->max_uses);

        $record->discount_percent = 20.00;
        $DB->update_record('enrol_coupon_discount_codes', $record);

        $updated = $DB->get_record('enrol_coupon_discount_codes', ['id' => $id]);
        $this->assertEquals(20.00, $updated->discount_percent);

        $DB->delete_records('enrol_coupon_discount_codes', ['id' => $id]);
        $exists = $DB->record_exists('enrol_coupon_discount_codes', ['id' => $id]);
        $this->assertFalse($exists);
    }

    public function test_coupon_unique_code() {
        $this->resetAfterTest();
        global $DB;

        $coupon = new \stdClass();
        $coupon->code = 'UNIQUE';
        $coupon->discount_percent = 15.00;
        $coupon->allowed_emails = '';
        $coupon->expirydate = 0;
        $coupon->description = '';
        $coupon->max_uses = 0;
        $coupon->timecreated = time();

        $DB->insert_record('enrol_coupon_discount_codes', $coupon);

        $duplicate = clone $coupon;
        $duplicate->discount_percent = 25.00;

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('enrol_coupon_discount_codes', $duplicate);
    }

    public function test_coupon_usage_tracking() {
        $this->resetAfterTest();
        global $DB;

        $coupon = new \stdClass();
        $coupon->code = 'USAGE1';
        $coupon->discount_percent = 10.00;
        $coupon->allowed_emails = '';
        $coupon->expirydate = 0;
        $coupon->description = '';
        $coupon->max_uses = 0;
        $coupon->timecreated = time();
        $couponid = $DB->insert_record('enrol_coupon_discount_codes', $coupon);

        $usage = new \stdClass();
        $usage->instanceid = 1;
        $usage->userid = 2;
        $usage->couponid = $couponid;
        $usage->discount_percent = 10.00;
        $usage->timecreated = time();

        $usageid = $DB->insert_record('enrol_coupon_discount_usage', $usage);
        $this->assertGreaterThan(0, $usageid);

        $count = $DB->count_records('enrol_coupon_discount_usage', ['couponid' => $couponid]);
        $this->assertEquals(1, $count);
    }

    public function test_max_uses_validation() {
        $this->resetAfterTest();
        global $DB;

        $coupon = new \stdClass();
        $coupon->code = 'LIMITED';
        $coupon->discount_percent = 25.00;
        $coupon->allowed_emails = '';
        $coupon->expirydate = 0;
        $coupon->description = '';
        $coupon->max_uses = 2;
        $coupon->timecreated = time();
        $couponid = $DB->insert_record('enrol_coupon_discount_codes', $coupon);

        $usage1 = new \stdClass();
        $usage1->instanceid = 1;
        $usage1->userid = 1;
        $usage1->couponid = $couponid;
        $usage1->discount_percent = 25.00;
        $usage1->timecreated = time();
        $DB->insert_record('enrol_coupon_discount_usage', $usage1);

        $usage2 = new \stdClass();
        $usage2->instanceid = 1;
        $usage2->userid = 2;
        $usage2->couponid = $couponid;
        $usage2->discount_percent = 25.00;
        $usage2->timecreated = time();
        $DB->insert_record('enrol_coupon_discount_usage', $usage2);

        $totaluses = $DB->count_records('enrol_coupon_discount_usage', ['couponid' => $couponid]);
        $this->assertEquals(2, $totaluses);

        $couponrecord = $DB->get_record('enrol_coupon_discount_codes', ['id' => $couponid]);
        $this->assertTrue($totaluses >= $couponrecord->max_uses);
    }

    public function test_unlimited_max_uses() {
        $this->resetAfterTest();
        global $DB;

        $coupon = new \stdClass();
        $coupon->code = 'UNLIMITED';
        $coupon->discount_percent = 50.00;
        $coupon->allowed_emails = '';
        $coupon->expirydate = 0;
        $coupon->description = '';
        $coupon->max_uses = 0;
        $coupon->timecreated = time();
        $DB->insert_record('enrol_coupon_discount_codes', $coupon);

        $record = $DB->get_record('enrol_coupon_discount_codes', ['code' => 'UNLIMITED']);
        $this->assertEquals(0, $record->max_uses);
    }

    public function test_service_provider_get_payable_without_discount() {
        $this->resetAfterTest();
        global $DB;

        $courseid = $this->getDataGenerator()->create_course()->id;
        $instance = new \stdClass();
        $instance->courseid = $courseid;
        $instance->enrol = 'coupon_discount';
        $instance->status = 0;
        $instance->name = 'Test';
        $instance->cost = 100.00;
        $instance->currency = 'USD';
        $instance->customint1 = 0;
        $instance->roleid = 5;
        $instance->enrolperiod = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instanceid = $DB->insert_record('enrol', $instance);

        $payable = service_provider::get_payable('coupon_discount', $instanceid);
        $this->assertEquals(100.00, $payable->get_amount());
        $this->assertEquals('USD', $payable->get_currency());
    }
}
