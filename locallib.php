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

/**
 * Certificate module core interaction API
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use mod_accredible\apirest\apirest;
use mod_accredible\Html2Text\Html2Text;
use mod_accredible\local\credentials;
use mod_accredible\local\evidenceitems;
use mod_accredible\local\users;

/**
 * Checks if a user has earned a specific credential according to the activity settings
 * @param stdObject $record An Accredible activity record
 * @param array $user
 * @return bool
 */
function accredible_check_if_cert_earned($record, $user) {
    global $DB;

    $earned = false;

    // Check for the existence of an activity instance and an auto-issue rule.
    if ( $record and ($record->finalquiz or $record->completionactivities) ) {

        if ($record->finalquiz) {
            $quiz = $DB->get_record('quiz', array('id' => $record->finalquiz), '*', MUST_EXIST);
            // Create that credential if it doesn't exist.
            $usersgrade = min( ( quiz_get_best_grade($quiz, $user['id']) / $quiz->grade ) * 100, 100);
            $gradeishighenough = ($usersgrade >= $record->passinggrade);

            // Check for pass.
            if ($gradeishighenough) {
                // Student earned certificate through final quiz.
                $earned = true;
            }
        }

        $completionactivities = unserialize_completion_array($record->completionactivities);

        if (!empty($quiz)) {
            // If this quiz is in the completion activities.
            if ( isset($completionactivities[$quiz->id]) ) {
                $completionactivities[$quiz->id] = true;
                $quizattempts = $DB->get_records('quiz_attempts', array('userid' => $user['id'], 'state' => 'finished'));
                foreach ($quizattempts as $quizattempt) {
                    // If this quiz was already attempted, then we shouldn't be issuing a certificate.
                    if ( $quizattempt->quiz == $quiz->id && $quizattempt->attempt > 1 ) {
                        return null;
                    }
                    // Otherwise, set this quiz as completed.
                    if ( isset($completionactivities[$quizattempt->quiz]) ) {
                        $completionactivities[$quizattempt->quiz] = true;
                    }
                }

                // But was this the last required activity that was completed?
                $coursecomplete = true;
                foreach ($completionactivities as $iscomplete) {
                    if (!$iscomplete) {
                        $coursecomplete = false;
                    }
                }
                // If it was the final activity.
                if ($coursecomplete) {
                    // Student earned certificate by completing completion activities.
                    $earned = true;
                }
            }
        }
    }
    return $earned;
}

/**
 * Get the SSO link for a recipient
 * @param int $groupid
 * @param string $email
 */
function accredible_get_recipient_sso_linik($groupid, $email) {
    global $CFG;

    $apirest = new apirest();

    try {
        $response = $apirest->recipient_sso_link(null, null, $email, null, $groupid, null);

        return $response->link;

    } catch (Exception $e) {
        return null;
    }
}

// Old below here.

/**
 * Issue a certificate
 *
 * @param int $userid
 * @param int $certificateid
 * @param string $name
 * @param string $email
 * @param string $grade
 * @param string $quizname
 * @param date|null $completedtimestamp
 * @param array $customattributes
 */
function accredible_issue_default_certificate($userid, $certificateid, $name, $email, $grade,
    $quizname, $completedtimestamp = null, $customattributes = null) {
    global $DB, $CFG;

    if (!isset($completedtimestamp)) {
        $completedtimestamp = time();
    }
    $issuedon = date('Y-m-d', (int) $completedtimestamp);

    // Issue certs.
    $accrediblecertificate = $DB->get_record('accredible', array('id' => $certificateid));

    $courseurl = new moodle_url('/course/view.php', array('id' => $accrediblecertificate->course));
    $courselink = $courseurl->__toString();

    $restapi = new apirest();
    $credential = $restapi->create_credential_legacy($name, $email, $accrediblecertificate->achievementid, $issuedon, null,
        $accrediblecertificate->certificatename, $accrediblecertificate->description, $courselink, $customattributes);

    // Evidence item posts.
    $credentialid = $credential->credential->id;
    if ($grade) {
        if ($grade < 50) {
            $hidden = true;
        } else {
            $hidden = false;
        }

        $response = $restapi->create_evidence_item_grade($grade, $quizname, $credentialid, $hidden);
    }

    $evidenceitem = new evidenceitem();

    if ($transcript = accredible_get_transcript($accrediblecertificate->course, $userid, $accrediblecertificate->finalquiz)) {
        $evidenceitem->post_evidence($credentialid, $transcript, false);
    }

    $evidenceitem->post_essay_answers($userid, $accrediblecertificate->course, $credentialid);
    $evidenceitem->course_duration_evidence($userid, $accrediblecertificate->course, $credentialid, $completedtimestamp);

    return json_decode($result);
}

