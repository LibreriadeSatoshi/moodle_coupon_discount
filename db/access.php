<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    // Capability required to configure the enrolment method when adding it to a course.
    'enrol/coupon_discount:config' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Capability to manage user enrolments for this plugin.
    'enrol/coupon_discount:manage' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        )
    ),

    // Capability to unenrol other users from the course.
    'enrol/coupon_discount:unenrol' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Capability for users to unenrol themselves (empty by default for payment methods).
    'enrol/coupon_discount:unenrolself' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
        )
    ),

);
