<?php
// This file is part of Moodle - http://moodle.org/

require_once('../../config.php');

global $DB, $SESSION, $USER;

$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$couponcode = optional_param('coupon', '', PARAM_TEXT);

$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'coupon_discount'], '*', MUST_EXIST);

require_login($course);

$returnurl = new moodle_url('/enrol/index.php', ['id' => $courseid]);

// If coupon field is empty, clear any applied coupon and redirect.
if (empty($couponcode)) {
    unset($SESSION->coupon_discount[$instanceid]);
    // Also remove any pending DB usage record for this user/instance.
    $DB->delete_records('enrol_coupon_discount_usage', [
        'instanceid' => $instanceid,
        'userid'     => $USER->id,
    ]);
    redirect($returnurl);
}

// Look up coupon in the database.
$coupon = $DB->get_record('enrol_coupon_discount_codes', ['code' => $couponcode]);

if (!$coupon) {
    unset($SESSION->coupon_discount[$instanceid]);
    redirect($returnurl, get_string('invalidcoupon', 'enrol_coupon_discount'), null, \core\output\notification::NOTIFY_ERROR);
}

// Valid coupon — store in session.
if (!isset($SESSION->coupon_discount)) {
    $SESSION->coupon_discount = [];
}
$SESSION->coupon_discount[$instanceid] = [
    'code'             => $coupon->code,
    'discount_percent' => (float) $coupon->discount_percent,
];

// Persist the applied coupon to DB so it survives gateway redirects.
// Delete any previous record for this user/instance first (idempotent).
$DB->delete_records('enrol_coupon_discount_usage', [
    'instanceid' => $instanceid,
    'userid'     => $USER->id,
]);

$usage                   = new stdClass();
$usage->instanceid       = $instanceid;
$usage->userid           = $USER->id;
$usage->couponid         = $coupon->id;
$usage->discount_percent = (float) $coupon->discount_percent;
$usage->timecreated      = time();
$DB->insert_record('enrol_coupon_discount_usage', $usage);

redirect($returnurl);
