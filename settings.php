<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $url  = new moodle_url('/enrol/coupon_discount/manage_coupons.php');
    $link = html_writer::link($url, get_string('managecoupons', 'enrol_coupon_discount'));
    $settings->add(new admin_setting_description(
        'enrol_coupon_discount_managecoupons',
        $link,
        ''
    ));
}
