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
     * Test the load_course_field_options method.
     * @covers ::load_course_field_options
     */
    public function test_load_course_field_options() {
        global $DB;

        $formhelper = new formhelper();

        $expected = array(
            '' => 'Select a Moodle course field',
            'fullname' => 'fullname',
            'shortname' => 'shortname',
            'startdate' => 'startdate',
            'enddate' => 'enddate'
        );
        $result = $formhelper->load_course_field_options();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the load_course_custom_field_options method.
     * @covers ::load_course_custom_field_options
     */
    public function test_load_course_custom_field_options() {
        global $DB;

        $formhelper = new formhelper();

        // When there are no custom fields.
        $expected = array('' => 'Select a Moodle course custom field');
        $result = $formhelper->load_course_custom_field_options();
        $this->assertEquals($expected, $result);

        // When there are custom fields.
        $customfield1 = array(
            'shortname' => 'customfield1',
            'name' => 'Custom Field 1',
            'timecreated' => time(),
            'timemodified' => time()
        );
        $customfield1id = $DB->insert_record('customfield_field', $customfield1);

        $customfield2 = array(
            'shortname' => 'customfield2',
            'name' => 'Custom Field 2',
            'timecreated' => time(),
            'timemodified' => time()
        );
        $customfield2id = $DB->insert_record('customfield_field', $customfield2);

        $expected = array(
          '' => 'Select a Moodle course custom field',
          $customfield1id => 'Custom Field 1',
          $customfield2id => 'Custom Field 2'
        );
        $result = $formhelper->load_course_custom_field_options();
        $this->assertEquals($expected, $result);
    }


    /**
     * Test the load_user_profile_field_options method.
     * @covers ::load_user_profile_field_options
     */
    public function test_load_user_profile_field_options() {
        global $DB;
        $formhelper = new formhelper();

        // When there are no user_info_field records.
        $expected = array('' => 'Select a Moodle user profile field');
        $result = $formhelper->load_user_profile_field_options();
        $this->assertEquals($expected, $result);

        // When there are user_info_field records.
        $userinfofield1 = array(
            'shortname' => 'userinfo1',
            'name' => 'User Info 1'
        );
        $userinfofield1id = $DB->insert_record('user_info_field', $userinfofield1);

        $userinfofield2 = array(
            'shortname' => 'userinfo2',
            'name' => 'User Info 2'
        );
        $userinfofield2id = $DB->insert_record('user_info_field', $userinfofield2);

        $expected = array(
            '' => 'Select a Moodle user profile field',
            $userinfofield1id => 'User Info 1',
            $userinfofield2id => 'User Info 2'
        );
        $result = $formhelper->load_user_profile_field_options();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the attributemapping_default_values method.
     * @covers ::attributemapping_default_values
     */
    public function test_attributemapping_default_values() {
        $formhelper = new formhelper();

        // When the JSON string $attributemapping is null.
        $result = $formhelper->attributemapping_default_values(null);
        $expected = [
            'coursefieldmapping' => [],
            'coursecustomfieldmapping' => [],
            'userprofilefieldmapping' => []
        ];
        $this->assertEquals($expected, $result);

        // When the JSON string $attributemapping is provided.
        $jsoninput = json_encode([
            (object)[
                'table' => 'course',
                'field' => 'startdate',
                'accredibleattribute' => 'Moodle Course Start Date'
            ],
            (object)[
                'table' => 'user_info_field',
                'id' => 123,
                'accredibleattribute' => 'Moodle User Birthday'
            ],
            (object)[
                'table' => 'customfield_field',
                'id' => 321,
                'accredibleattribute' => 'Moodle Typology'
            ]
        ]);
        $result = $formhelper->attributemapping_default_values($jsoninput);
        $expected = [
            'coursefieldmapping' => [
                [
                    'field' => 'startdate',
                    'accredibleattribute' => 'Moodle Course Start Date'
                ]
            ],
            'coursecustomfieldmapping' => [
                [
                    'id' => 321,
                    'accredibleattribute' => 'Moodle Typology'
                ]
            ],
            'userprofilefieldmapping' => [
                [
                    'id' => 123,
                    'accredibleattribute' => 'Moodle User Birthday'
                ]
            ]
        ];
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
