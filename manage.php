<?php
// This file is part of Moodle - http://moodle.org/

require_once('../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'coupon_discount'], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('enrol/coupon_discount:manage', $context);

$PAGE->set_url(new moodle_url('/enrol/coupon_discount/manage.php', ['enrolid' => $enrolid, 'id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('manualenrolment', 'enrol_coupon_discount'));
$PAGE->set_heading(get_string('manualenrolment', 'enrol_coupon_discount'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$emails = optional_param('emails', '', PARAM_TEXT);

$plugin = enrol_get_plugin('coupon_discount');

if ($action === 'enrol' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    
    $parts = explode(',', $emails);
    $enrolled = 0;
    $notfound = [];

    foreach ($parts as $part) {
        $email = strtolower(trim($part));
        if (empty($email) || !validate_email($email)) {
            continue;
        }
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if ($user) {
            $plugin->enrol_user($instance, $user->id, $instance->roleid);
            $enrolled++;
        } else {
            $notfound[] = $email;
        }
    }

    if ($enrolled > 0) {
        \core\output\notification::add(get_string('enrolledusers', 'enrol', $enrolled), \core\output\notification::NOTIFY_SUCCESS);
    }
    if (!empty($notfound)) {
        \core\output\notification::add('No se encontraron usuarios con estos correos: ' . implode(', ', $notfound), \core\output\notification::NOTIFY_ERROR);
    }

    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualenrolment', 'enrol_coupon_discount'));

echo html_writer::start_div('card p-4 mb-4');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url . '&action=enrol']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group mb-3');
echo html_writer::tag('label', 'Correos electrónicos de los usuarios a matricular (separados por comas)', ['for' => 'emails']);
echo html_writer::tag('textarea', '', [
    'name' => 'emails',
    'id' => 'emails',
    'class' => 'form-control',
    'rows' => '3',
    'placeholder' => 'usuario1@correo.com, usuario2@correo.com'
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('enrolusers', 'enrol_coupon_discount'), 'class' => 'btn btn-primary']);

echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::start_div('mt-3');
$backurl = new moodle_url('/enrol/instances.php', ['id' => $course->id]);
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
