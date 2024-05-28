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
require_once($CFG->dirroot . '/mod/accredible/locallib.php');

use mod_accredible\apirest\apirest;
use mod_accredible\local\credentials;
use mod_accredible\local\groups;
use mod_accredible\local\evidenceitems;
use mod_accredible\local\users;
use mod_accredible\local\accredible;

/**
 * Add certificate instance.
 *
 * @param stdObject $post
 * @return array $certificate new certificate object
 */
function accredible_add_instance($post) {
    global $DB;

    $post->groupid = isset($post->groupid) ? $post->groupid : null;

    $post->instance = isset($post->instance) ? $post->instance : null;

    $localcredentials = new credentials();
    $evidenceitems = new evidenceitems();
    $usersclient = new users();
    $accredible = new accredible();

    $recordid = $accredible->save_record($post);

    // Issue certs.
    if ( isset($post->users) ) {
        $record = $DB->get_record('accredible', ['id' => $recordid], '*', MUST_EXIST);
        // Load grade attributes for users who will get a credential issued if need to be added.
        $userids = array();
        foreach ($post->users as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $userids[] = $userid;
            }
        }
        $gradeattributes = $usersclient->get_user_grades($post, $userids);

        // Checklist array from the form comes in the format:
        // Int userid => boolean issuecertificate.
        foreach ($post->users as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

                // Later: refactor the attribute mapping generation into a class function.
                $gradeattributemapping = $usersclient->load_user_grade_as_custom_attributes($post, $gradeattributes, $userid);
                $additionalattributemapping = $accredible->load_credential_custom_attributes($record, $userid);
                $customattributes = array_merge($gradeattributemapping, $additionalattributemapping);
                $credential = $localcredentials->create_credential($user, $post->groupid, null, $customattributes);

                if ($credential) {
                    // Evidence item posts.
                    $credentialid = $credential->id;
                    if ($post->finalquiz) {
                        $quiz = $DB->get_record('quiz', array('id' => $post->finalquiz), '*', MUST_EXIST);
                        $usersgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                        $gradeevidence = array('string_object' => (string) $usersgrade,
                            'description' => $quiz->name, 'custom' => true, 'category' => 'grade');
                        if ($usersgrade < 50) {
                            $gradeevidence['hidden'] = true;
                        }
                        $evidenceitems->post_evidence($credentialid, $gradeevidence, true);
                    }
                    if ($transcript = accredible_get_transcript($post->course, $userid, $post->finalquiz)) {
                        $evidenceitems->post_evidence($credentialid, $transcript, true);
                    }
                    $evidenceitems->post_essay_answers($userid, $post->course, $credentialid);
                    $evidenceitems->course_duration_evidence($userid, $post->course, $credentialid);
                }
            }
        }
    }

    return $recordid;
}

/**
 * Update certificate instance.
 *
 * @param stdClass $post
 * @return stdClass $certificate updated
 */