/**
 * Log message when certificate is created.
 *
 * @param int $certificateid ID of the certificate.
 * @param int $userid ID of the user.
 * @param int $courseid ID of the course.
 * @param int $cmid ID of the couse module.
 */
function accredible_log_creation($certificateid, $userid, $courseid, $cmid) {
    global $DB;

    // Get context.
    $accrediblemod = $DB->get_record('modules', array('name' => 'accredible'), '*', MUST_EXIST);
    if ($cmid) {
        $cm = $DB->get_record('course_modules', array('id' => (int) $cmid), '*');
    } else { // This is an activity add, so we have to use $courseid.
        $coursemodules = $DB->get_records('course_modules', array('course' => $courseid, 'module' => $accrediblemod->id));
        $cm = end($coursemodules);
    }
    $context = context_module::instance($cm->id);

    return \mod_accredible\event\certificate_created::create(array(
        'objectid' => $certificateid,
        'context' => $context,
        'relateduserid' => $userid
    ));
}

/**
 * Quiz submission handler (checks for a completed course)
 *
 * @param core/event $event quiz mod attempt_submitted event
 */
function accredible_quiz_submission_handler($event) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/quiz/lib.php');

    $api = new apirest();
    $localcredentials = new credentials();
    $usersclient = new users();

    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

    $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);
    $user = $DB->get_record('user', array('id' => $event->relateduserid));
    if ($accrediblecertificaterecords = $DB->get_records('accredible', array('course' => $event->courseid))) {
        foreach ($accrediblecertificaterecords as $record) {
            // Check for the existence of an activity instance and an auto-issue rule.
            if ( $record and ($record->finalquiz or $record->completionactivities) ) {
                // Load user grade to attach in the credential.
                $gradeattributes = $usersclient->get_user_grades($record, $user->id);
                $customattributes = $usersclient->load_user_grade_as_custom_attributes($record, $gradeattributes, $userid);

                // Check if we have a group mapping - if not use the old logic.
                if ($record->groupid) {
                    // Check which quiz is used as the deciding factor in this course.
                    if ($quiz->id == $record->finalquiz) {
                        // Check for an existing certificate.
                        $existingcertificate = $localcredentials->check_for_existing_credential($record->groupid, $user->email);

                        // Create that credential if it doesn't exist.
                        if (!$existingcertificate) {
                            $usersgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                            $gradeishighenough = ($usersgrade >= $record->passinggrade);

                            // Check for pass.
                            if ($gradeishighenough) {
                                // Issue a ceritificate.
                                $localcredentials->create_credential($user, $record->groupid, null, $customattributes);
                            }
                        } else {
                            // Check the existing grade to see if this one is higher and update the credential if so.
                            $credential = $api->get_credential($existingcertificate->id)->credential;
                            foreach ($credential->evidence_items as $evidenceitem) {
                                if ($evidenceitem->type == "grade") {
                                    $highestgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                                    $apigrade = intval($evidenceitem->string_object->grade);
                                    if ($apigrade < $highestgrade) {
                                        $api->update_evidence_item_grade($existingcertificate->id,
                                            $evidenceitem->id, $highestgrade);
                                    }
                                }
                            }
                        }
                    }

                    $completionactivities = unserialize_completion_array($record->completionactivities);
                    // If this quiz is in the completion activities.
                    if ( isset($completionactivities[$quiz->id]) ) {
                        $completionactivities[$quiz->id] = true;
                        $quizattempts = $DB->get_records('quiz_attempts', array('userid' => $user->id, 'state' => 'finished'));
                        foreach ($quizattempts as $quizattempt) {
                            // If this quiz was already attempted, then we shouldn't be issuing a certificate.
                            if ( $quizattempt->quiz == $quiz->id && $quizattempt->attempt > 1 ) {
                                return null;
                            }
                            // Otherwise, set this quiz as completed.
                            if ( isset($completionactivities[$quizattempt->quiz]) ) {
                                $completionactivities[$quizattempt->quiz] = true;
                            }
                        }

                        // But was this the last required activity that was completed?
                        $coursecomplete = true;
                        foreach ($completionactivities as $iscomplete) {
                            if (!$iscomplete) {
                                $coursecomplete = false;
                            }
                        }
                        // If it was the final activity.
                        if ($coursecomplete) {
                            $existingcertificate = $localcredentials->check_for_existing_credential($record->groupid, $user->email);
                            // Make sure there isn't already a certificate.
                            if (!$existingcertificate) {
                                // Issue a ceritificate.
                                $localcredentials->create_credential($user, $record->groupid, null, $customattributes);
                            }
                        }
                    }

                } else {
                    // Check which quiz is used as the deciding factor in this course.
                    if ($quiz->id == $record->finalquiz) {
                        $existingcertificate = $localcredentials->check_for_existing_certificate (
                            $record->achievementid, $user
                        );

                        // Check for an existing certificate.
                        if (!$existingcertificate) {
                            $usersgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                            $gradeishighenough = ($usersgrade >= $record->passinggrade);

                            // Check for pass.
                            if ($gradeishighenough) {
                                // Issue a ceritificate.
                                $apiresponse = accredible_issue_default_certificate( $user->id,
                                    $record->id, fullname($user), $user->email, $usersgrade, $quiz->name, null, $customattributes);
                                $certificateevent = \mod_accredible\event\certificate_created::create(array(
                                  'objectid' => $apiresponse->credential->id,
                                  'context' => context_module::instance($event->contextinstanceid),
                                  'relateduserid' => $event->relateduserid
                                ));
                                $certificateevent->trigger();
                            }
                        } else {
                            // Check the existing grade to see if this one is higher.
                            $credential = $api->get_credential($existingcertificate->id)->credential;
                            foreach ($credential->evidence_items as $evidenceitem) {
                                if ($evidenceitem->type == "grade") {
                                    $highestgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                                    $apigrade = intval($evidenceitem->string_object->grade);
                                    if ($apigrade < $highestgrade) {
                                        $api->update_evidence_item_grade($existingcertificate->id,
                                            $evidenceitem->id, $highestgrade);
                                    }
                                }
                            }
                        }
                    }

                    $completionactivities = unserialize_completion_array($record->completionactivities);
                    // If this quiz is in the completion activities.
                    if ( isset($completionactivities[$quiz->id]) ) {
                        $completionactivities[$quiz->id] = true;
                        $quizattempts = $DB->get_records('quiz_attempts', array('userid' => $user->id, 'state' => 'finished'));
                        foreach ($quizattempts as $quizattempt) {
                            // If this quiz was already attempted, then we shouldn't be issuing a certificate.
                            if ( $quizattempt->quiz == $quiz->id && $quizattempt->attempt > 1 ) {
                                return null;
                            }
                            // Otherwise, set this quiz as completed.
                            if ( isset($completionactivities[$quizattempt->quiz]) ) {
                                $completionactivities[$quizattempt->quiz] = true;
                            }
                        }

                        // But was this the last required activity that was completed?
                        $coursecomplete = true;
                        foreach ($completionactivities as $iscomplete) {
                            if (!$iscomplete) {
                                $coursecomplete = false;
                            }
                        }
                        // If it was the final activity.
                        if ($coursecomplete) {
                            $existingcertificate = $localcredentials->check_for_existing_certificate (
                                $record->achievementid, $user
                            );
                            // Make sure there isn't already a certificate.
                            if (!$existingcertificate) {
                                // And issue a ceritificate.
                                $apiresponse = accredible_issue_default_certificate( $user->id,
                                    $record->id, fullname($user), $user->email, null, null, null, $customattributes);
                                $certificateevent = \mod_accredible\event\certificate_created::create(array(
                                  'objectid' => $apiresponse->credential->id,
                                  'context' => context_module::instance($event->contextinstanceid),
                                  'relateduserid' => $event->relateduserid
                                ));
                                $certificateevent->trigger();
                            }
                        }
                    }
                }

            }
        }
    }
}


