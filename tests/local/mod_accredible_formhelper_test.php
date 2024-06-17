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

        $expected = [
            ['value' => '', 'name' => 'Select a Moodle course field'],
            ['value' => 'fullname', 'name' => 'fullname'],
            ['value' => 'shortname', 'name' => 'shortname'],
            ['value' => 'startdate', 'name' => 'startdate'],
            ['value' => 'enddate', 'name' => 'enddate'],
        ];
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
        $expected = [
            ['value' => '', 'name' => 'Select a Moodle course custom field']
        ];
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

        $expected = [
            ['value' => '', 'name' => 'Select a Moodle course custom field'],
            ['value' => $customfield1id, 'name' => 'Custom Field 1'],
            ['value' => $customfield2id, 'name' => 'Custom Field 2'],
        ];
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
        $expected = [
            ['value' => '', 'name' => 'Select a Moodle user profile field']
        ];
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

        $expected = [
            ['value' => '', 'name' => 'Select a Moodle user profile field'],
            ['value' => $userinfofield1id, 'name' => 'User Info 1'],
            ['value' => $userinfofield2id, 'name' => 'User Info 2'],
        ];
        $result = $formhelper->load_user_profile_field_options();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the map_select_options method.
     * @covers ::map_select_options
     */
    public function test_map_select_options() {
        $formhelper = new formhelper();

        // When the options array has values.
        $options = [
            'key1' => 'Option 1',
            'key2' => 'Option 2',
            'key3' => 'Option 3'
        ];
        $expected = [
            ['name' => 'Option 1', 'value' => 'key1'],
            ['name' => 'Option 2', 'value' => 'key2'],
            ['name' => 'Option 3', 'value' => 'key3']
        ];
        $result = $formhelper->map_select_options($options);
        $this->assertEquals($expected, $result);

        // When the options array is null.
        $options = null;
        $expected = [];
        $result = $formhelper->map_select_options($options);
        $this->assertEquals($expected, $result);

        // When the options array is empty.
        $options = [];
        $result = $formhelper->map_select_options($options);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the get_attributekeys_choices method.
     * @covers ::get_attributekeys_choices
     */
    public function test_get_attributekeys_choices() {
        // Mock attribute_keys class and 1 method.
        $attributekeysmock = $this->getMockBuilder(attribute_keys::class)
            ->onlyMethods(['get_attribute_keys'])
            ->getMock();

        // When get_attribute_keys(mocked) method returns values for 'text' and 'date'.
        $attributekeysmock->expects($this->any())
            ->method('get_attribute_keys')
            ->will($this->returnValueMap([
                ['text', ['key1' => 'Text Attribute 1', 'key2' => 'Text Attribute 2']],
                ['date', ['key3' => 'Date Attribute 1']]
            ]));
        $formhelper = $this->create_formhelper_with_mock($attributekeysmock);

        $expected = [
            '' => get_string('accrediblecustomattributeselectprompt', 'accredible'),
            'key1' => 'Text Attribute 1',
            'key2' => 'Text Attribute 2',
            'key3' => 'Date Attribute 1'
        ];
        $result = $formhelper->get_attributekeys_choices();
        $this->assertEquals($expected, $result);

        // Mock attribute_keys class and 1 method.
        $attributekeysmock = $this->getMockBuilder(attribute_keys::class)
            ->onlyMethods(['get_attribute_keys'])
            ->getMock();

        // When get_attribute_keys(mocked) method returns empty for 'text' and 'date'.
        $attributekeysmock->expects($this->any())
            ->method('get_attribute_keys')
            ->will($this->returnValueMap([
                ['text', []],
                ['date', []]
            ]));
        $formhelper = $this->create_formhelper_with_mock($attributekeysmock);

        $expected = [
            '' => get_string('accrediblecustomattributeselectprompt', 'accredible'),
        ];
        $result = $formhelper->get_attributekeys_choices();
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
                    'index' => 0,
                    'field' => 'startdate',
                    'accredibleattribute' => 'Moodle Course Start Date'
                ]
            ],
            'coursecustomfieldmapping' => [
                [
                    'index' => 0,
                    'id' => 321,
                    'accredibleattribute' => 'Moodle Typology'
                ]
            ],
            'userprofilefieldmapping' => [
                [
                    'index' => 0,
                    'id' => 123,
                    'accredibleattribute' => 'Moodle User Birthday'
                ]
            ]
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test the reindexarray method.
     * @covers ::reindexarray
     */
    public function test_reindexarray() {
        $formhelper = new formhelper();

        // When the associative array is not passed.
        $result = $formhelper->reindexarray(null);
        $expected = [];
        $this->assertEquals($expected, $result);

        // When the associative array is provided.
        $given = [
            "2" => ["field" => "1", "attribute" => "moodle_course_grade"],
            "3" => ["field" => "3", "attribute" => "moodle_year"]
        ];
        $result = $formhelper->reindexarray($given);
        $expected = [
            "0" => ["field" => "1", "attribute" => "moodle_course_grade"],
            "1" => ["field" => "3", "attribute" => "moodle_year"]
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

    /**
     * Helper method to create an instance of formhelper with a mock attribute_keys class.
     *
     * @param mixed $attributekeysmock The mock instance of the attribute_keys class.
     * @return formhelper The formhelper class instance extended with a mock attribute_keys client.
     */
    private function create_formhelper_with_mock($attributekeysmock) {
        // Use an anonymous class to extend formhelper and inject the mock.
        return new class($attributekeysmock) extends formhelper {
            // Mock instance of the attribute_keys client.
            public $mockclient;

            /**
             * Constructor
             *
             * Initializes the anonymous class with the mock client.
             *
             * @param mixed $mockclient The mock instance of the attribute_keys client.
             */
            public function __construct($mockclient) {
                $this->mockclient = $mockclient;
            }

            /**
             * Overrides the get_attribute_keys_client method to return the mock client.
             *
             * @return $mockclient The mock instance of the attribute_keys client.
             */
            public function get_attribute_keys_client() {
                return $this->mockclient;
            }
        };
    }
}
