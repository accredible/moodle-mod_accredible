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

namespace mod_accredible\local;

/**
 * Unit tests for mod/accredible/classes/local/formhelper.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_formhelper_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $gradeitem = array(
            'courseid' => $this->course->id,
            'itemtype' => 'course',
            'itemmodule' => null
        );
        if (!$DB->record_exists('grade_items', $gradeitem)) {
            $DB->insert_record('grade_items', $gradeitem);
        }
        $this->coursegradeitemid = $DB->get_field('grade_items', 'id', $gradeitem);
    }

    /**
     * Test the load_grade_item_options method.
     * @covers ::load_grade_item_options
     */
    public function test_load_grade_item_options() {
        $formhelper = new formhelper();

        // When there are no grade items.
        $expected = array(
            '' => 'Select an Activity Grade',
            $this->coursegradeitemid => get_string('coursetotal', 'accredible')
        );
        $result = $formhelper->load_grade_item_options($this->course->id);
        $this->assertEquals($expected, $result);

        // When there are grade items.
        $quizid1 = $this->create_quiz_module($this->course->id, 'Quiz 1');
        $gradeitemid1 = $this->create_grade_item($this->course->id, 'Quiz 1', 'quiz', $quizid1);

        $quizid2 = $this->create_quiz_module($this->course->id, 'Quiz 2');
        $gradeitemid2 = $this->create_grade_item($this->course->id, 'Quiz 2', 'quiz', $quizid2);

        $expected = array(
            '' => 'Select an Activity Grade',
            $this->coursegradeitemid => get_string('coursetotal', 'accredible'),
            $gradeitemid1 => 'Quiz 1',
            $gradeitemid2 => 'Quiz 2',
        );
        $result = $formhelper->load_grade_item_options($this->course->id);
        $this->assertEquals($expected, $result);
    }

    /**
     * fetch course grate item record
     *
     * @param int $courseid
     */
    private function fetch_course_grade_item($courseid) {
        global $DB;

        return $DB->get_record(
            'grade_items',
            array(
                'courseid' => $courseid,
                'itemtype' => 'course',
                'itemmodule' => null
            ),
            '*',
            MUST_EXIST
        );
    }

    /**
     * Create quiz module test
     *
     * @param int $courseid
     * @param string $name
     */
    private function create_quiz_module($courseid, $name) {
        global $DB;

        return $DB->insert_record('quiz',
            array(
                'course' => $courseid,
                'name' => $name,
                'intro' => 'Default intro',
                'grade' => 10
            )
        );
    }

    /**
     * Create quiz module test
     *
     * @param int $courseid
     * @param string $itemname
     * @param string $itemmodule
     * @param int $iteminstance
     */
    private function create_grade_item($courseid, $itemname, $itemmodule, $iteminstance) {
        global $DB;
        $gradeitem = array(
            "courseid" => $courseid,
            "itemname" => $itemname,
            "itemtype" => 'mod',
            "itemmodule" => $itemmodule,
            "iteminstance" => $iteminstance,
            "itemnumber" => 0
        );
        return $DB->insert_record('grade_items', $gradeitem);
    }
}
