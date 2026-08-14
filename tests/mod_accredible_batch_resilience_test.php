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

namespace mod_accredible;

use mod_accredible\local\credentials;
use mod_accredible\event\credential_issue_failed;

/**
 * Resilience tests for the manual issuance batches in lib.php.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_batch_resilience_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/accredible/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        // Every activity issues against a brand: there is no default account.
        set_config('accredible_brand1_name', 'CEAC');
        set_config('accredible_brand1_api_key', 'ceacapikey');
        set_config('accredible_brand1_is_eu', 1);

        putenv('ACCREDIBLE_DEV_API_ENDPOINT');
    }

    /**
     * One failing user must not abort the batch: the rest are still processed,
     * a credential_issue_failed is logged for the failure, and a summary surfaces.
     * accredible_update_instance uses the identical per-user try/catch construct.
     * @covers ::accredible_add_instance
     */
    public function test_add_instance_continues_past_failing_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $post = (object)[
            'name' => 'Batch Test',
            'course' => $course->id,
            'finalquiz' => 0,
            'passinggrade' => 70,
            'groupid' => 1,
            'brand' => 'CEAC',
            'completionactivities' => null,
            'instance' => null,
            'coursemodule' => 0,
            'users' => [$user1->id => 1, $user2->id => 1, $user3->id => 1],
        ];

        // The mock throws for the second user and is a no-op (skip) for the others,
        // so the success path does not reach the evidence-item API calls.
        $mockcreds = $this->getMockBuilder(credentials::class)
            ->onlyMethods(['create_credential'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockcreds->expects($this->exactly(3))
            ->method('create_credential')
            ->willReturnCallback(function (
                $user,
                $groupid = null,
                $issuedon = null,
                $customattributes = null,
                $context = null
            ) use ($user2) {
                if ($user->id == $user2->id) {
                    throw new \moodle_exception('error');
                }
                return null;
            });

        $sink = $this->redirectEvents();
        // Must not throw despite the failing user.
        $recordid = accredible_add_instance($post, null, $mockcreds);
        $this->assertNotEmpty($recordid);

        // The failing user is logged once, with the right reason and user.
        $failed = array_values(array_filter($sink->get_events(), function ($e) {
            return $e instanceof credential_issue_failed;
        }));
        $this->assertCount(1, $failed);
        $this->assertEquals($user2->id, $failed[0]->relateduserid);
        $this->assertEquals('exception', $failed[0]->other['reason']);

        // The admin gets a summary naming the failed user.
        $notifications = \core\notification::fetch();
        $this->assertNotEmpty($notifications);
        $combined = '';
        foreach ($notifications as $notification) {
            $combined .= $notification->get_message();
        }
        $this->assertStringContainsString(fullname($user2), $combined);
    }
}
