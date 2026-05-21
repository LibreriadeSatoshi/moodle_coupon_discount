<?php
// This file is part of Moodle - http://moodle.org/

function xmldb_enrol_coupon_discount_upgrade($oldversion) { // NOSONAR Moodle upgrade hook naming convention.
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026051800) {
        $table = new xmldb_table('enrol_coupon_discount_codes');
        $field = new xmldb_field('allowed_emails', XMLDB_TYPE_TEXT, null, null, null, null, null, 'discount_percent');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026051800, 'enrol', 'coupon_discount');
    }

    if ($oldversion < 2026052001) {
        $table = new xmldb_table('enrol_coupon_discount_codes');
        $field = new xmldb_field('expirydate', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'allowed_emails');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052001, 'enrol', 'coupon_discount');
    }

    if ($oldversion < 2026052002) {
        $table = new xmldb_table('enrol_coupon_discount_codes');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'expirydate');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052002, 'enrol', 'coupon_discount');
    }

    if ($oldversion < 2026052100) {
        $table = new xmldb_table('enrol_coupon_discount_codes');
        $field = new xmldb_field('max_uses', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'description');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026052100, 'enrol', 'coupon_discount');
    }

    return true;
}