function accredible_update_instance($post) {
    // To update your certificate details, go to accredible.com.
    global $DB;

    $localcredentials = new credentials();
    $evidenceitems = new evidenceitems();
    $usersclient = new users();
    $accredible = new accredible();

    // Load grade attributes for users if need to be added in the credential.
    $userids = array();
    if (isset($post->users)) {
        foreach ($post->users as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $userids[] = $userid;
            }
        }
    }
    if (isset($post->unissuedusers)) {
        foreach ($post->unissuedusers as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $userids[] = $userid;
            }
        }
    }

    $gradeattributes = $usersclient->get_user_grades($post, array_unique($userids));

    $existingrecord = $DB->get_record('accredible', array('id' => $post->instance), '*', MUST_EXIST);

    // Issue certs for unissued users.
    if (isset($post->unissuedusers)) {
        // Checklist array from the form comes in the format:
        // Int userid => boolean issuecertificate.
        if ($existingrecord->achievementid) {
            $groupid = $existingrecord->achievementid;
        } else if ($existingrecord->groupid) {
            $groupid = $existingrecord->groupid;
        }
        foreach ($post->unissuedusers as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                $completedtimestamp = accredible_manual_issue_completion_timestamp($existingrecord, $user);
                $completeddate = date('Y-m-d', (int) $completedtimestamp);
                // Later: refactor the attribute mapping generation into a class function.
                $gradeattributemapping = $usersclient->load_user_grade_as_custom_attributes($post, $gradeattributes, $userid);
                $additionalattributemapping = $accredible->load_credential_custom_attributes($existingrecord, $userid);
                $customattributes = array_merge($gradeattributemapping, $additionalattributemapping);
                if ($existingrecord->groupid) {
                    // Create the credential.
                    $credential = $localcredentials->create_credential($user, $groupid, $completeddate, $customattributes);
                    if ($credential) {
                        $credentialid = $credential->id;
                        // Evidence item posts.
                        if ($post->finalquiz) {
                            $quiz = $DB->get_record('quiz', array('id' => $post->finalquiz), '*', MUST_EXIST);
                            $usersgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                            $gradeevidence = array('string_object' => (string) $usersgrade,
                                'description' => $quiz->name, 'custom' => true, 'category' => 'grade');
                            if ($usersgrade < 50) {
                                $gradeevidence['hidden'] = true;
                            }
                            $evidenceitems->post_evidence($credentialid, $gradeevidence, true);
                        }
                        if ($transcript = accredible_get_transcript($post->course, $userid, $post->finalquiz)) {
                            $evidenceitems->post_evidence($credentialid, $transcript, true);
                        }
                        $evidenceitems->post_essay_answers($userid, $post->course, $credentialid);
                        $evidenceitems->course_duration_evidence($userid, $post->course, $credentialid, $completedtimestamp);
                    }
                } else if ($existingrecord->achievementid) {
                    if ($post->finalquiz) {
                        $quiz = $DB->get_record('quiz', array('id' => $post->finalquiz), '*', MUST_EXIST);
                        $grade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                    }
                    // TODO: testing.
                    $result = accredible_issue_default_certificate($user->id,
                        $existingrecord->id, fullname($user), $user->email,
                        $grade, $quiz->name, $completedtimestamp, $customattributes);
                    $credentialid = $result->credential->id;
                }
                // Log the creation.
                $event = accredible_log_creation(
                    $credentialid,
                    $user->id,
                    null,
                    $post->coursemodule
                );
                $event->trigger();
            }
        }
    }

    // Issue certs.
    if ( isset($post->users) ) {
        // Checklist array from the form comes in the format:
        // Int userid => boolean issuecertificate.
        foreach ($post->users as $userid => $issuecertificate) {
            if ($issuecertificate) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                $completedtimestamp = accredible_manual_issue_completion_timestamp($existingrecord, $user);
                $completeddate = date('Y-m-d', (int) $completedtimestamp);
                // Later: refactor the attribute mapping generation into a class function.
                $gradeattributemapping = $usersclient->load_user_grade_as_custom_attributes($post, $gradeattributes, $userid);
                $additionalattributemapping = $accredible->load_credential_custom_attributes($existingrecord, $userid);
                $customattributes = array_merge($gradeattributemapping, $additionalattributemapping);
                if ($existingrecord->achievementid) {

                    $courseurl = new moodle_url('/course/view.php', array('id' => $post->course));
                    $courselink = $courseurl->__toString();

                    $credential = $localcredentials->create_credential_legacy($user, $post->achievementid,
                        $post->certificatename, $post->description, $courselink, $completeddate, $customattributes);
                } else {
                    $credential = $localcredentials->create_credential($user, $post->groupid, $completeddate, $customattributes);
                }

                // Evidence item posts.
                if ($credential) {
                    $credentialid = $credential->id;
                    if ($post->finalquiz) {
                        $quiz = $DB->get_record('quiz', array('id' => $post->finalquiz), '*', MUST_EXIST);
                        $usersgrade = min( ( quiz_get_best_grade($quiz, $user->id) / $quiz->grade ) * 100, 100);
                        $gradeevidence = array('string_object' => (string) $usersgrade,
                            'description' => $quiz->name, 'custom' => true, 'category' => 'grade');
                        if ($usersgrade < 50) {
                            $gradeevidence['hidden'] = true;
                        }
                        $evidenceitems->post_evidence($credentialid, $gradeevidence, true);
                    }
                    if ($transcript = accredible_get_transcript($post->course, $userid, $post->finalquiz)) {
                        $evidenceitems->post_evidence($credentialid, $transcript, true);
                    }
                    $evidenceitems->post_essay_answers($userid, $post->course, $credentialid);
                    $evidenceitems->course_duration_evidence($userid, $post->course, $credentialid, $completedtimestamp);

                    // Log the creation.
                    $event = accredible_log_creation(
                        $credentialid,
                        $userid,
                        null,
                        $post->coursemodule
                    );
                    $event->trigger();
                }
            }
        }
    }

    // Set completion activitied to 0 if unchecked.
    if (!property_exists($post, 'completionactivities')) {
        $post->completionactivities = 0;
    }

    return $accredible->save_record($post, $existingrecord);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance.
 *
 * @param int $id
 * @return bool true if successful
 */
function accredible_delete_instance($id) {
    global $DB;

    // Ensure the certificate exists.
    if (!$certificate = $DB->get_record('accredible', array('id' => $id))) {
        return false;
    }

    return $DB->delete_records('accredible', array('id' => $id));
}

/**
 * Supported feature list
 *
 * @uses FEATURE_MOD_INTRO
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function accredible_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
