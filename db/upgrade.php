<?php
// This file is part of Moodle - http://moodle.org/

function xmldb_enrol_coupon_discount_upgrade($oldversion) {
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

    return true;
}
