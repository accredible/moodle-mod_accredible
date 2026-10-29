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

namespace mod_accredible\external;

/**
 * Unit tests for the reload_users external function.
 *
 * form_helper includes lib/externallib.php, which Moodle 4.2+ only allows in an isolated process.
 *
 * @runTestsInSeparateProcesses
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_form_helper_test extends \advanced_testcase {
    /**
     * Course holding the activity.
     * @var \stdClass $course
     */
    protected $course;
    /**
     * Accredible instance id in $course.
     * @var int $instanceid
     */
    protected $instanceid;
    /**
     * Editing teacher in $course.
     * @var \stdClass $editingteacher
     */
    protected $editingteacher;
    /**
     * Non-editing teacher in $course.
     * @var \stdClass $teacher
     */
    protected $teacher;
    /**
     * Student in $course.
     * @var \stdClass $student
     */
    protected $student;

    /**
     * Setup before every test.
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/accredible/locallib.php');

        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->instanceid = $this->create_activity($this->course->id);
        $this->editingteacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * An editing teacher gets the list when editing an existing activity.
     *
     * @covers ::reload_users
     */
    public function test_editing_teacher_can_reload_users_for_existing_activity(): void {
        $this->setUser($this->editingteacher);

        $result = $this->reload_users($this->course->id, $this->instanceid);

        $this->assertCount(3, $result['users']);
    }

    /**
     * An editing teacher gets the list when adding a new activity.
     *
     * @covers ::reload_users
     */
    public function test_editing_teacher_can_reload_users_for_new_activity(): void {
        $this->setUser($this->editingteacher);

        $result = $this->reload_users($this->course->id, 0);

        $this->assertCount(3, $result['users']);
        $this->assertSame([], $result['unissued_users']);
    }

    /**
     * A student cannot load the list when editing.
     *
     * @covers ::reload_users
     */
    public function test_student_cannot_reload_users_for_existing_activity(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        $this->reload_users($this->course->id, $this->instanceid);
    }

    /**
     * A student cannot load the list when adding.
     *
     * @covers ::reload_users
     */
    public function test_student_cannot_reload_users_for_new_activity(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        $this->reload_users($this->course->id, 0);
    }

    /**
     * A non-editing teacher cannot open the edit form, so cannot load its list either.
     *
     * @covers ::reload_users
     */
    public function test_non_editing_teacher_cannot_reload_users(): void {
        $this->setUser($this->teacher);

        $this->expectException(\required_capability_exception::class);
        $this->reload_users($this->course->id, $this->instanceid);
    }

    /**
     * An activity from another course is rejected, even for an editing teacher of both.
     *
     * @covers ::reload_users
     */
    public function test_instance_from_other_course_is_rejected(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $otherinstanceid = $this->create_activity($othercourse->id);
        $this->getDataGenerator()->enrol_user($this->editingteacher->id, $othercourse->id, 'editingteacher');
        $this->setUser($this->editingteacher);

        $this->expectException(\dml_missing_record_exception::class);
        $this->reload_users($this->course->id, $otherinstanceid);
    }

    /**
     * A user who is not enrolled in the course is rejected.
     *
     * @covers ::reload_users
     */
    public function test_unenrolled_user_is_rejected(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        $this->reload_users($this->course->id, $this->instanceid);
    }

    /**
     * Call reload_users the way the web service layer does, without an Accredible group.
     *
     * @param int $courseid
     * @param int $instanceid
     * @return array cleaned return value
     */
    private function reload_users($courseid, $instanceid): array {
        $result = form_helper::reload_users($courseid, 0, $instanceid);
        return \external_api::clean_returnvalue(form_helper::reload_users_returns(), $result);
    }

    /**
     * Create an accredible activity with its course module.
     *
     * @param int $courseid
     * @return int accredible instance id
     */
    private function create_activity($courseid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $instanceid = $DB->insert_record('accredible', (object) [
            'name' => 'Accredible Test',
            'course' => $courseid,
            'finalquiz' => 0,
            'passinggrade' => 70,
            'completionactivities' => null,
            'includegradeattribute' => 0,
            'timecreated' => time(),
            'certificatename' => 'Accredible Test',
        ]);
        $cm = (object) [
            'course' => $courseid,
            'module' => $DB->get_field('modules', 'id', ['name' => 'accredible'], MUST_EXIST),
            'instance' => $instanceid,
            'section' => 0,
        ];
        $cmid = add_course_module($cm);
        course_add_cm_to_section($courseid, $cmid, 0);
        return $instanceid;
    }
}
