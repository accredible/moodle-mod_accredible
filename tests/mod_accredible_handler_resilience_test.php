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
use mod_accredible\event\credential_issued;
use mod_accredible\event\credential_issue_failed;
use mod_accredible\event\credential_issue_skipped;

/**
 * Resilience tests for the issuance event handlers in locallib.php.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_handler_resilience_test extends \advanced_testcase {
    /**
     * Test user.
     * @var \stdClass $user
     */
    protected $user;

    /**
     * Test course.
     * @var \stdClass $course
     */
    protected $course;

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
        $this->course = $this->getDataGenerator()->create_course();
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');
    }

    /**
     * Create a quiz module in the test course.
     * @return \stdClass
     */
    private function create_quiz(): \stdClass {
        return $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'grade' => 10]);
    }

    /**
     * Insert an accredible activity record and return it.
     * @param int $finalquiz
     * @param mixed $completionactivities
     * @return \stdClass
     */
    private function create_accredible_record($finalquiz = 0, $completionactivities = null): \stdClass {
        global $DB;
        $id = $DB->insert_record('accredible', [
            'name' => 'Test',
            'course' => $this->course->id,
            'finalquiz' => $finalquiz,
            'passinggrade' => 70,
            'timecreated' => time(),
            'groupid' => 1,
            'completionactivities' => $completionactivities,
        ]);
        return $DB->get_record('accredible', ['id' => $id]);
    }

    /**
     * Insert a finished quiz attempt for the test user.
     * @param int $quizid
     * @param int $attempt
     * @return \stdClass
     */
    private function insert_attempt($quizid, $attempt = 1): \stdClass {
        global $DB;
        $id = $DB->insert_record('quiz_attempts', (object)[
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
            'sumgrades' => 8,
        ]);
        return $DB->get_record('quiz_attempts', ['id' => $id]);
    }

    /**
     * Build a stub attempt_submitted event the handler can read.
     * @param \stdClass $quiz
     * @param \stdClass $attempt
     * @return object
     */
    private function quiz_submitted_event($quiz, $attempt) {
        $event = new class {
            /** @var int */
            public $objectid;
            /** @var int */
            public $relateduserid;
            /** @var int */
            public $courseid;
            /** @var int */
            public $contextinstanceid;
            /** @var \stdClass */
            public $attemptsnap;
            /** @var \stdClass */
            public $quizsnap;
            /**
             * Mimic core event record snapshots.
             * @param string $table
             * @param int $id
             * @return \stdClass
             */
            public function get_record_snapshot($table, $id) {
                return $table === 'quiz_attempts' ? $this->attemptsnap : $this->quizsnap;
            }
        };
        $event->objectid = $attempt->id;
        $event->relateduserid = $this->user->id;
        $event->courseid = $this->course->id;
        $event->contextinstanceid = $quiz->cmid;
        $event->attemptsnap = $attempt;
        $event->quizsnap = $quiz;
        return $event;
    }

    /**
     * Build a mock credentials client overriding the API-touching methods.
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function mock_credentials() {
        return $this->getMockBuilder(credentials::class)
            ->onlyMethods(['check_for_existing_credential', 'create_credential'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get the events of a given class captured by the sink.
     * @param \core\event\base[] $events
     * @param string $class
     * @return array
     */
    private function filter_events($events, $class): array {
        return array_values(array_filter($events, function($e) use ($class) {
            return $e instanceof $class;
        }));
    }

    /**
     * An exception while issuing is caught, logged, and does not bubble out.
     * @covers ::accredible_quiz_submission_handler
     */
    public function test_quiz_handler_catches_create_exception(): void {
        global $DB;
        $quiz = $this->create_quiz();
        $attempt = $this->insert_attempt($quiz->id, 1);
        $DB->insert_record('quiz_grades', ['quiz' => $quiz->id, 'userid' => $this->user->id, 'grade' => 8]);
        $this->create_accredible_record($quiz->id);

        $mockcreds = $this->mock_credentials();
        $mockcreds->method('check_for_existing_credential')->willReturn(false);
        $mockcreds->method('create_credential')->willThrowException(new \moodle_exception('error'));

        $sink = $this->redirectEvents();
        // Must not throw.
        accredible_quiz_submission_handler($this->quiz_submitted_event($quiz, $attempt), $mockcreds);

        $failed = $this->filter_events($sink->get_events(), credential_issue_failed::class);
        $this->assertCount(1, $failed);
        $this->assertEquals('exception', $failed[0]->other['reason']);
    }

    /**
     * A submission for a quiz that is neither the final quiz nor a completion activity is silent.
     * @covers ::accredible_quiz_submission_handler
     */
    public function test_quiz_handler_silent_for_irrelevant_quiz(): void {
        $quiz = $this->create_quiz();
        $otherquiz = $this->create_quiz();
        $attempt = $this->insert_attempt($quiz->id, 1);
        $this->create_accredible_record($otherquiz->id);

        $mockcreds = $this->mock_credentials();
        $mockcreds->method('check_for_existing_credential')->willReturn(false);
        $mockcreds->expects($this->never())->method('create_credential');

        $sink = $this->redirectEvents();
        accredible_quiz_submission_handler($this->quiz_submitted_event($quiz, $attempt), $mockcreds);

        $this->assertCount(0, $sink->get_events());
    }

    /**
     * A record with no auto-issue rule logs nothing_to_check.
     * @covers ::accredible_quiz_submission_handler
     */
    public function test_quiz_handler_logs_nothing_to_check(): void {
        $quiz = $this->create_quiz();
        $attempt = $this->insert_attempt($quiz->id, 1);
        $this->create_accredible_record(0, null);

        $mockcreds = $this->mock_credentials();
        $mockcreds->expects($this->never())->method('create_credential');

        $sink = $this->redirectEvents();
        accredible_quiz_submission_handler($this->quiz_submitted_event($quiz, $attempt), $mockcreds);

        $skipped = $this->filter_events($sink->get_events(), credential_issue_skipped::class);
        $this->assertCount(1, $skipped);
        $this->assertEquals('nothing_to_check', $skipped[0]->other['reason']);
    }

    /**
     * course_completed does not re-issue when a credential already exists
     * @covers ::accredible_course_completed_handler
     */
    public function test_course_completed_guard_prevents_double_issue(): void {
        $this->create_accredible_record(0, serialize_completion_array([999 => 1]));

        $mockcreds = $this->mock_credentials();
        $mockcreds->method('check_for_existing_credential')->willReturn((object)['id' => 123]);
        $mockcreds->expects($this->never())->method('create_credential');

        $event = new class {
            /** @var int */
            public $relateduserid;
            /** @var int */
            public $courseid;
        };
        $event->relateduserid = $this->user->id;
        $event->courseid = $this->course->id;

        $sink = $this->redirectEvents();
        accredible_course_completed_handler($event, $mockcreds);

        $issued = $this->filter_events($sink->get_events(), credential_issued::class);
        $this->assertCount(0, $issued);
    }
}
