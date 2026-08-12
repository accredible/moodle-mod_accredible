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
use mod_accredible\local\accredible;

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
    if ($record && ($record->finalquiz || $record->completionactivities)) {
        if ($record->finalquiz) {
            $quiz = $DB->get_record('quiz', ['id' => $record->finalquiz], '*', MUST_EXIST);
            // Create that credential if it doesn't exist.
            $usersgrade = min(( quiz_get_best_grade($quiz, $user['id']) / $quiz->grade ) * 100, 100);
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
            if (isset($completionactivities[$quiz->id])) {
                $completionactivities[$quiz->id] = true;
                $quizattempts = $DB->get_records('quiz_attempts', ['userid' => $user['id'], 'state' => 'finished']);
                foreach ($quizattempts as $quizattempt) {
                    // If this quiz was already attempted, then we shouldn't be issuing a certificate.
                    if ($quizattempt->quiz == $quiz->id && $quizattempt->attempt > 1) {
                        return null;
                    }
                    // Otherwise, set this quiz as completed.
                    if (isset($completionactivities[$quizattempt->quiz])) {
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
 * Evaluate completion-activities eligibility for a quiz submission.
 * Fires credential_issue_skipped at each negative decision point and returns a
 * result array the caller can act on without re-implementing the eligibility rules.
 *
 * @param stdClass $user
 * @param stdClass $record accredible activity record
 * @param stdClass $quiz submitted quiz
 * @param \context $ctx context for events
 * @return array ['relevant' => false] | ['eligible' => false, 'reason' => string] | ['eligible' => true]
 */
function accredible_evaluate_completion_eligibility($user, $record, $quiz, $ctx) {
    global $DB;

    if (empty($record->completionactivities)) {
        return ['relevant' => false];
    }

    // Detect a corrupt record: non-empty value that fails to unserialize.
    $unserialized = @unserialize(base64_decode($record->completionactivities));
    if ($unserialized === false) {
        \mod_accredible\event\credential_issue_skipped::create([
            'context' => $ctx,
            'relateduserid' => $user->id,
            'other' => ['reason' => 'malformed_completion_record', 'groupid' => $record->groupid ?? null],
        ])->trigger();
        return ['eligible' => false, 'reason' => 'malformed_completion_record'];
    }

    $completionactivities = (array)$unserialized;

    // This quiz is not tracked by this record — not relevant to its issuance rule.
    if (!isset($completionactivities[$quiz->id])) {
        return ['relevant' => false];
    }

    $completionactivities[$quiz->id] = true;
    $quizattempts = $DB->get_records('quiz_attempts', ['userid' => $user->id, 'state' => 'finished']);
    foreach ($quizattempts as $quizattempt) {
        if ($quizattempt->quiz == $quiz->id && $quizattempt->attempt > 1) {
            \mod_accredible\event\credential_issue_skipped::create([
                'context' => $ctx,
                'relateduserid' => $user->id,
                'other' => ['reason' => 'repeat_attempt', 'groupid' => $record->groupid ?? null],
            ])->trigger();
            return ['eligible' => false, 'reason' => 'repeat_attempt'];
        }
        if (isset($completionactivities[$quizattempt->quiz])) {
            $completionactivities[$quizattempt->quiz] = true;
        }
    }

    foreach ($completionactivities as $iscomplete) {
        if (!$iscomplete) {
            \mod_accredible\event\credential_issue_skipped::create([
                'context' => $ctx,
                'relateduserid' => $user->id,
                'other' => ['reason' => 'completion_not_met', 'groupid' => $record->groupid ?? null],
            ])->trigger();
            return ['eligible' => false, 'reason' => 'completion_not_met'];
        }
    }

    return ['eligible' => true];
}

/**
 * Get the SSO link for a recipient
 * @param int $groupid
 * @param string $email
 * @param string|null $brand the activity's brand; required, it selects the account
 */
function accredible_get_recipient_sso_link($groupid, $email, $brand = null) {
    global $CFG, $DB;

    $apirest = apirest::for_brand($brand);

    // Resolve the recipient so the logged api_request_failed event has a related user.
    $recipient = $DB->get_record('user', ['email' => $email], 'id', IGNORE_MULTIPLE);
    $userid = $recipient ? $recipient->id : null;

    try {
        $response = $apirest->recipient_sso_link(null, null, $email, null, $groupid, null);

        // The detect_error() call fires api_request_failed when the call failed; recipient_sso_link
        // returns null or an error body without throwing, so check explicitly.
        if ($apirest->detect_error($response, $userid) !== null || empty($response->link)) {
            return null;
        }
        return $response->link;
    } catch (\Throwable $e) {
        \mod_accredible\event\api_request_failed::create([
            'context' => \context_system::instance(),
            'relateduserid' => $userid ?: null,
            'other' => [
                'endpoint' => 'sso/generate_link',
                'http_status' => null,
                'error' => $e->getMessage(),
            ],
        ])->trigger();
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
function accredible_issue_default_certificate(
    $userid,
    $certificateid,
    $name,
    $email,
    $grade,
    $quizname,
    $completedtimestamp = null,
    $customattributes = null
) {
    global $DB, $CFG;

    if (!isset($completedtimestamp)) {
        $completedtimestamp = time();
    }
    $issuedon = date('Y-m-d', (int) $completedtimestamp);

    // Issue certs.
    $accrediblecertificate = $DB->get_record('accredible', ['id' => $certificateid]);

    $courseurl = new moodle_url('/course/view.php', ['id' => $accrediblecertificate->course]);
    $courselink = $courseurl->__toString();

    $restapi = apirest::for_brand($accrediblecertificate->brand ?? null);
    $credential = $restapi->create_credential_legacy(
        $name,
        $email,
        $accrediblecertificate->achievementid,
        $issuedon,
        null,
        $accrediblecertificate->certificatename,
        $accrediblecertificate->description,
        $courselink,
        $customattributes
    );

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

    return $credential;
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
    $accrediblemod = $DB->get_record('modules', ['name' => 'accredible'], '*', MUST_EXIST);
    if ($cmid) {
        $cm = $DB->get_record('course_modules', ['id' => (int) $cmid], '*');
    } else { // This is an activity add, so we have to use $courseid.
        $coursemodules = $DB->get_records('course_modules', ['course' => $courseid, 'module' => $accrediblemod->id]);
        $cm = end($coursemodules);
    }
    $context = context_module::instance($cm->id);

    return \mod_accredible\event\certificate_created::create([
        'objectid' => $certificateid,
        'context' => $context,
        'relateduserid' => $userid,
    ]);
}

/**
 * Quiz submission handler (checks for a completed course)
 *
 * @param core/event $event quiz mod attempt_submitted event
 * @param credentials|null $localcredentials injectable credentials client for testing
 */
function accredible_quiz_submission_handler($event, $localcredentials = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/quiz/lib.php');

    $ctx = context_module::instance($event->contextinstanceid);
    $accredible = new accredible();

    // A course can hold several activities, each with its own brand, so the API
    // clients are built per record inside the loop. An injected client still
    // wins, which is what the tests rely on.
    $injectedcredentials = $localcredentials;

    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

    $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);
    $user = $DB->get_record('user', ['id' => $event->relateduserid]);
    if ($accrediblecertificaterecords = $DB->get_records('accredible', ['course' => $event->courseid])) {
        foreach ($accrediblecertificaterecords as $record) {
            $api = apirest::for_brand($record->brand ?? null);
            $localcredentials = $injectedcredentials ?? new credentials($api);
            $usersclient = new users($api);

            try {
                if (!$record->finalquiz && !$record->completionactivities) {
                    \mod_accredible\event\credential_issue_skipped::create([
                        'context' => $ctx,
                        'relateduserid' => $user->id,
                        'other' => ['reason' => 'nothing_to_check', 'groupid' => $record->groupid ?? null],
                    ])->trigger();
                    continue;
                }

                // Load user grade to attach in the credential.
                $gradeattributes = $usersclient->get_user_grades($record, $user->id);
                // Later: refactor the attribute mapping generation into a class function.
                $gradeattributemapping = $usersclient->load_user_grade_as_custom_attributes($record, $gradeattributes, $user->id);
                $additionalattributemapping = $accredible->load_credential_custom_attributes($record, $user->id);
                $customattributes = array_merge($gradeattributemapping, $additionalattributemapping);

                // Check if we have a group mapping - if not use the old logic.
                if ($record->groupid) {
                    $existingcertificate = $localcredentials->check_for_existing_credential($record->groupid, $user->email);

                    if ($existingcertificate) {
                        // Already issued: stay silent (Decision 1), but keep the grade evidence current.
                        if ($quiz->id == $record->finalquiz) {
                            $credential = $api->get_credential($existingcertificate->id)->credential;
                            foreach ($credential->evidence_items as $evidenceitem) {
                                if ($evidenceitem->type == "grade") {
                                    $highestgrade = min(( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                                    $apigrade = intval($evidenceitem->string_object->grade);
                                    if ($apigrade < $highestgrade) {
                                        $api->update_evidence_item_grade(
                                            $existingcertificate->id,
                                            $evidenceitem->id,
                                            $highestgrade
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        $gradetoolow = false;

                        // Final-quiz grade rule. Defer the skip so the completion rule can be the
                        // single terminal decision when this quiz belongs to both rules.
                        if ($quiz->id == $record->finalquiz) {
                            $usersgrade = min(( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                            if ($usersgrade >= $record->passinggrade) {
                                $localcredentials->create_credential($user, $record->groupid, null, $customattributes, $ctx);
                                $existingcertificate = true;
                            } else {
                                $gradetoolow = true;
                            }
                        }

                        // Completion-activities rule (skipped once the grade rule has issued).
                        if (!$existingcertificate) {
                            $eligibility = accredible_evaluate_completion_eligibility($user, $record, $quiz, $ctx);
                            if (isset($eligibility['eligible']) && $eligibility['eligible']) {
                                $localcredentials->create_credential($user, $record->groupid, null, $customattributes, $ctx);
                            } else if ($gradetoolow && isset($eligibility['relevant'])) {
                                // Grade too low and the completion rule didn't apply to this quiz.
                                \mod_accredible\event\credential_issue_skipped::create([
                                    'context' => $ctx,
                                    'relateduserid' => $user->id,
                                    'other' => ['reason' => 'grade_below_threshold', 'groupid' => $record->groupid],
                                ])->trigger();
                            }
                        }
                    }
                } else {
                    $existingcertificate = $localcredentials->check_for_existing_certificate(
                        $record->achievementid,
                        $user
                    );

                    if ($existingcertificate) {
                        // Already issued: stay silent (Decision 1), but keep the grade evidence current.
                        if ($quiz->id == $record->finalquiz) {
                            $credential = $api->get_credential($existingcertificate->id)->credential;
                            foreach ($credential->evidence_items as $evidenceitem) {
                                if ($evidenceitem->type == "grade") {
                                    $highestgrade = min(( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                                    $apigrade = intval($evidenceitem->string_object->grade);
                                    if ($apigrade < $highestgrade) {
                                        $api->update_evidence_item_grade(
                                            $existingcertificate->id,
                                            $evidenceitem->id,
                                            $highestgrade
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        $gradetoolow = false;

                        // Final-quiz grade rule. Defer the skip so the completion rule can be the
                        // single terminal decision when this quiz belongs to both rules.
                        if ($quiz->id == $record->finalquiz) {
                            $usersgrade = min(( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                            if ($usersgrade >= $record->passinggrade) {
                                // Issue a certificate.
                                $apiresponse = accredible_issue_default_certificate(
                                    $user->id,
                                    $record->id,
                                    fullname($user),
                                    $user->email,
                                    $usersgrade,
                                    $quiz->name,
                                    null,
                                    $customattributes
                                );
                                $certificateevent = \mod_accredible\event\certificate_created::create([
                                  'objectid' => $apiresponse->credential->id,
                                  'context' => $ctx,
                                  'relateduserid' => $event->relateduserid,
                                ]);
                                $certificateevent->trigger();
                                $existingcertificate = true;
                            } else {
                                $gradetoolow = true;
                            }
                        }

                        // Completion-activities rule (skipped once the grade rule has issued).
                        if (!$existingcertificate) {
                            $eligibility = accredible_evaluate_completion_eligibility($user, $record, $quiz, $ctx);
                            if (isset($eligibility['eligible']) && $eligibility['eligible']) {
                                // And issue a certificate.
                                $apiresponse = accredible_issue_default_certificate(
                                    $user->id,
                                    $record->id,
                                    fullname($user),
                                    $user->email,
                                    null,
                                    null,
                                    null,
                                    $customattributes
                                );
                                $certificateevent = \mod_accredible\event\certificate_created::create([
                                  'objectid' => $apiresponse->credential->id,
                                  'context' => $ctx,
                                  'relateduserid' => $event->relateduserid,
                                ]);
                                $certificateevent->trigger();
                            } else if ($gradetoolow && isset($eligibility['relevant'])) {
                                // Grade too low and the completion rule didn't apply to this quiz.
                                \mod_accredible\event\credential_issue_skipped::create([
                                    'context' => $ctx,
                                    'relateduserid' => $user->id,
                                    'other' => ['reason' => 'grade_below_threshold'],
                                ])->trigger();
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \mod_accredible\event\credential_issue_failed::create([
                    'context' => $ctx,
                    'relateduserid' => $user->id,
                    'other' => [
                        'reason' => 'exception',
                        'message' => $e->getMessage(),
                        'class' => get_class($e),
                        'groupid' => $record->groupid ?? null,
                    ],
                ])->trigger();
                continue;
            }
        }
    }
}


/**
 * Course completion handler
 *
 * @param core/event $event
 * @param credentials|null $localcredentials injectable credentials client for testing
 */
function accredible_course_completed_handler($event, $localcredentials = null) {
    global $DB, $CFG;

    $ctx = context_course::instance($event->courseid);
    $accredible = new accredible();

    // One client per record: activities in the same course can target different
    // brands. An injected client still wins, which is what the tests rely on.
    $injectedcredentials = $localcredentials;

    $user = $DB->get_record('user', ['id' => $event->relateduserid]);

    // Check we have a course record.
    if ($accrediblecertificaterecords = $DB->get_records('accredible', ['course' => $event->courseid])) {
        foreach ($accrediblecertificaterecords as $record) {
            $api = apirest::for_brand($record->brand ?? null);
            $localcredentials = $injectedcredentials ?? new credentials($api);
            $usersclient = new users($api);

            try {
                // Check for the existence of an activity instance and an auto-issue rule.
                if ($record && ($record->completionactivities && $record->completionactivities != 0)) {
                    // Load user grade to attach in the credential.
                    $gradeattributes = $usersclient->get_user_grades($record, $user->id);
                    // Later: refactor the attribute mapping generation into a class function.
                    $gradeattributemapping =
                        $usersclient->load_user_grade_as_custom_attributes($record, $gradeattributes, $user->id);
                    $additionalattributemapping = $accredible->load_credential_custom_attributes($record, $user->id);
                    $customattributes = array_merge($gradeattributemapping, $additionalattributemapping);

                    // Check if we have a group mapping - if not use the old logic.
                    if ($record->groupid) {
                        // Prevent double-issue: skip if a credential already exists for this user/group.
                        $existingcertificate = $localcredentials->check_for_existing_credential($record->groupid, $user->email);
                        if (!$existingcertificate) {
                            // Create the credential.
                            $localcredentials->create_credential($user, $record->groupid, null, $customattributes, $ctx);
                        }
                    } else {
                        $apiresponse = accredible_issue_default_certificate(
                            $user->id,
                            $record->id,
                            fullname($user),
                            $user->email,
                            null,
                            null,
                            null,
                            $customattributes
                        );
                        $certificateevent = \mod_accredible\event\certificate_created::create([
                          'objectid' => $apiresponse->credential->id,
                          'context' => $ctx,
                          'relateduserid' => $event->relateduserid,
                        ]);
                        $certificateevent->trigger();
                    }
                }
            } catch (\Throwable $e) {
                \mod_accredible\event\credential_issue_failed::create([
                    'context' => $ctx,
                    'relateduserid' => $user->id,
                    'other' => [
                        'reason' => 'exception',
                        'message' => $e->getMessage(),
                        'class' => get_class($e),
                        'groupid' => $record->groupid ?? null,
                    ],
                ])->trigger();
                continue;
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
    $transcriptitems = [];
    $quizes = $DB->get_records_select('quiz', 'course = :course_id', ['course_id' => $courseid], 'id ASC');

    // Grab the grades for all quizes.
    foreach ($quizes as $quiz) {
        if ($quiz->id !== $finalquizid) {
            $highestgrade = quiz_get_best_grade($quiz, $userid);
            if ($highestgrade) {
                $itemscompleted += 1;
                array_push($transcriptitems, [
                    'category' => $quiz->name,
                    'percent' => min(( $highestgrade / $quiz->grade ) * 100, 100),
                ]);
                $totalscore += min(( $highestgrade / $quiz->grade ) * 100, 100);
            }
            $totalitems += 1;
        }
    }

    // If they've completed over 2/3 of items
    // and have a passing average, make a transcript.
    if (
        $totalitems !== 0 && $itemscompleted !== 0 && $itemscompleted / $totalitems >= 0.66 &&
        $totalscore / $itemscompleted > 50
    ) {
        return [
            'description' => 'Course Transcript',
            'string_object' => json_encode($transcriptitems),
            'category' => 'transcript',
            'custom' => true,
            'hidden' => true,
        ];
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
    return base64_encode(serialize((array)$completionarray));
}

/**
 * Unserialize completion array
 *
 * @param stdObject $completionobject
 */
function unserialize_completion_array($completionobject) {
    return is_null($completionobject) ? [] : (array)unserialize(base64_decode($completionobject));
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

        $quiz = $DB->get_record('quiz', ['id' => $accrediblerecord->finalquiz], '*', MUST_EXIST);
        $totalrawscore = $quiz->sumgrades;
        $highestattempt = null;

        $quizattempts = $DB->get_records('quiz_attempts', [
            'userid' => $user->id,
            'state' => 'finished',
            'quiz' => $accrediblerecord->finalquiz,
        ]);
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

    // Later: when is the completion if there are completion activities set?

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
function number_ending($number) {
    return ($number > 1) ? 's' : '';
}

/**
 * Convert number of seconds in a string
 *
 * @param int $seconds
 * @return string
 */
function seconds_to_str($seconds) {
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
