<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

class enrol_coupon_discount_plugin extends enrol_plugin {

    public function get_possible_currencies(): array {
        $codes = \core_payment\helper::get_supported_currencies();
        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }
        uasort($currencies, function($a, $b) {
            return strcmp($a, $b);
        });
        return $currencies;
    }

    public function roles_protected() {
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context)) {
            return false;
        }
        return true;
    }

    public function use_standard_editing_ui() {
        return true;
    }

    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    public function enrol_page_hook(stdClass $instance) {
        global $USER, $OUTPUT, $DB, $CFG, $SESSION;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id' => $instance->courseid));
        $context = context_course::instance($course->id);

        $cost = (float) $instance->cost;
        if (abs($cost) < 0.01) {
            echo '<p>'.get_string('nocost', 'enrol_coupon_discount').'</p>';
            return $OUTPUT->box(ob_get_clean());
        }

        // Check if there is an applied coupon in the session
        $discount_percent = 0;
        $couponcode = '';
        $original_cost = $cost;
        if (isset($SESSION->coupon_discount[$instance->id])) {
            $couponcode = $SESSION->coupon_discount[$instance->id]['code'];
            $discount_percent = $SESSION->coupon_discount[$instance->id]['discount_percent'];
            $cost = $cost - ($cost * ($discount_percent / 100));
        }

        $cost_str = \core_payment\helper::get_cost_as_string($original_cost, $instance->currency);
        $discounted_cost_str = \core_payment\helper::get_cost_as_string($cost, $instance->currency);

        echo '<div class="enrol_coupon_discount_container">';
        echo '<h3><i class="fa fa-btc"></i> ' . get_string('pluginname', 'enrol_coupon_discount') . '</h3>';

        echo '<div class="enrol_coupon_discount_price_box">';
        if ($discount_percent > 0) {
            echo '<span class="original_price">' . $cost_str . '</span>';
            echo '<span class="discounted_price animate_price">' . $discounted_cost_str . '</span>';
            echo '<div class="enrol_coupon_discount_success_msg"><i class="fa fa-check-circle"></i> ' . get_string('couponapplied', 'enrol_coupon_discount', $discount_percent.'%') . '</div>';
        } else {
            echo '<span class="discounted_price">' . $cost_str . '</span>';
        }
        echo '</div>';

        $applyurl = new moodle_url('/enrol/coupon_discount/verify_coupon.php');
        echo '<form action="'.$applyurl.'" method="post" class="enrol_coupon_discount_form">';
        echo '<input type="hidden" name="id" value="'.$instance->courseid.'">';
        echo '<input type="hidden" name="instanceid" value="'.$instance->id.'">';
        
        echo '<div class="enrol_coupon_discount_input_group">';
        echo '<input type="text" name="coupon" placeholder="'.get_string('couponcode', 'enrol_coupon_discount').'" value="'.s($couponcode).'" autocomplete="off">';
        echo '</div>';
        
        echo '<button type="submit" class="enrol_coupon_discount_btn_apply">'.get_string('applycoupon', 'enrol_coupon_discount').'</button>';
        echo '</form>';

        // Payment button
        $successurl = \enrol_coupon_discount\payment\service_provider::get_success_url('coupon_discount', $instance->id)->out(false);
        $description = get_string('pluginname', 'enrol_coupon_discount') . ' - ' . format_string($course->fullname, true, ['context' => $context]);
        
        echo '<div class="enrol_coupon_discount_payment_region">';
        if (isguestuser() || !isloggedin()) {
            echo '<div class="mdl-align"><p>You must log in to pay</p></div>';
        } else {
            echo '<button class="enrol_coupon_discount_btn_pay" type="button" id="gateways-modal-trigger-btc" ' .
                 'data-action="core_payment/triggerPayment" ' .
                 'data-component="enrol_coupon_discount" ' .
                 'data-paymentarea="coupon_discount" ' .
                 'data-itemid="'.$instance->id.'" ' .
                 'data-cost="'.$discounted_cost_str.'" ' .
                 'data-successurl="'.$successurl.'" ' .
                 'data-description="'.$description.'">' . 
                 '<i class="fa fa-credit-card"></i> ' . get_string('sendpaymentbutton', 'enrol_coupon_discount') . '</button>';
        }
        echo '</div>';
        
        echo '</div>'; // End container
        
        global $PAGE;
        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');

        return $OUTPUT->box(ob_get_clean());
    }

    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_coupon_discount'), $options);
        $mform->setDefault('status', ENROL_INSTANCE_ENABLED);

        $accounts = \core_payment\helper::get_payment_accounts_menu($context);
        if ($accounts) {
            $mform->addElement('select', 'customint1', get_string('paymentaccount', 'payment'), $accounts);
        } else {
            $mform->addElement('hidden', 'customint1', 0);
            $mform->setType('customint1', PARAM_INT);
        }

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_coupon_discount'), array('size' => 4));
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', 0);

        $supportedcurrencies = $this->get_possible_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_coupon_discount'), $supportedcurrencies);

        $roles = get_default_enrol_roles($context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_coupon_discount'), $roles);

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_coupon_discount'), $options);

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_coupon_discount'), $options);

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_coupon_discount'), $options);
    }

    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();
        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = 'Invalid cost';
        }
        return $errors;
    }
}
