<?php
// This file is part of Moodle - http://moodle.org/

require_once '../../config.php';

define('PLUGIN_NAME', 'enrol_coupon_discount');
define('COUPON_TABLE', 'enrol_coupon_discount_codes');
define('CSS_FORM_GROUP', 'form-group mb-3');
define('CSS_FORM_CONTROL', 'form-control');
define('CSS_HINT', 'text-muted small mb-1');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/enrol/coupon_discount/manage_coupons.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('managecoupons', PLUGIN_NAME));
$PAGE->set_heading(get_string('managecoupons', PLUGIN_NAME));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

if ($action === 'delete' && $id) {
    require_sesskey();
    $DB->delete_records(COUPON_TABLE, ['id' => $id]);
    redirect($PAGE->url, get_string('coupondeleted', PLUGIN_NAME), null, \core\output\notification::NOTIFY_SUCCESS);
}

$submit = optional_param('submit', '', PARAM_ALPHA);
if ($submit === 'add' && confirm_sesskey()) {
    $code        = required_param('code', PARAM_ALPHANUMEXT);
    $discount    = required_param('discount_percent', PARAM_FLOAT);
    $rawemails   = optional_param('allowed_emails', '', PARAM_TEXT);
    $rawexpiry   = optional_param('expirydate', '', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $maxuses     = optional_param('max_uses', 0, PARAM_INT);

    $emails_array = [];
    if (!empty($rawemails)) {
        $parts = explode(',', $rawemails);
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if (!empty($email) && validate_email($email)) {
                $emails_array[] = $email;
            }
        }
    }
    $clean_emails = implode(',', $emails_array);

    $expiryts = 0;
    if (!empty($rawexpiry)) {
        $parsed = strtotime($rawexpiry);
        if ($parsed !== false && $parsed > 0) {
            $expiryts = $parsed;
        }
    }

    if ($discount < 1 || $discount > 100) {
        $error = get_string('invaliddiscount', PLUGIN_NAME);
    } elseif ($maxuses < 0) {
        $error = get_string('invalidmaxuses', PLUGIN_NAME);
    } elseif ($DB->record_exists(COUPON_TABLE, ['code' => strtoupper(trim($code))])) {
        $error = get_string('duplicatecoupon', PLUGIN_NAME);
    } else {
        $record                   = new stdClass();
        $record->code             = strtoupper(trim($code));
        $record->discount_percent = $discount;
        $record->allowed_emails   = $clean_emails;
        $record->expirydate       = $expiryts;
        $record->description      = trim($description);
        $record->max_uses         = $maxuses;
        $record->timecreated      = time();
        $DB->insert_record(COUPON_TABLE, $record);
        redirect($PAGE->url, get_string('couponadded', PLUGIN_NAME), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

if ($submit === 'edit' && confirm_sesskey()) {
    $editid      = required_param('editid', PARAM_INT);
    $code        = required_param('code', PARAM_ALPHANUMEXT);
    $discount    = required_param('discount_percent', PARAM_FLOAT);
    $rawemails   = optional_param('allowed_emails', '', PARAM_TEXT);
    $rawexpiry   = optional_param('expirydate', '', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $maxuses     = optional_param('max_uses', 0, PARAM_INT);

    $emails_array = [];
    if (!empty($rawemails)) {
        $parts = explode(',', $rawemails);
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if (!empty($email) && validate_email($email)) {
                $emails_array[] = $email;
            }
        }
    }
    $clean_emails = implode(',', $emails_array);

    $expiryts = 0;
    if (!empty($rawexpiry)) {
        $parsed = strtotime($rawexpiry);
        if ($parsed !== false && $parsed > 0) {
            $expiryts = $parsed;
        }
    }

    if ($discount < 1 || $discount > 100) {
        $error = get_string('invaliddiscount', PLUGIN_NAME);
    } elseif ($maxuses < 0) {
        $error = get_string('invalidmaxuses', PLUGIN_NAME);
    } else {
        $existing = $DB->get_record(COUPON_TABLE, ['code' => strtoupper(trim($code))]);
        if ($existing && $existing->id != $editid) {
            $error = get_string('duplicatecoupon', PLUGIN_NAME);
        } else {
            $record                   = new stdClass();
            $record->id               = $editid;
            $record->code             = strtoupper(trim($code));
            $record->discount_percent = $discount;
            $record->allowed_emails   = $clean_emails;
            $record->expirydate       = $expiryts;
            $record->description      = trim($description);
            $record->max_uses         = $maxuses;
            $DB->update_record(COUPON_TABLE, $record);
            redirect($PAGE->url, get_string('couponupdated', PLUGIN_NAME), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

$editcoupon = null;
if ($action === 'edit' && $id) {
    $editcoupon = $DB->get_record(COUPON_TABLE, ['id' => $id]);
}

echo $OUTPUT->header();

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

if ($editcoupon) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submit', 'value' => 'edit']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'editid', 'value' => $editcoupon->id]);
} else {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submit', 'value' => 'add']);
}

echo html_writer::start_div('card mb-4 p-3');
echo html_writer::tag('h5', $editcoupon ? get_string('editcoupon', PLUGIN_NAME) : get_string('addcoupon', PLUGIN_NAME));

if (!empty($error)) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

$codevalue = $editcoupon ? $editcoupon->code : '';
echo html_writer::start_div('form-group mb-2');
echo html_writer::tag('label', get_string('couponcode', PLUGIN_NAME), ['for' => 'code']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'code',
    'id'          => 'code',
    'class'       => CSS_FORM_CONTROL,
    'placeholder' => get_string('couponcodeplaceholder', PLUGIN_NAME),
    'required'    => 'required',
    'style'       => 'text-transform:uppercase',
    'value'       => s($codevalue),
]);
echo html_writer::end_div();

$discountvalue = $editcoupon ? $editcoupon->discount_percent : '';
echo html_writer::start_div(CSS_FORM_GROUP);
echo html_writer::tag('label', get_string('discount_percent', PLUGIN_NAME), ['for' => 'discount_percent']);
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'name'        => 'discount_percent',
    'id'          => 'discount_percent',
    'class'       => CSS_FORM_CONTROL,
    'min'         => '1',
    'max'         => '100',
    'step'        => '0.01',
    'placeholder' => '10',
    'required'    => 'required',
    'style'       => 'width:120px',
    'value'       => $discountvalue,
]);
echo html_writer::end_div();

$maxusesvalue = $editcoupon ? $editcoupon->max_uses : 0;
echo html_writer::start_div(CSS_FORM_GROUP);
echo html_writer::tag('label', get_string('max_uses', PLUGIN_NAME), ['for' => 'max_uses']);
echo html_writer::tag('div', get_string('max_uses_desc', PLUGIN_NAME), ['class' => CSS_HINT]);
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'name'        => 'max_uses',
    'id'          => 'max_uses',
    'class'       => CSS_FORM_CONTROL,
    'min'         => '0',
    'step'        => '1',
    'placeholder' => '0',
    'style'       => 'width:120px',
    'value'       => $maxusesvalue,
]);
echo html_writer::end_div();

$emailsvalue = $editcoupon ? $editcoupon->allowed_emails : '';
echo html_writer::start_div(CSS_FORM_GROUP);
echo html_writer::tag('label', get_string('allowed_emails', PLUGIN_NAME), ['for' => 'allowed_emails']);
echo html_writer::tag('div', get_string('allowed_emails_desc', PLUGIN_NAME), ['class' => CSS_HINT]);
echo html_writer::tag('textarea', s($emailsvalue), [
    'name'        => 'allowed_emails',
    'id'          => 'allowed_emails',
    'class'       => CSS_FORM_CONTROL,
    'rows'        => '2',
    'placeholder' => get_string('allowedemailsplaceholder', PLUGIN_NAME),
]);
echo html_writer::end_div();

$expiryvalue = '';
if ($editcoupon && !empty($editcoupon->expirydate) && $editcoupon->expirydate > 0) {
    $expiryvalue = date('Y-m-d\TH:i', $editcoupon->expirydate);
}
echo html_writer::start_div(CSS_FORM_GROUP);
echo html_writer::tag('label', get_string('expirydate', PLUGIN_NAME), ['for' => 'expirydate']);
echo html_writer::tag('div', get_string('expirydate_desc', PLUGIN_NAME), ['class' => CSS_HINT]);
echo html_writer::empty_tag('input', [
    'type'  => 'datetime-local',
    'name'  => 'expirydate',
    'id'    => 'expirydate',
    'class' => CSS_FORM_CONTROL,
    'style' => 'width:220px',
    'value' => $expiryvalue,
]);
echo html_writer::end_div();

$descvalue = $editcoupon ? $editcoupon->description : '';
echo html_writer::start_div(CSS_FORM_GROUP);
echo html_writer::tag('label', get_string('coupondescription', PLUGIN_NAME), ['for' => 'description']);
echo html_writer::tag('div', get_string('coupondescription_desc', PLUGIN_NAME), ['class' => CSS_HINT]);
echo html_writer::tag('textarea', s($descvalue), [
    'name'        => 'description',
    'id'          => 'description',
    'class'       => CSS_FORM_CONTROL,
    'rows'        => '2',
    'placeholder' => get_string('coupondescriptionplaceholder', PLUGIN_NAME),
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => $editcoupon ? get_string('updatecoupon', PLUGIN_NAME) : get_string('addcoupon', PLUGIN_NAME),
    'class' => 'btn btn-primary',
]);

if ($editcoupon) {
    $cancelurl = new moodle_url($PAGE->url);
    echo html_writer::link($cancelurl, get_string('cancel'), ['class' => 'btn btn-secondary ml-2']);
}

echo html_writer::end_div();
echo html_writer::end_tag('form');

$coupons = $DB->get_records(COUPON_TABLE, null, 'timecreated DESC');

if ($coupons) {
    $table            = new html_table();
    $table->head      = [
        get_string('couponcode', PLUGIN_NAME),
        get_string('discount_percent', PLUGIN_NAME),
        get_string('max_uses', PLUGIN_NAME),
        get_string('coupondescription', PLUGIN_NAME),
        get_string('allowed_emails', PLUGIN_NAME),
        get_string('expirydate', PLUGIN_NAME),
        get_string('timecreated', PLUGIN_NAME),
        get_string('actions', PLUGIN_NAME),
    ];
    $table->attributes = ['class' => 'table table-striped generaltable'];

    foreach ($coupons as $coupon) {
        $currentuses = $DB->count_records('enrol_coupon_discount_usage', ['couponid' => $coupon->id]);
        $usesdisplay = $coupon->max_uses > 0
            ? $currentuses . ' / ' . $coupon->max_uses
            : $currentuses . ' / ' . html_writer::tag('span', get_string('unlimited', PLUGIN_NAME), ['class' => 'badge badge-info bg-info']);

        $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $coupon->id]);
        $editlink = html_writer::link(
            $editurl,
            get_string('edit'),
            ['class' => 'btn btn-sm btn-warning mr-1']
        );

        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $coupon->id, 'sesskey' => sesskey()]);
        $deletelink = html_writer::link(
            $deleteurl,
            get_string('delete'),
            ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . get_string('confirmdeletecoupon', PLUGIN_NAME) . '")']
        );

        $allowed_display = empty($coupon->allowed_emails)
            ? html_writer::tag('span', get_string('allusers', PLUGIN_NAME), ['class' => 'badge badge-success bg-success'])
            : s($coupon->allowed_emails);

        $expiry_display = (!empty($coupon->expirydate) && $coupon->expirydate > 0)
            ? userdate($coupon->expirydate, get_string('strftimedatetimeshort', 'langconfig'))
            : html_writer::tag('span', get_string('neverexpires', PLUGIN_NAME), ['class' => 'badge badge-secondary bg-secondary']);

        $desc_display = !empty($coupon->description)
            ? s($coupon->description)
            : html_writer::tag('span', '—', ['class' => 'text-muted']);

        $table->data[] = [
            html_writer::tag('strong', $coupon->code),
            $coupon->discount_percent . '%',
            $usesdisplay,
            $desc_display,
            $allowed_display,
            $expiry_display,
            userdate($coupon->timecreated),
            $editlink . $deletelink,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocoupons', PLUGIN_NAME), \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
