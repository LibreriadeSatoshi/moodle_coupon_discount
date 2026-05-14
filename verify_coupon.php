<?php
// This file is part of Moodle - http://moodle.org/

require_once('../../config.php');

global $DB, $SESSION;

$courseid = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$couponcode = optional_param('coupon', '', PARAM_TEXT);

error_log("DEBUG: verify_coupon.php START - course: $courseid, instance: $instanceid, coupon: '$couponcode'");

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$instance = $DB->get_record('enrol', array('id' => $instanceid, 'enrol' => 'coupon_discount'), '*', MUST_EXIST);

// require_login($course);

error_log("DEBUG: Validating coupon '$couponcode' for instance $instanceid");

$returnurl = new moodle_url('/enrol/index.php', array('id' => $courseid));

if (empty($couponcode)) {
    // Clear coupon
    unset($SESSION->coupon_discount[$instanceid]);
    redirect($returnurl);
}

// Check coupon in DB
try {
    $coupon = $DB->get_record('enrol_coupon_discount_codes', array('code' => $couponcode));
    if ($coupon) {
        error_log("DEBUG: Coupon found in DB! ID: " . $coupon->id);
        // Valid coupon, store in session
        if (!isset($SESSION->coupon_discount)) {
            $SESSION->coupon_discount = array();
        }
        $SESSION->coupon_discount[$instanceid] = array(
            'code' => $coupon->code,
            'discount_percent' => (float)$coupon->discount_percent
        );
        error_log("DEBUG: Session variable set for instance $instanceid");
        redirect($returnurl);
    } else {
        error_log("DEBUG: Coupon NOT found in DB for code: '$couponcode'");
        // Invalid coupon
        unset($SESSION->coupon_discount[$instanceid]);
        redirect($returnurl, get_string('invalidcoupon', 'enrol_coupon_discount'), null, \core\output\notification::NOTIFY_ERROR);
    }
} catch (\Exception $e) {
    error_log("DEBUG: FATAL ERROR in verify_coupon.php: " . $e->getMessage());
    redirect($returnurl, "Internal error: " . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
