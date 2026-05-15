<?php
// This file is part of Moodle - http://moodle.org/

require_once('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/enrol/coupon_discount/manage_coupons.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('managecoupons', 'enrol_coupon_discount'));
$PAGE->set_heading(get_string('managecoupons', 'enrol_coupon_discount'));
$PAGE->set_pagelayout('admin');

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

if ($action === 'delete' && $id) {
    require_sesskey();
    $DB->delete_records('enrol_coupon_discount_codes', ['id' => $id]);
    redirect($PAGE->url, get_string('coupondeleted', 'enrol_coupon_discount'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle add form submission.
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $code     = required_param('code', PARAM_ALPHANUMEXT);
    $discount = required_param('discount_percent', PARAM_FLOAT);

    if ($discount < 1 || $discount > 100) {
        $error = get_string('invaliddiscount', 'enrol_coupon_discount');
    } else if ($DB->record_exists('enrol_coupon_discount_codes', ['code' => $code])) {
        $error = get_string('duplicatecoupon', 'enrol_coupon_discount');
    } else {
        $record                   = new stdClass();
        $record->code             = strtoupper(trim($code));
        $record->discount_percent = $discount;
        $record->timecreated      = time();
        $DB->insert_record('enrol_coupon_discount_codes', $record);
        redirect($PAGE->url, get_string('couponadded', 'enrol_coupon_discount'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecoupons', 'enrol_coupon_discount'));

// --- Add coupon form ---
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url . '?action=add']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('card mb-4 p-3');
echo html_writer::tag('h5', get_string('addcoupon', 'enrol_coupon_discount'));

if (!empty($error)) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

echo html_writer::start_div('form-group mb-2');
echo html_writer::tag('label', get_string('couponcode', 'enrol_coupon_discount'), ['for' => 'code']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'code',
    'id'          => 'code',
    'class'       => 'form-control',
    'placeholder' => 'e.g. BITCOIN20',
    'required'    => 'required',
    'style'       => 'text-transform:uppercase',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mb-3');
echo html_writer::tag('label', get_string('discount_percent', 'enrol_coupon_discount'), ['for' => 'discount_percent']);
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'name'        => 'discount_percent',
    'id'          => 'discount_percent',
    'class'       => 'form-control',
    'min'         => '1',
    'max'         => '100',
    'step'        => '0.01',
    'placeholder' => '10',
    'required'    => 'required',
    'style'       => 'width:120px',
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('addcoupon', 'enrol_coupon_discount'), 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// --- Coupon list ---
$coupons = $DB->get_records('enrol_coupon_discount_codes', null, 'timecreated DESC');

if ($coupons) {
    $table            = new html_table();
    $table->head      = [
        get_string('couponcode', 'enrol_coupon_discount'),
        get_string('discount_percent', 'enrol_coupon_discount'),
        get_string('timecreated', 'enrol_coupon_discount'),
        get_string('actions', 'enrol_coupon_discount'),
    ];
    $table->attributes = ['class' => 'table table-striped generaltable'];

    foreach ($coupons as $coupon) {
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $coupon->id, 'sesskey' => sesskey()]);
        $deletelink = html_writer::link(
            $deleteurl,
            get_string('delete'),
            ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . get_string('confirmdeletecoupon', 'enrol_coupon_discount') . '")']
        );
        $table->data[] = [
            html_writer::tag('strong', $coupon->code),
            $coupon->discount_percent . '%',
            userdate($coupon->timecreated),
            $deletelink,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocoupons', 'enrol_coupon_discount'), \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
