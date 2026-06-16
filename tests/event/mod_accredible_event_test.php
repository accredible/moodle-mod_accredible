<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_accredible\event;

/**
 * Unit tests for the credential-issuance event classes.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_event_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * credential_issued: modern create path, crud=c, participating, no objecttable.
     * @covers \mod_accredible\event\credential_issued
     */
    public function test_credential_issued(): void {
        $sink = $this->redirectEvents();
        $event = credential_issued::create([
            'context' => \context_system::instance(),
            'relateduserid' => 42,
            'other' => ['credentialid' => 9988, 'groupid' => 55],
        ]);
        $event->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(credential_issued::class, $events[0]);
        $data = $events[0]->get_data();
        $this->assertEquals('c', $data['crud']);
        $this->assertEquals(\core\event\base::LEVEL_PARTICIPATING, $data['edulevel']);
        $this->assertNull($data['objecttable']);
        $this->assertEquals(get_string('eventcredentialissued', 'mod_accredible'), credential_issued::get_name());
        $this->assertStringContainsString('9988', $event->get_description());
        $this->assertStringContainsString('42', $event->get_description());
    }

    /**
     * credential_issue_skipped: crud=r, other (LEVEL_OTHER), reason in description.
     * @covers \mod_accredible\event\credential_issue_skipped
     */
    public function test_credential_issue_skipped(): void {
        $sink = $this->redirectEvents();
        $event = credential_issue_skipped::create([
            'context' => \context_system::instance(),
            'relateduserid' => 42,
            'other' => ['reason' => 'completion_not_met', 'groupid' => 55],
        ]);
        $event->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(credential_issue_skipped::class, $events[0]);
        $data = $events[0]->get_data();
        $this->assertEquals('r', $data['crud']);
        $this->assertEquals(\core\event\base::LEVEL_OTHER, $data['edulevel']);
        $this->assertNull($data['objecttable']);
        $this->assertEquals(get_string('eventcredentialissueskipped', 'mod_accredible'), credential_issue_skipped::get_name());
        $this->assertStringContainsString(
            get_string('reason_completion_not_met', 'mod_accredible'),
            $event->get_description()
        );
    }

    /**
     * credential_issue_failed: crud=c, other (LEVEL_OTHER), reason in description.
     * @covers \mod_accredible\event\credential_issue_failed
     */
    public function test_credential_issue_failed(): void {
        $sink = $this->redirectEvents();
        $event = credential_issue_failed::create([
            'context' => \context_system::instance(),
            'relateduserid' => 42,
            'other' => ['reason' => 'exception', 'message' => 'boom', 'class' => 'Exception'],
        ]);
        $event->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(credential_issue_failed::class, $events[0]);
        $data = $events[0]->get_data();
        $this->assertEquals('c', $data['crud']);
        $this->assertEquals(\core\event\base::LEVEL_OTHER, $data['edulevel']);
        $this->assertNull($data['objecttable']);
        $this->assertEquals(get_string('eventcredentialissuefailed', 'mod_accredible'), credential_issue_failed::get_name());
        $this->assertStringContainsString(
            get_string('reason_exception', 'mod_accredible'),
            $event->get_description()
        );
        // The raw exception message is still appended verbatim.
        $this->assertStringContainsString('boom', $event->get_description());
    }

    /**
     * api_request_failed: crud=r, no objecttable, fires without a related user.
     * @covers \mod_accredible\event\api_request_failed
     */
    public function test_api_request_failed(): void {
        $sink = $this->redirectEvents();
        $event = api_request_failed::create([
            'context' => \context_system::instance(),
            'other' => [
                'endpoint' => 'https://api.accredible.com/v1/credentials',
                'http_status' => 422,
                'error' => 'recipient_email: is invalid',
            ],
        ]);
        $event->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(api_request_failed::class, $events[0]);
        $data = $events[0]->get_data();
        $this->assertEquals('r', $data['crud']);
        $this->assertEquals(\core\event\base::LEVEL_OTHER, $data['edulevel']);
        $this->assertNull($data['objecttable']);
        $this->assertEquals(get_string('eventapirequestfailed', 'mod_accredible'), api_request_failed::get_name());
        $this->assertStringContainsString('422', $event->get_description());
        $this->assertStringContainsString('https://api.accredible.com/v1/credentials', $event->get_description());
    }
}
