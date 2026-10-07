<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace enrol_coupon_discount\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests coupon privacy boundaries and retained payment history.
 *
 * @package enrol_coupon_discount
 * @copyright 2026 Bitcoin Diploma
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var array Test fixtures. */
    private array $fixture;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user(['email' => 'alice@example.com']);
        $bob = $generator->create_user(['email' => 'bob@example.com']);
        $unrelated = $generator->create_user(['email' => 'lice@example.com']);
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $context = \context_course::instance($course->id);
        $othercontext = \context_course::instance($othercourse->id);
        $instance = enrol_get_plugin('coupon_discount')->add_instance($course);
        $otherinstance = enrol_get_plugin('coupon_discount')->add_instance($othercourse);
        $coupon = $DB->insert_record('enrol_coupon_discount_codes', (object) [
            'code' => 'PRIVATE', 'discount_percent' => 25, 'allowed_emails' => 'alice@example.com, bob@example.com',
            'timecreated' => 1700000000,
        ]);
        $usages = [];
        foreach ([[$alice->id, $instance], [$bob->id, $instance], [$alice->id, $otherinstance],
                [$alice->id, 999999]] as [$userid, $instanceid]) {
            $usages[] = $DB->insert_record('enrol_coupon_discount_usage', (object) [
                'userid' => $userid, 'instanceid' => $instanceid, 'couponid' => $coupon,
                'discount_percent' => 25, 'payment_status' => 1, 'timecreated' => 1700000000,
            ]);
        }
        $paymentgenerator = $generator->get_plugin_generator('core_payment');
        $account = $paymentgenerator->create_payment_account(['name' => 'Coupons']);
        $payment = $paymentgenerator->create_payment([
            'accountid' => $account->get('id'), 'component' => 'enrol_coupon_discount',
            'paymentarea' => 'coupon_discount', 'itemid' => $instance, 'userid' => $alice->id,
            'amount' => 75, 'currency' => 'USD', 'gateway' => 'paypal',
            'timecreated' => 1700000000, 'timemodified' => 1700000000,
        ]);
        $DB->insert_record('paygw_paypal', (object) ['paymentid' => $payment, 'pp_orderid' => 'ORDER-123']);
        $this->fixture = compact('alice', 'bob', 'unrelated', 'context', 'othercontext', 'instance',
            'coupon', 'usages', 'payment');
    }

    /** Discovery includes usage, orphan records, and exact allowlist membership. */
    public function test_discovery(): void {
        $f = $this->fixture;
        $contexts = provider::get_contexts_for_userid($f['alice']->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$f['context']->id, $f['othercontext']->id, SYSCONTEXTID], $contexts);
        $this->assertEmpty(provider::get_contexts_for_userid($f['unrelated']->id)->get_contextids());
        $users = new userlist($f['context'], 'enrol_coupon_discount');
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$f['alice']->id, $f['bob']->id], $users->get_userids());
        $users = new userlist(\context_system::instance(), 'enrol_coupon_discount');
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$f['alice']->id, $f['bob']->id], $users->get_userids());
        $this->assertEquals($f['context']->id, provider::get_contextid_for_payment('coupon_discount', $f['instance']));
        $this->assertNull(provider::get_contextid_for_payment('coupon_discount', 999999));
    }

    /** Export only approved courses, the requesting user's usage, and their own email. */
    public function test_export(): void {
        $f = $this->fixture;
        provider::export_user_data(new approved_contextlist($f['alice'], 'enrol_coupon_discount',
            [$f['context']->id, SYSCONTEXTID]));
        $path = [get_string('pluginname', 'enrol_coupon_discount')];
        $data = writer::with_context($f['context'])->get_data($path);
        $this->assertCount(1, $data->usage);
        $this->assertEquals($f['usages'][0], $data->usage[0]->id);
        $this->assertEquals(25, $data->usage[0]->discount_percent);
        $this->assertEquals(1, $data->usage[0]->payment_status);
        $this->assertFalse(writer::with_context($f['othercontext'])->has_any_data());
        $data = writer::with_context(\context_system::instance())->get_data($path);
        $this->assertCount(1, $data->usage);
        $this->assertEquals($f['usages'][3], $data->usage[0]->id);
        $this->assertCount(1, $data->coupons);
        $this->assertSame('alice@example.com', $data->coupons[0]->allowed_email);
        $paymentpath = [get_string('payments', 'payment'), $path[0], 'payment-' . $f['payment']];
        $this->assertNotEmpty(writer::with_context($f['context'])->get_data($paymentpath));
    }

    /** Deleting one user in a course preserves all other users, courses, and global restrictions. */
    public function test_delete_user_in_approved_course(): void {
        global $DB;
        $f = $this->fixture;
        provider::delete_data_for_user(new approved_contextlist($f['alice'], 'enrol_coupon_discount', [$f['context']->id]));
        $this->assertEqualsCanonicalizing(array_slice($f['usages'], 1),
            array_keys($DB->get_records('enrol_coupon_discount_usage')));
        $this->assertFalse($DB->record_exists('payments', ['id' => $f['payment']]));
        $this->assertSame('alice@example.com, bob@example.com',
            $DB->get_field('enrol_coupon_discount_codes', 'allowed_emails', ['id' => $f['coupon']]));
    }

    /** Bulk deletion cannot erase users or courses outside the approved list. */
    public function test_delete_selected_users(): void {
        global $DB;
        $f = $this->fixture;
        provider::delete_data_for_users(new approved_userlist($f['context'], 'enrol_coupon_discount', [$f['bob']->id]));
        $this->assertEqualsCanonicalizing([$f['usages'][0], $f['usages'][2], $f['usages'][3]],
            array_keys($DB->get_records('enrol_coupon_discount_usage')));
        provider::delete_data_for_users(new approved_userlist($f['context'], 'enrol_coupon_discount', []));
        $this->assertEquals(3, $DB->count_records('enrol_coupon_discount_usage'));
        $this->assertTrue($DB->record_exists('payments', ['id' => $f['payment']]));
    }

    /** Full context deletion retains data in other contexts. */
    public function test_delete_course(): void {
        global $DB;
        $f = $this->fixture;
        provider::delete_data_for_all_users_in_context($f['context']);
        $this->assertEqualsCanonicalizing([$f['usages'][2], $f['usages'][3]],
            array_keys($DB->get_records('enrol_coupon_discount_usage')));
        $this->assertFalse($DB->record_exists('payments', ['id' => $f['payment']]));
    }

    /** Removing the final allowed email never makes a restricted coupon public. */
    public function test_delete_global_restrictions_and_orphans(): void {
        global $DB;
        $f = $this->fixture;
        provider::delete_data_for_user(new approved_contextlist($f['alice'], 'enrol_coupon_discount', [SYSCONTEXTID]));
        $this->assertFalse($DB->record_exists('enrol_coupon_discount_usage', ['id' => $f['usages'][3]]));
        $this->assertEquals(3, $DB->count_records('enrol_coupon_discount_usage'));
        $this->assertSame('bob@example.com',
            $DB->get_field('enrol_coupon_discount_codes', 'allowed_emails', ['id' => $f['coupon']]));
        provider::delete_data_for_users(new approved_userlist(\context_system::instance(),
            'enrol_coupon_discount', [$f['bob']->id]));
        $coupon = $DB->get_record('enrol_coupon_discount_codes', ['id' => $f['coupon']], '*', MUST_EXIST);
        $this->assertEmpty($coupon->allowed_emails);
        $this->assertGreaterThan(0, $coupon->expirydate);
        $this->assertLessThan(time(), $coupon->expirydate);
    }

    /** Email-only eligibility is discovered without usage, and does not match email substrings. */
    public function test_email_only_eligibility(): void {
        global $DB;
        $f = $this->fixture;
        $DB->delete_records('enrol_coupon_discount_usage', ['userid' => $f['bob']->id]);
        $DB->set_field('enrol_coupon_discount_codes', 'allowed_emails', 'ALICE@EXAMPLE.COM, BOB@EXAMPLE.COM',
            ['id' => $f['coupon']]);
        $this->assertEquals([SYSCONTEXTID], provider::get_contexts_for_userid($f['bob']->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($f['unrelated']->id)->get_contextids());
        $users = new userlist(\context_system::instance(), 'enrol_coupon_discount');
        provider::get_users_in_context($users);
        $this->assertEqualsCanonicalizing([$f['alice']->id, $f['bob']->id], $users->get_userids());
    }

    /** Payments remain discoverable and erasable after the enrolment has been deleted. */
    public function test_orphaned_payment_without_usage(): void {
        global $DB;
        $f = $this->fixture;
        $DB->delete_records('enrol_coupon_discount_usage');
        $DB->set_field('enrol_coupon_discount_codes', 'allowed_emails', 'bob@example.com', ['id' => $f['coupon']]);
        $this->assertEquals([$f['context']->id], provider::get_contexts_for_userid($f['alice']->id)->get_contextids());
        $DB->delete_records('enrol', ['id' => $f['instance']]);
        $this->assertEquals([SYSCONTEXTID], provider::get_contexts_for_userid($f['alice']->id)->get_contextids());
        $users = new userlist(\context_system::instance(), 'enrol_coupon_discount');
        provider::get_users_in_context($users);
        $this->assertContains((int) $f['alice']->id, $users->get_userids());
        provider::export_user_data(new approved_contextlist($f['alice'], 'enrol_coupon_discount', [SYSCONTEXTID]));
        $path = [get_string('payments', 'payment'), get_string('pluginname', 'enrol_coupon_discount'),
            'payment-' . $f['payment']];
        $this->assertNotEmpty(writer::with_context(\context_system::instance())->get_data($path));
        provider::delete_data_for_user(new approved_contextlist($f['alice'], 'enrol_coupon_discount', [SYSCONTEXTID]));
        $this->assertFalse($DB->record_exists('payments', ['id' => $f['payment']]));
        $this->assertFalse($DB->record_exists('paygw_paypal', ['paymentid' => $f['payment']]));
    }

    /** Unsupported and empty approved contexts cannot export or delete data. */
    public function test_unsupported_and_empty_contexts(): void {
        global $DB;
        $f = $this->fixture;
        $context = \context_user::instance($f['alice']->id);
        $contexts = new approved_contextlist($f['alice'], 'enrol_coupon_discount', [$context->id]);
        provider::export_user_data($contexts);
        $this->assertFalse(writer::with_context($context)->has_any_data());
        provider::delete_data_for_user($contexts);
        provider::delete_data_for_all_users_in_context($context);
        provider::delete_data_for_users(new approved_userlist($context, 'enrol_coupon_discount', [$f['alice']->id]));
        provider::delete_data_for_user(new approved_contextlist($f['alice'], 'enrol_coupon_discount', []));
        $users = new userlist($context, 'enrol_coupon_discount');
        provider::get_users_in_context($users);
        $this->assertEmpty($users->get_userids());
        $this->assertEquals(4, $DB->count_records('enrol_coupon_discount_usage'));
        $this->assertTrue($DB->record_exists('payments', ['id' => $f['payment']]));
    }

    /** System deletion scrubs restrictions and orphans while keeping course records and public coupons. */
    public function test_delete_system_context(): void {
        global $DB;
        $f = $this->fixture;
        $publiccoupon = $DB->insert_record('enrol_coupon_discount_codes', (object) [
            'code' => 'PUBLIC', 'discount_percent' => 10, 'allowed_emails' => '', 'timecreated' => 1700000000,
        ]);
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEqualsCanonicalizing(array_slice($f['usages'], 0, 3),
            array_keys($DB->get_records('enrol_coupon_discount_usage')));
        $this->assertTrue($DB->record_exists('payments', ['id' => $f['payment']]));
        $coupon = $DB->get_record('enrol_coupon_discount_codes', ['id' => $f['coupon']], '*', MUST_EXIST);
        $this->assertEmpty($coupon->allowed_emails);
        $this->assertEquals(1, $coupon->expirydate);
        $this->assertEquals(0, $DB->get_field('enrol_coupon_discount_codes', 'expirydate', ['id' => $publiccoupon]));
    }

}
