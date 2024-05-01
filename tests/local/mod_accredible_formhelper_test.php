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
    }

    /**
     * Test the load_grade_item_options method.
     */
    public function test_load_grade_item_options() {
        global $DB;

        $formhelper = new formhelper();

        // When there are no grade items.
        $expected = array('' => 'Select an Activity Grade');
        $result = $formhelper->load_grade_item_options($this->course->id);
        $this->assertEquals($expected, $result);
        
        // When there are grade items.
        $quiz1 = $this->create_quiz_module($this->course->id);
        $gradeitem1 = $this->fetch_grade_item($this->course->id, 'mod', 'quiz', $quiz1->id);

        $quiz2 = $this->create_quiz_module($this->course->id);
        $gradeitem2 = $this->fetch_grade_item($this->course->id, 'mod', 'quiz', $quiz2->id);

        $expected = array(
            '' => 'Select an Activity Grade',
            $gradeitem1->id => $quiz1->name,
            $gradeitem2->id => $quiz2->name
        );
        $result = $formhelper->load_grade_item_options($this->course->id);
        $this->assertEquals($expected, $result);
    }

    /**
     * fetch mod grate item record
     *
     * @param int $courseid
     * @param string $itemmodule
     * @param int $iteminstance
     */
    private function fetch_grade_item($courseid, $itemtype, $itemmodule, $iteminstance) {
        global $DB;

        return $DB->get_record(
          'grade_items',
          array(
            'courseid' => $courseid,
            'itemtype' => $itemtype,
            'itemmodule' => $itemmodule,
            'iteminstance' => $iteminstance
          ),
          '*',
          MUST_EXIST
        );
    }

    /**
     * Create quiz module test
     *
     * @param int $courseid
     */
    private function create_quiz_module($courseid) {
        $quiz = array("course" => $courseid, "grade" => 10);
        return $this->getDataGenerator()->create_module('quiz', $quiz);
    }
}
