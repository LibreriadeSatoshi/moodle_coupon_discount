<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

define('PLUGIN_COMPONENT', 'enrol_coupon_discount');

class enrol_coupon_discount_plugin extends enrol_plugin { // NOSONAR Moodle enrol plugin naming convention.

    /**
     * Returns the localised name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return 'coupon_discount';
    }

    /**
     * Returns the list of possible currencies for payment.
     *
     * @return array
     */
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

    /**
     * Returns whether the role is protected from changes.
     *
     * @return bool
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Returns whether unenrolment is allowed for this instance.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Returns whether managing this instance is allowed.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Returns whether the enrolment link should be shown.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance) {
        return $instance->status == ENROL_INSTANCE_ENABLED;
    }

    /**
     * Returns whether the instance can be deleted.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/coupon_discount:config', $context);
    }

    /**
     * Returns the action icons for this instance.
     *
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        $icons = parent::get_action_icons($instance);
        $context = context_course::instance($instance->courseid);

        if (has_capability('enrol/coupon_discount:manage', $context)) {
            $enrolusersurl = new moodle_url('/enrol/coupon_discount/manage.php', ['enrolid' => $instance->id, 'id' => $instance->courseid]);
            $icons[] = $OUTPUT->action_icon($enrolusersurl, new pix_icon('t/enrolusers', get_string('addusers', PLUGIN_COMPONENT)));
        }

        return $icons;
    }

    /**
     * Returns whether adding an instance is allowed.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context)) {
            return false;
        }
        return true;
    }

    /**
     * Returns whether the standard editing UI should be used.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Adds a new instance of the enrolment plugin.
     *
     * @param stdClass $course
     * @param array|null $fields
     * @return int
     */
    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    /**
     * Updates an existing instance of the enrolment plugin.
     *
     * @param stdClass $instance
     * @param stdClass|null $data
     * @return bool
     */
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Renders the enrolment page hook with coupon input and payment button.
     *
     * @param stdClass $instance
     * @return string
     */
    public function enrol_page_hook(stdClass $instance) {
        global $USER, $OUTPUT, $DB, $CFG, $SESSION;

        $alreadyenrolled = $DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id));
        $notstarted = $instance->enrolstartdate != 0 && $instance->enrolstartdate > time();
        $ended = $instance->enrolenddate != 0 && $instance->enrolenddate < time();
        if ($alreadyenrolled || $notstarted || $ended) {
            return '';
        }

        ob_start();

        $course = $DB->get_record('course', array('id' => $instance->courseid));
        $context = context_course::instance($course->id);

        $cost = (float) $instance->cost;
        if (abs($cost) < 0.01) {
            echo '<p>'.get_string('nocost', PLUGIN_COMPONENT).'</p>';
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
        echo '<h3>' . get_string('coursepricing', PLUGIN_COMPONENT) . ': ' . format_string($course->fullname, true, ['context' => $context]) . '</h3>';

        echo '<div class="enrol_coupon_discount_price_box">';
        if ($discount_percent > 0) {
            echo '<span class="original_price">' . $cost_str . '</span>';
            echo '<span class="discounted_price animate_price">' . $discounted_cost_str . '</span>';
            echo '<div class="enrol_coupon_discount_success_msg"><i class="fa fa-check-circle"></i> ' . get_string('couponapplied', PLUGIN_COMPONENT, $discount_percent.'%') . '</div>';
        } else {
            echo '<span class="discounted_price">' . $cost_str . '</span>';
        }
        echo '</div>';

        $applyurl = new moodle_url('/enrol/coupon_discount/verify_coupon.php');
        echo '<form action="'.$applyurl.'" method="post" class="enrol_coupon_discount_form">';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';
        echo '<input type="hidden" name="id" value="'.$instance->courseid.'">';
        echo '<input type="hidden" name="instanceid" value="'.$instance->id.'">';

        echo '<div class="enrol_coupon_discount_input_group">';
        echo '<input type="text" name="coupon" placeholder="'.get_string('couponcode', PLUGIN_COMPONENT).'" value="'.s($couponcode).'" autocomplete="off">';
        echo '</div>';

        echo '<button type="submit" class="enrol_coupon_discount_btn_apply">'.get_string('applycoupon', PLUGIN_COMPONENT).'</button>';
        echo '</form>';

        // Payment button
        $successurl = \enrol_coupon_discount\payment\service_provider::get_success_url('coupon_discount', $instance->id)->out(false);
        $description = get_string('pluginname', PLUGIN_COMPONENT) . ' - ' . format_string($course->fullname, true, ['context' => $context]);

        echo '<div class="enrol_coupon_discount_payment_region">';
        if (isguestuser() || !isloggedin()) {
            echo '<div class="mdl-align"><p>' . get_string('mustloginpay', PLUGIN_COMPONENT) . '</p></div>';
        } else {
            echo '<button class="enrol_coupon_discount_btn_pay" type="button" id="gateways-modal-trigger-btc" ' .
                 'data-action="core_payment/triggerPayment" ' .
                 'data-component="enrol_coupon_discount" ' .
                 'data-paymentarea="coupon_discount" ' .
                 'data-itemid="'.$instance->id.'" ' .
                 'data-cost="'.$discounted_cost_str.'" ' .
                 'data-successurl="'.$successurl.'" ' .
                 'data-description="'.$description.'">' .
                 '<i class="fa fa-credit-card"></i> ' . get_string('sendpaymentbutton', PLUGIN_COMPONENT) . '</button>';
        }
        echo '</div>';

        echo '</div>'; // End container

        global $PAGE;
        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');

        return $OUTPUT->box(ob_get_clean());
    }

    /**
     * Adds elements to the instance edit form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', PLUGIN_COMPONENT), $options);
        $mform->setDefault('status', ENROL_INSTANCE_ENABLED);

        $accounts = \core_payment\helper::get_payment_accounts_menu($context);
        if ($accounts) {
            $mform->addElement('select', 'customint1', get_string('paymentaccount', 'payment'), $accounts);
        } else {
            $mform->addElement('hidden', 'customint1', 0);
            $mform->setType('customint1', PARAM_INT);
        }

        $mform->addElement('text', 'cost', get_string('cost', PLUGIN_COMPONENT), array('size' => 4));
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', 0);

        $supportedcurrencies = $this->get_possible_currencies();
        $mform->addElement('select', 'currency', get_string('currency', PLUGIN_COMPONENT), $supportedcurrencies);

        $roles = get_default_enrol_roles($context);
        $mform->addElement('select', 'roleid', get_string('assignrole', PLUGIN_COMPONENT), $roles);

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', PLUGIN_COMPONENT), $options);

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', PLUGIN_COMPONENT), $options);

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', PLUGIN_COMPONENT), $options);
    }

    /**
     * Validates the instance edit form data.
     *
     * @param array $data
     * @param array $files
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();
        $cost = clean_param($data['cost'], PARAM_RAW);
        $numericcost = unformat_float($cost);
        if ($numericcost === null || $numericcost < 0) {
            $errors['cost'] = get_string('invalidcost', PLUGIN_COMPONENT);
        }
        return $errors;
    }
}
