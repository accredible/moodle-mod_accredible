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

use mod_accredible\apirest\apirest;

/**
 * Unit tests for mod/accredible/classes/local/evidenceitems.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_evidenceitems_test extends \advanced_testcase {
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

        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Post credential evidence test
     * @covers ::post_evidence
     */
    public function test_post_evidence() {
        // When the throw_error is FALSE and the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/create_success.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';
        $evidenceitem = array(
            "string_object" => "100",
            "description" => "Quiz",
            "custom" => true,
            "category" => "grade"
        );
        $reqdata = json_encode(array("evidence_item" => $evidenceitem));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $evidenceitems = new evidenceitems($api);
        $result = $evidenceitems->post_evidence(1, $evidenceitem, false);
        $this->assertEquals($result, null);

        // When the throw_error is FALSE and the response is NOT successful.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to not throwing an exception.
        $api = new apirest($mockclient2);
        $evidenceitems = new evidenceitems($api);
        $result = $evidenceitems->post_evidence(1, $evidenceitem, false);
        $this->assertEquals($result, null);

        // When the throw_error is TRUE and the response is NOT successful.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata without throwing an exception.
        $foundexception = false;
        $api = new apirest($mockclient3);
        $evidenceitems = new evidenceitems($api);
        try {
            $evidenceitems->post_evidence(1, $evidenceitem, true, $api);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Post credential evidence from essay answers test
     * @covers ::post_essay_answers
     */
    public function test_post_essay_answers() {
        global $DB;

        $evidenceitems = new evidenceitems();

        // When there are not quizes.
        $result = $evidenceitems->post_essay_answers(1, 1, 1);

        $this->assertEmpty($DB->get_records('quiz'));
        $this->assertEquals($result, null);

        // When there are not quiz attempts.
        $this->create_quiz_module(1);
        $result = $evidenceitems->post_essay_answers(1, 1, 1);

        $this->assertEmpty($DB->get_records('quiz_attempts'));
        $this->assertEquals($result, null);

        // When there are no quiz attemps for user.
        $quiz = $this->create_quiz_module(1);
        $this->create_quiz_grades($quiz->id, 2, 5);
        $result = $evidenceitems->post_essay_answers(1, 1, 1);

        $this->assertEquals($result, null);

        // When there are quiz attemps for user.
        $quiz = $this->create_quiz_module(1, $this->user->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, array('category' => $category->id));
        quiz_add_quiz_question($question->id, $quiz);

        $questionusageid = $this->create_question_usage();
        $this->create_question_attempt($quiz->id, $this->user->id, $questionusageid, $question->id);
        $this->create_quiz_attempt($quiz->id, $this->user->id, $questionusageid);

        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/create_success.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';
        $questionsoutput = "<style>#main {  max-width: 780px;margin-left: auto;";
        $questionsoutput .= "margin-right: auto;margin-top: 50px;margin-bottom: 80px; font-family: Arial;} ";
        $questionsoutput .= "h1, h5 {   text-align: center;} ";
        $questionsoutput .= ".answer { border: 1px solid grey; padding: 20px; font-size: 14px; ";
        $questionsoutput .= "line-height: 22px; margin-bottom:30px; margin-top:30px;} ";
        $questionsoutput .= "p {font-size: 14px; line-height: 18px;} </style>";
        $questionsoutput .= "<div id='main'>";
        $questionsoutput .= "<h1>" . $quiz->name . "</h1>";
        $questionsoutput .= "<h5>Time Taken: 0 second</h5><div class='answer'></div></div>";
        $evidenceitem = array(
            "evidence_item" => array(
                "description"   => $quiz->name,
                "string_object" => $questionsoutput,
                "hidden"        => true
            )
        );
        $reqdata = json_encode($evidenceitem);

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        $api = new apirest($mockclient1);
        $evidenceitems = new evidenceitems($api);
        $result = $evidenceitems->post_essay_answers($this->user->id, 1, 1);
        $this->assertEquals($result, null);
    }

    /**
     * Post credential evidence test
     * @covers ::course_duration_evidence
     */
    public function test_course_duration_evidence() {
        global $DB;

        $evidenceitems = new evidenceitems();

        // When there are not enrolments.
        $result = $evidenceitems->course_duration_evidence(1, 1, 1);
        $this->assertEquals($result, null);

        // When there is enrolment with timestart 0.
        $enrolid = $this->create_enrolment(1);
        $this->create_user_enrolment($enrolid, 2, 0);

        $result = $evidenceitems->course_duration_evidence(2, 1, 1);
        $this->assertEquals($result, null);

        // When there is enrolment with completed time > enrol start.
        $enrolid = $this->create_enrolment(1);
        $this->create_user_enrolment($enrolid, $this->user->id, strtotime('2022-04-19'));

        $result = $evidenceitems->course_duration_evidence($this->user->id, 1, 1, strtotime('2022-04-17'));
        $this->assertEquals($result, null);

        // When there is enrolment with completed time < enrol start.
        $user = $this->getDataGenerator()->create_user();
        $enrolid = $this->create_enrolment(1);
        $this->create_user_enrolment($enrolid, $user->id, strtotime('2022-04-15'));

        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/create_success.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';

        $stringobject = array(
            "start_date"       => "2022-04-15",
            "end_date"         => "2022-04-17",
            "duration_in_days" => 2
        );
        $evidenceitem = array(
            "evidence_item" => array(
                "description"   => 'Completed in 2 days',
                "category"      => 'course_duration',
                "string_object" => json_encode($stringobject),
                "hidden"        => true
            )
        );
        $reqdata = json_encode($evidenceitem);

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        $api = new apirest($mockclient1);
        $evidenceitems = new evidenceitems($api);

        $result = $evidenceitems->course_duration_evidence($user->id, 1, 1, strtotime('2022-04-17'));
        $this->assertEquals($result, null);
    }

    /**
     * Create enrolment test
     *
     * @param int $courseid
     */
    private function create_enrolment($courseid) {
        global $DB;
        $data = array("courseid" => $courseid);
        return $DB->insert_record('enrol', $data);
    }

    /**
     * Create user enrolment test
     *
     * @param int $enrolid
     * @param int $userid
     * @param date $timestart
     */
    private function create_user_enrolment($enrolid, $userid, $timestart) {
        global $DB;
        $data = array("enrolid" => $enrolid, "userid" => $userid, "modifierid" => $userid, "timestart" => $timestart);
        return $DB->insert_record('user_enrolments', $data);
    }

    /**
     * Create quiz module test
     *
     * @param int $courseid
     * @param int $userid
     */
    private function create_quiz_module($courseid, $userid = null) {
        $quiz = array("course" => $courseid, "grade" => 10);
        if ($userid) {
            $quiz["userid"] = $userid;
        }
        return $this->getDataGenerator()->create_module('quiz', $quiz);
    }

    /**
     * Create question attempt
     *
     * @param int $quizid
     * @param int $userid
     * @param int $questionusageid
     */
    private function create_quiz_attempt($quizid, $userid, $questionusageid) {
        global $DB;
        $data = array("quiz"            => $quizid,
                      "userid"          => $userid,
                      "attempt"         => 1,
                      "uniqueid"        => $questionusageid,
                      "layout"          => "layout",
                      "state"           => "finished");
        return $DB->insert_record('quiz_attempts', $data);
    }

    /**
     * Create question attempt
     *
     * @param int $quizid
     * @param int $userid
     * @param int $questionusageid
     * @param int $questionid
     */
    private function create_question_attempt($quizid, $userid, $questionusageid, $questionid) {
        global $DB;
        $data = array("quiz"            => $quizid,
                      "userid"          => $userid,
                      "slot"            => 1,
                      "questionusageid" => $questionusageid,
                      "questionid"      => $questionid,
                      "maxmark"         => 10,
                      "behaviour"       => "manualgraded",
                      "minfraction"     => 1,
                      "maxfraction"     => 1,
                      "timemodified"    => time());
        $DB->insert_record('question_attempts', $data);
    }

    /**
     * Create question usage
     */
    private function create_question_usage() {
        global $DB;
        $data = array("id" => 1, "contextid" => 1);
        return $DB->insert_record('question_usages', $data);
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
        return $DB->insert_record('quiz_grades', $quizgrade);
    }
}