/**
 * Course completion handler
 *
 * @param core/event $event
 */
function accredible_course_completed_handler($event) {

    global $DB, $CFG;

    $localcredentials = new credentials();
    $usersclient = new users();

    $user = $DB->get_record('user', array('id' => $event->relateduserid));

    // Check we have a course record.
    if ($accrediblecertificaterecords = $DB->get_records('accredible', array('course' => $event->courseid))) {
        foreach ($accrediblecertificaterecords as $record) {
            // Check for the existence of an activity instance and an auto-issue rule.
            if ( $record and ($record->completionactivities && $record->completionactivities != 0) ) {
                // Load user grade to attach in the credential.
                $gradeattributes = $usersclient->get_user_grades($record, $user->id);
                $customattributes = $usersclient->load_user_grade_as_custom_attributes($record, $gradeattributes, $user->id);

                // Check if we have a group mapping - if not use the old logic.
                if ($record->groupid) {
                    // Create the credential.
                    $localcredentials->create_credential($user, $record->groupid, null, $customattributes);

                } else {
                    $apiresponse = accredible_issue_default_certificate( $user->id, $record->id,
                        fullname($user), $user->email, null, null, null, $customattributes);
                    $certificateevent = \mod_accredible\event\certificate_created::create(array(
                      'objectid' => $apiresponse->credential->id,
                      'context' => context_module::instance($event->contextinstanceid),
                      'relateduserid' => $event->relateduserid
                    ));
                    $certificateevent->trigger();
                }

            }
        }
    }
}


