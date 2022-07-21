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

use mod_accredible\local\user;
use mod_accredible\client\client;
use mod_accredible\apirest\apirest;

/**
 * Unit tests for mod/accredible/classes/helpers/user_helper.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_users_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Add plugin settings.
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);

        // Unset the devlopment environment variable.
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');

        $this->mockapi = new class {
            /**
             * Returns a mock API response based on the fixture json.
             * @param string $jsonpath
             * @return array
             */
            public function resdata($jsonpath) {
                global $CFG;
                $fixturedir = $CFG->dirroot . '/mod/accredible/tests/fixtures/mockapi/v1/';
                $filepath = $fixturedir . $jsonpath;
                return json_decode(file_get_contents($filepath));
            }
        };

        $this->user = $this->getDataGenerator()->create_user(array('email' => 'person1@example.com'));
        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($this->course->id);
    }

    /**
     * Generate list of users with their credentials from a course
     */
    public function test_get_users_with_credentials() {
        $userhelper = new users();

        // When there are not users.
        $result = $userhelper->get_users_with_credentials(array());
        $this->assertEquals($result, array());

        // When there are users but not groupid.
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);

        $userrespone = array('id'             => $this->user->id,
                             'email'          => $this->user->email,
                             'name'           => $this->user->firstname . ' ' . $this->user->lastname,
                             'credential_url' => null,
                             'credential_id'  => null);
        $expectedresponse = array('0' => $userrespone);
        $enrolledusers = get_enrolled_users($this->context, "mod/accredible:view", null, 'u.*', 'id');
        $result = $userhelper->get_users_with_credentials($enrolledusers);
        $this->assertEquals($result, $expectedresponse);

        // When there users and groupid.
        $user2 = $this->getDataGenerator()->create_user(array('email' => 'person2@example.com'));
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id);
        $user2respone = array('id'             => $user2->id,
                              'email'          => $user2->email,
                              'name'           => $user2->firstname . ' ' . $user2->lastname,
                              'credential_url' => 'https://www.credential.net/10250012',
                              'credential_id'  => 10250012);
        $expectedresponse = array('0' => $userrespone, '1' => $user2respone);

        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdatapage1 = $this->mockapi->resdata('credentials/search_success.json');
        $resdatapage2 = $this->mockapi->resdata('credentials/search_success_page_2.json');

        // Expect to call the endpoint once with page and page_size.
        $urlpage1 = "https://api.accredible.com/v1/all_credentials?group_id=123&email=&page_size=50&page=1";
        $urlpage2 = "https://api.accredible.com/v1/all_credentials?group_id=123&email=&page_size=50&page=2";
        $mockclient1->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([$this->equalTo($urlpage1)], [$this->equalTo($urlpage2)])
            ->will($this->onConsecutiveCalls($resdatapage1, $resdatapage2));

        $api = new apirest($mockclient1);
        $userhelper = new users($api);
        $enrolledusers = get_enrolled_users($this->context, "mod/accredible:view", null, 'u.*', 'id');
        $result = $userhelper->get_users_with_credentials($enrolledusers, 123);
        $this->assertEquals($result, $expectedresponse);

        // When apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=123&email=&page_size=50&page=1";
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return empty array.
        $api = new apirest($mockclient2);
        $userhelper = new users($api);
        $result = $userhelper->get_users_with_credentials($enrolledusers, 123);
        $this->assertEquals($result, array());
    }

    /**
     * Generate list of users without credential but with requirements for the course pass.
     */
    public function test_get_unissued_users() {
        $userhelper = new users();
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id);

        $generateduser2 = $this->getDataGenerator()->create_user(array('email' => 'person3@example.com'));
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);
        $this->getDataGenerator()->enrol_user($generateduser2->id, $this->course->id);

        $user1 = array('id'             => $this->user->id,
                       'email'          => $this->user->email,
                       'name'           => $this->user->firstname . ' ' . $this->user->lastname,
                       'credential_url' => null,
                       'credential_id'  => null);
        $user2 = array('id'             => $generateduser2->id,
                       'email'          => $generateduser2->email,
                       'name'           => $generateduser2->firstname . ' ' . $generateduser2->lastname,
                       'credential_url' => 'https://www.credential.net/10250012',
                       'credential_id'  => 10250012);

        $users = array($user1, $user2);

        // When there are not users.
        $result = $userhelper->get_unissued_users(array(), $accredibleinstanceid);
        $this->assertEquals($result, array());

        // When accredible instance id not provided.
        $result = $userhelper->get_unissued_users($users);
        $this->assertEquals($result, array());

        // When the Accredible module don't have any requirement.
        $result = $userhelper->get_unissued_users($users, $accredibleinstanceid);
        $this->assertEquals($result, array());

        // When there are not users who pass the requirements.
        $quiz = $this->create_quiz_module($this->course->id);
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, $quiz->id);

        $result = $userhelper->get_unissued_users($users, $accredibleinstanceid);
        $this->assertEquals($result, array());

        // When there is a user who pass the requirements but have a credential.
        $this->create_quiz_grades($quiz->id, $generateduser2->id, 8);
        $result = $userhelper->get_unissued_users($users, $accredibleinstanceid);
        $this->assertEquals($result, array());

        // When there is a user who pass the requirements and not have a credential.
        $this->create_quiz_grades($quiz->id, $this->user->id, 9);
        $result = $userhelper->get_unissued_users($users, $accredibleinstanceid);
        $expectedresponse = array($user1);
        $this->assertEquals($result, $expectedresponse);
    }

    /**
     * Return list of grades from a grade item.
     */
    public function test_get_user_grades() {
        global $DB;
        $userhelper = new users();

        $generateduser2 = $this->getDataGenerator()->create_user(array('email' => 'person3@example.com'));
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);
        $this->getDataGenerator()->enrol_user($generateduser2->id, $this->course->id);

        $users = array(
            $this->user->id     => $this->user->id,
            $generateduser2->id => $generateduser2->id
        );

        // When accredible instance not provided.
        $result = $userhelper->get_user_grades(null, $users);
        $this->assertEquals($result, null);

        // When includegradeattribute is false.
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id);
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);
        $this->assertEquals($result, null);

        // When gradeattributegradeitemid is null.
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1);
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);
        $this->assertEquals($result, null);

        // When gradeattributegradeitemid is false.
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, 0);
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);
        $this->assertEquals($result, null);

        // When gradeattributekeyname is null.
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, 1);
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);
        $this->assertEquals($result, null);

        // When gradeattributekeyname is empty.
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, 1, "");
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);
        $this->assertEquals($result, null);

        // When there are not grades for the users.
        $quiz = $this->create_quiz_module($this->course->id);
        $gradeitemid = $this->create_grade_item($this->course->id, $quiz->name, 'quiz', $quiz->id);
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, $gradeitemid, "Custom Attribute");
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);

        $this->assertEquals($result, array());

        // When a user has a grade.
        $expectedresponse = array(
            $generateduser2->id => "80.00"
        );

        $this->create_grade_grades($gradeitemid, $generateduser2->id, 80);
        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, $gradeitemid, "Custom Attribute");
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);
        $result = $userhelper->get_user_grades($accredibleinstance, $users);

        $this->assertEquals($result, $expectedresponse);

        // When multiple users have a grade.
        $this->create_grade_grades($gradeitemid, $this->user->id, 60);
        $expectedresponse = array(
            $this->user->id => "60.00",
            $generateduser2->id => "80.00"
        );
        $result = $userhelper->get_user_grades($accredibleinstance, array($generateduser2->id, $this->user->id));

        $this->assertEquals($result, $expectedresponse);

        // When multiple users have a grade but only a user ID is sent.
        $expectedresponse = array(
            $generateduser2->id => "80.00"
        );

        $result = $userhelper->get_user_grades($accredibleinstance, $generateduser2->id);

        $this->assertEquals($result, $expectedresponse);

        // When user id is not sent.
        $result = $userhelper->get_user_grades($accredibleinstance, null);

        $this->assertEquals($result, null);
    }

    /**
     * Return list of grades from a grade item.
     */
    public function test_load_user_grade_as_custom_attributes() {
        global $DB;
        $userhelper = new users();

        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);

        $accredibleinstanceid = $this->create_accredible_instance($this->course->id, 0, 1, 1, "Custom Attribute");
        $accredibleinstance = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);

        // When grades not provided.
        $result = $userhelper->load_user_grade_as_custom_attributes($accredibleinstance, array(), $this->user->id);
        $this->assertEquals($result, null);

        // When there's a grade for the user.
        $grades = array(
            $this->user->id => "80.00"
        );

        $expectedresponse = array("Custom Attribute" => "80.00");

        $result = $userhelper->load_user_grade_as_custom_attributes($accredibleinstance, $grades, $this->user->id);
        $this->assertEquals($result, $expectedresponse);
    }

    /**
     * Create accredible activity.
     *
     * @param int $courseid
     * @param int $finalquizid
     * @param int $includegradeattribute
     * @param int $gradeattributegradeitemid
     * @param string $gradeattributekeyname
     */
    private function create_accredible_instance($courseid, $finalquizid = 0, $includegradeattribute = 0,
        $gradeattributegradeitemid = null, $gradeattributekeyname = null) {

        global $DB;
        $dbrecord = array(
            "name"                      => 'Accredible Test',
            "course"                    => $courseid,
            "finalquiz"                 => $finalquizid,
            "passinggrade"              => 70,
            "timecreated"               => time(),
            "groupid"                   => 1,
            "completionactivities"      => null,
            "includegradeattribute"     => $includegradeattribute,
            "gradeattributegradeitemid" => $gradeattributegradeitemid,
            "gradeattributekeyname"     => $gradeattributekeyname,
        );

        return $DB->insert_record('accredible', $dbrecord);
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
     * Create quiz module test
     *
     * @param int $courseid
     */
    private function create_quiz_module($courseid) {
        $quiz = array("course" => $courseid, "grade" => 10);
        return $this->getDataGenerator()->create_module('quiz', $quiz);
    }

    /**
     * Create quiz grades test
     *
     * @param int $quizid
     * @param int $userid
     * @param int $grade
     */
    private function create_quiz_grades($quizid, $userid, $grade) {
        global $DB;
        $quizgrade = array("quiz" => $quizid, "userid" => $userid, "grade" => $grade);
        $DB->insert_record('quiz_grades', $quizgrade);
    }

    /**
     * Create quiz grades test
     *
     * @param int $gradeitemid
     * @param int $userid
     * @param int $grade
     */
    private function create_grade_grades($gradeitemid, $userid, $grade) {
        global $DB;
        $quizgrade = array(
            "itemid" => $gradeitemid,
            "rawgrade" => $grade,
            "userid" => $userid,
            "finalgrade" => $grade
        );
        $DB->insert_record('grade_grades', $quizgrade);
    }
}
