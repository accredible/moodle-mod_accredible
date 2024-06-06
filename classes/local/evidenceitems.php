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
 * Local functions related to credential evidence items.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evidenceitems {
    /**
     * HTTP request apirest.
     * @var apirest
     */
    private $apirest;

    /**
     * Constructor method
     *
     * @param stdObject $apirest a mock apirest for testing.
     */
    public function __construct($apirest = null) {
        // A mock apirest is passed when unit testing.
        if ($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apirest();
        }
    }

    /**
     * Create evidence item
     *
     * @param int $credentialid
     * @param stdObject $evidenceitem
     * @param bool $throwerror
     */
    public function post_evidence($credentialid, $evidenceitem, $throwerror = false) {
        $this->apirest->create_evidence_item(['evidence_item' => $evidenceitem], $credentialid, $throwerror);
    }

    /**
     * Post answers from essay
     *
     * @param int $userid
     * @param int $courseid
     * @param int $credentialid
     */
    public function post_essay_answers($userid, $courseid, $credentialid) {
        global $DB;

        // Grab the course quizes.
        if ($quizes = $DB->get_records_select('quiz', 'course = :course_id', ['course_id' => $courseid]) ) {
            foreach ($quizes as $quiz) {
                $evidenceitem = ['description' => $quiz->name];
                // Grab quiz attempts.
                $quizattempt = $DB->get_records(
                    'quiz_attempts',
                    [
                        'quiz' => $quiz->id,
                        'userid' => $userid,
                    ],
                    '-attempt',
                    '*',
                    0,
                    1
                );

                if ($quizattempt) {
                    $sql = "SELECT
                                    qa.id,
                                    quiza.quiz,
                                    quiza.id AS quizattemptid,
                                    quiza.timestart,
                                    quiza.timefinish,
                                    qa.slot,
                                    qa.behaviour,
                                    qa.questionsummary AS question,
                                    qa.responsesummary AS answer

                            FROM {quiz_attempts} quiza
                            JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                            JOIN {question_attempts} qa ON qa.questionusageid = qu.id

                            WHERE quiza.id = ? AND qa.behaviour = ?

                            ORDER BY quiza.userid, quiza.attempt, qa.slot";

                    if ( $questions = $DB->get_records_sql($sql, [reset($quizattempt)->id, 'manualgraded']) ) {
                        $questionsoutput = "<style>#main {  max-width: 780px;margin-left: auto;";
                        $questionsoutput .= "margin-right: auto;margin-top: 50px;margin-bottom: 80px; font-family: Arial;} ";
                        $questionsoutput .= "h1, h5 {   text-align: center;} ";
                        $questionsoutput .= ".answer { border: 1px solid grey; padding: 20px; font-size: 14px; ";
                        $questionsoutput .= "line-height: 22px; margin-bottom:30px; margin-top:30px;} ";
                        $questionsoutput .= "p {font-size: 14px; line-height: 18px;} </style>";
                        $questionsoutput .= "<div id='main'>";
                        $questionsoutput .= "<h1>" . $quiz->name . "</h1>";
                        $questionsoutput .= "<h5>Time Taken: ".
                            seconds_to_str( current($questions)->timefinish - current($questions)->timestart ) ."</h5>";

                        foreach ($questions as $questionattempt) {
                            $questionsoutput .= $questionattempt->question;
                            $questionsoutput .= "<div class='answer'>".$questionattempt->answer."</div>";
                        }

                        $questionsoutput .= "</div>";

                        $evidenceitem['string_object'] = $questionsoutput;
                        $evidenceitem['hidden'] = true;

                        // Post the evidence.
                        $this->post_evidence($credentialid, $evidenceitem, false);
                    }
                }
            }
        }
    }

    /**
     * Create evidence course duration
     *
     * @param int $userid
     * @param int $courseid
     * @param int $credentialid
     * @param int|null $completedtimestamp
     */
    public function course_duration_evidence($userid, $courseid, $credentialid, $completedtimestamp = null) {
        global $DB;

        $sql = "SELECT enrol.id, ue.timestart
                        FROM {enrol} enrol, {user_enrolments} ue
                        WHERE enrol.id = ue.enrolid AND ue.userid = ? AND enrol.courseid = ?";
        $enrolment = $DB->get_record_sql($sql, [$userid, $courseid]);

        if ($enrolment) {
            $enrolmenttimestamp = $enrolment->timestart;

            if (!isset($completedtimestamp)) {
                $completedtimestamp = time();
            }

            if ($enrolmenttimestamp && $enrolmenttimestamp != 0 &&
                ($enrolmenttimestamp < $completedtimestamp)) {
                $this->apirest->create_evidence_item_duration($enrolmenttimestamp, $completedtimestamp, $credentialid, true);
            }
        }
    }
}
