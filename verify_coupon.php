<?php
// This file is part of Moodle - http://moodle.org/

require_once '../../config.php';

global $DB, $SESSION, $USER;

define('COUPON_PLUGIN', 'enrol_coupon_discount');
define('COUPON_USAGE_TABLE', 'enrol_coupon_discount_usage');

$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$couponcode = optional_param('coupon', '', PARAM_TEXT);

$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'coupon_discount'], '*', MUST_EXIST);

require_login();
require_sesskey();

$returnurl = new moodle_url('/enrol/index.php', ['id' => $courseid]);

if (empty($couponcode)) {
    unset($SESSION->coupon_discount[$instanceid]);
    $DB->delete_records(COUPON_USAGE_TABLE, [
        'instanceid' => $instanceid,
        'userid'     => $USER->id,
    ]);
    redirect($returnurl);
}

$coupon = $DB->get_record('enrol_coupon_discount_codes', ['code' => strtoupper(trim($couponcode))]);

if (!$coupon) {
    unset($SESSION->coupon_discount[$instanceid]);
    redirect($returnurl, get_string('invalidcoupon', COUPON_PLUGIN), null, \core\output\notification::NOTIFY_ERROR);
}

if (!empty($coupon->expirydate) && $coupon->expirydate > 0 && time() > $coupon->expirydate) {
    unset($SESSION->coupon_discount[$instanceid]);
    redirect($returnurl, get_string('expiredcoupon', COUPON_PLUGIN), null, \core\output\notification::NOTIFY_ERROR);
}

if (!empty($coupon->allowed_emails)) {
    $allowed = explode(',', $coupon->allowed_emails);
    $useremail = strtolower(trim($USER->email));
    if (!in_array($useremail, $allowed, true)) {
        unset($SESSION->coupon_discount[$instanceid]);
        redirect($returnurl, get_string('notallowedcoupon', COUPON_PLUGIN), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($coupon->max_uses > 0) {
    $totaluses = $DB->count_records(COUPON_USAGE_TABLE, ['couponid' => $coupon->id]);
    if ($totaluses >= $coupon->max_uses) {
        unset($SESSION->coupon_discount[$instanceid]);
        redirect($returnurl, get_string('couponmaxusesreached', COUPON_PLUGIN), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$alreadyused = $DB->record_exists(COUPON_USAGE_TABLE, [
    'instanceid' => $instanceid,
    'userid'     => $USER->id,
    'couponid'   => $coupon->id,
]);
if ($alreadyused) {
    unset($SESSION->coupon_discount[$instanceid]);
    redirect($returnurl, get_string('couponalreadyused', COUPON_PLUGIN), null, \core\output\notification::NOTIFY_ERROR);
}

$transaction = $DB->start_delegated_transaction();

try {
    if (!isset($SESSION->coupon_discount)) {
        $SESSION->coupon_discount = [];
    }
    $SESSION->coupon_discount[$instanceid] = [
        'code'             => $coupon->code,
        'discount_percent' => (float) $coupon->discount_percent,
    ];

    $DB->delete_records(COUPON_USAGE_TABLE, [
        'instanceid' => $instanceid,
        'userid'     => $USER->id,
    ]);

    $usage                   = new stdClass();
    $usage->instanceid       = $instanceid;
    $usage->userid           = $USER->id;
    $usage->couponid         = $coupon->id;
    $usage->discount_percent = (float) $coupon->discount_percent;
    $usage->timecreated      = time();
    $DB->insert_record(COUPON_USAGE_TABLE, $usage);

    $transaction->allow_commit();
} catch (Exception $e) {
    $transaction->rollback($e);
}

redirect($returnurl);