/**
 * Make a transcript if user has completed 2/3 of the quizes.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $finalquizid
 */
function accredible_get_transcript($courseid, $userid, $finalquizid) {
    global $DB, $CFG;

    $totalitems = 0;
    $totalscore = 0;
    $itemscompleted = 0;
    $transcriptitems = array();
    $quizes = $DB->get_records_select('quiz', 'course = :course_id', array('course_id' => $courseid), 'id ASC');

    // Grab the grades for all quizes.
    foreach ($quizes as $quiz) {
        if ($quiz->id !== $finalquizid) {
            $highestgrade = quiz_get_best_grade($quiz, $userid);
            if ($highestgrade) {
                $itemscompleted += 1;
                array_push($transcriptitems, array(
                    'category' => $quiz->name,
                    'percent' => min( ( $highestgrade / $quiz->grade ) * 100, 100 )
                ));
                $totalscore += min( ( $highestgrade / $quiz->grade ) * 100, 100);
            }
            $totalitems += 1;
        }
    }

    // If they've completed over 2/3 of items
    // and have a passing average, make a transcript.
    if ( $totalitems !== 0 && $itemscompleted !== 0 && $itemscompleted / $totalitems >= 0.66 &&
        $totalscore / $itemscompleted > 50 ) {
        return array(
                'description' => 'Course Transcript',
                'string_object' => json_encode($transcriptitems),
                'category' => 'transcript',
                'custom' => true,
                'hidden' => true
            );
    } else {
        return false;
    }
}

/**
 * Serialize completion array
 *
 * @param Array $completionarray
 */
function serialize_completion_array($completionarray) {
    return base64_encode(serialize( (array)$completionarray ));
}

/**
 * Unserialize completion array
 *
 * @param stdObject $completionobject
 */
function unserialize_completion_array($completionobject) {
    return (array)unserialize(base64_decode( $completionobject ));
}

/**
 * Get a timestamp for when a student completed a course. This is
 * used when manually issuing certs to get a proper issue date and
 * for the course duration item. Currently checking for the date of
 * the highest quiz attempt for the final quiz specified for that
 * accredible activity.
 *
 * @param stdObject $accrediblerecord
 * @param stdObject $user
 */
function accredible_manual_issue_completion_timestamp($accrediblerecord, $user) {
    global $DB;

    $completedtimestamp = false;

    if ($accrediblerecord->finalquiz) {
        // If there is a finalquiz set, that governs when the course is complete.

        $quiz = $DB->get_record('quiz', array('id' => $accrediblerecord->finalquiz), '*', MUST_EXIST);
        $totalrawscore = $quiz->sumgrades;
        $highestattempt = null;

        $quizattempts = $DB->get_records('quiz_attempts', array('userid' => $user->id,
            'state' => 'finished', 'quiz' => $accrediblerecord->finalquiz));
        foreach ($quizattempts as $quizattempt) {
            if (!isset($highestattempt)) {
                // First attempt in the loop, so currently the highest.
                $highestattempt = $quizattempt;
                continue;
            }

            if ($quizattempt->sumgrades >= $highestattempt->sumgrades) {
                // Compare raw sumgrades from attempt. It seems that moodle
                // doesn't allow the amount that questions are worth in a quiz
                // to change so this should be ok - the scale should be constant
                // across attempts.
                $highestattempt = $quizattempt;
            }
        }

        if (isset($highestattempt)) {
            // At least one attempt was found.
            $attemptrawscore = $highestattempt->sumgrades;
            $grade = ($attemptrawscore / $totalrawscore) * 100;
            // Check if the grade is passing, and if so set completion time to the attempt timefinish.
            if ($grade >= $accrediblerecord->passinggrade) {
                $completedtimestamp = $highestattempt->timefinish;
            }
        }

    }

    // TODO: When is the completion if there are completion activities set?

    // Set timestamp to now if no good timestamp was found.
    if ($completedtimestamp === false) {
        $completedtimestamp = time();
    }

    return (int) $completedtimestamp;
}

/**
 * Return 's' when number is bigger than 1
 *
 * @param int $number
 * @return string
 */
function number_ending ($number) {
    return ($number > 1) ? 's' : '';
}

/**
 * Convert number of seconds in a string
 *
 * @param int $seconds
 * @return string
 */
function seconds_to_str ($seconds) {
    $hours = floor(($seconds %= 86400) / 3600);
    if ($hours) {
        return $hours . ' hour' . number_ending($hours);
    }
    $minutes = floor(($seconds %= 3600) / 60);
    if ($minutes) {
        return $minutes . ' minute' . number_ending($minutes);
    }
    return $seconds . ' second' . number_ending($seconds);
}
