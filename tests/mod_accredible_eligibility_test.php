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

use mod_accredible\event\credential_issue_skipped;

/**
 * Unit tests for accredible_evaluate_completion_eligibility() in locallib.php.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_eligibility_test extends \advanced_testcase {
    /**
     * Test user.
     * @var \stdClass $user
     */
    protected $user;

    /**
     * Setup before every test.
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/accredible/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Build an accredible activity record stub with the given completionactivities blob.
     * @param mixed $completionactivities
     * @return \stdClass
     */
    private function record($completionactivities): \stdClass {
        return (object)['completionactivities' => $completionactivities, 'groupid' => 1];
    }

    /**
     * Evaluate eligibility for a quiz id against the given record.
     * @param \stdClass $record
     * @param int $quizid
     * @return array
     */
    private function evaluate($record, $quizid): array {
        return accredible_evaluate_completion_eligibility(
            $this->user, $record, (object)['id' => $quizid], \context_system::instance());
    }

    /**
     * Insert a finished quiz attempt for the test user.
     * @param int $quizid
     * @param int $attempt
     */
    private function insert_finished_attempt($quizid, $attempt): void {
        global $DB;
        $DB->insert_record('quiz_attempts', (object)[
            'quiz' => $quizid,
            'userid' => $this->user->id,
            'attempt' => $attempt,
            'uniqueid' => $quizid * 1000 + $attempt,
            'layout' => '1,0',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => 0,
            'timefinish' => time(),
            'timemodified' => time(),
            'sumgrades' => 0,
        ]);
    }

    /**
     * Empty completionactivities is not relevant to this record's rule (silent).
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_relevant_false_when_no_completion_activities(): void {
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(null), 123);
        $this->assertSame(['relevant' => false], $result);
        $this->assertCount(0, $sink->get_events());
    }

    /**
     * A non-empty value that fails to unserialize is flagged as a corrupt record.
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_malformed_completion_record(): void {
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(base64_encode('this-is-not-serialized')), 123);
        $this->assertFalse($result['eligible']);
        $this->assertSame('malformed_completion_record', $result['reason']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(credential_issue_skipped::class, $events[0]);
        $this->assertEquals('malformed_completion_record', $events[0]->other['reason']);
    }

    /**
     * A quiz not tracked by the record's completion rule is not relevant (silent).
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_relevant_false_when_quiz_not_tracked(): void {
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(serialize_completion_array([888 => 0])), 123);
        $this->assertSame(['relevant' => false], $result);
        $this->assertCount(0, $sink->get_events());
    }

    /**
     * Other required activities still incomplete yields completion_not_met.
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_completion_not_met(): void {
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(serialize_completion_array([123 => 0, 888 => 0])), 123);
        $this->assertFalse($result['eligible']);
        $this->assertSame('completion_not_met', $result['reason']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertEquals('completion_not_met', $events[0]->other['reason']);
    }

    /**
     * All required activities complete yields eligible (silent — no skip event).
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_eligible_when_all_complete(): void {
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(serialize_completion_array([123 => 0])), 123);
        $this->assertSame(['eligible' => true], $result);
        $this->assertCount(0, $sink->get_events());
    }

    /**
     * A prior finished attempt (> 1) on the deciding quiz yields repeat_attempt.
     * @covers ::accredible_evaluate_completion_eligibility
     */
    public function test_repeat_attempt(): void {
        $this->insert_finished_attempt(123, 2);
        $sink = $this->redirectEvents();
        $result = $this->evaluate($this->record(serialize_completion_array([123 => 0])), 123);
        $this->assertFalse($result['eligible']);
        $this->assertSame('repeat_attempt', $result['reason']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertEquals('repeat_attempt', $events[0]->other['reason']);
    }
}
