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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/accredible/lib.php');
require_once($CFG->dirroot.'/mod/accredible/locallib.php');

use mod_accredible\local\credentials;
use mod_accredible\Html2Text\Html2Text;
use mod_accredible\local\groups;

/**
 * Accredible settings form.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        global $DB, $COURSE, $CFG, $PAGE;

        $credentialsclient = new credentials();
        $groupsclient = new groups();

        $updatingcert = false;
        $alreadyexists = false;

        if (!extension_loaded('mbstring')) {
            throw new moodle_exception('You or administrator must install mbstring extensions of php.');
        }

        if (!extension_loaded('dom')) {
            throw new moodle_exception('You or administrator must install dom extensions of php.');
        }

        $description = Html2Text::convert($COURSE->summary);
        if (empty($description)) {
            $description = "Recipient has compeleted the achievement.";
        }

        if ($CFG->is_eu) {
            $dashboardurl = 'https://eu.dashboard.accredible.com/';
        } else {
            $dashboardurl = 'https://dashboard.accredible.com/';
        }

        // Make sure the API key is set.
        if (!isset($CFG->accredible_api_key)) {
            throw new moodle_exception('Please set your API Key first in the plugin settings.');
        }
        // Update form init.
        if (optional_param('update', '', PARAM_INT)) {
            $updatingcert = true;
            $cmid = optional_param('update', '', PARAM_INT);
            $cm = get_coursemodule_from_id('accredible', $cmid, 0, false, MUST_EXIST);
            $id = $cm->course;
            $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
            $accrediblecertificate = $DB->get_record('accredible', array('id' => $cm->instance), '*', MUST_EXIST);
        } else if (optional_param('course', '', PARAM_INT)) { // New form init.
            $id = optional_param('course', '', PARAM_INT);
            $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
            // See if other accredible certificates already exist for this course.
            $alreadyexists = $DB->record_exists('accredible', array('course' => $id));
        }

        // Load user data.
        $context = context_course::instance($course->id);
        $users = get_enrolled_users($context, "mod/accredible:view", null, 'u.*');

        // Load final quiz choices.
        $quizchoices = array(0 => 'None');
        if ($quizes = $DB->get_records_select('quiz', 'course = :course_id', array('course_id' => $id) )) {
            foreach ($quizes as $quiz) {
                $quizchoices[$quiz->id] = $quiz->name;
            }
        }

        $inputstyle = array('style' => 'width: 399px');

        // Form start.
        $PAGE->requires->js_call_amd('mod_accredible/userlist_updater', 'init');
        $mform =& $this->_form;
        $mform->addElement('hidden', 'course', $id);
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'accredible'), $inputstyle);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $course->fullname);

        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiesone', '', get_string('additionalactivitiesone', 'accredible'));
        }

        // Load available groups.
        $templates = array('' => '') + $groupsclient->get_groups();
        $mform->addElement('select', 'groupid', get_string('accrediblegroup', 'accredible'), $templates, $inputstyle);
        $mform->addRule('groupid', null, 'required', null, 'client');
        if ($updatingcert && $accrediblecertificate->groupid) {
            $mform->setDefault('groupid', $accrediblecertificate->groupid);
        }

        $mform->addElement('static', 'overview', '',
            get_string('activitygroupdescription', 'accredible', $dashboardurl));

        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiestwo', '', get_string('additionalactivitiestwo', 'accredible'));
        }

        if ($updatingcert && $accrediblecertificate->achievementid) {
            // Grab the list of templates available.
            $templates = $groupsclient->get_templates();
            $mform->addElement('static', 'usestemplatesdescription', '', get_string('usestemplatesdescription', 'accredible'));
            $mform->addElement('select', 'achievementid', get_string('groupselect', 'accredible'), $templates);
            $mform->addRule('achievementid', null, 'required', null, 'client');
            $mform->setDefault('achievementid', $course->shortname);

            if ($alreadyexists) {
                $mform->addElement('static', 'additionalactivitiesthree', '',
                    get_string('additionalactivitiesthree', 'accredible'));
            }
            $mform->addElement('text', 'certificatename',
                get_string('certificatename', 'accredible'), array('style' => 'width: 399px'));
            $mform->addRule('certificatename', null, 'required', null, 'client');
            $mform->setType('certificatename', PARAM_TEXT);
            $mform->setDefault('certificatename', $course->fullname);

            $mform->addElement('textarea', 'description',
                get_string('description', 'accredible'),
                array('cols' => '64', 'rows' => '10', 'wrap' => 'virtual', 'maxlength' => '1000'));
            $mform->addRule('description', null, 'required', null, 'client');
            $mform->setType('description', PARAM_RAW);
            $mform->setDefault('description', $description);
            if ($updatingcert) {
                $mform->addElement('static', 'dashboardlink',
                    get_string('dashboardlink', 'accredible'), get_string('dashboardlinktext', 'accredible'));
            }
        }

        // Get certificates if updating.
        if ($updatingcert) {
            // Grab existing certificates and cross-reference emails.
            if ($accrediblecertificate->achievementid) {
                $certificates = $credentialsclient->get_credentials($accrediblecertificate->achievementid);
            } else if ($accrediblecertificate->groupid) {
                $certificates = $credentialsclient->get_credentials($accrediblecertificate->groupid);
            }
        }

        // Generate list of users who have earned a certificate, if updating.
        if ($updatingcert) {
            foreach ($users as $user) {
                // If this user has completed the criteria to earn a certificate, add to $usersearnedcertificate.
                if (accredible_check_if_cert_earned($accrediblecertificate, $course, $user)) {
                    $usersearnedcertificate[$user->id] = $user;
                }
            }
        }

        // Unissued certificates header.
        if (isset($usersearnedcertificate) && count($usersearnedcertificate) > 0) {
            $unissuedheader = false;
            foreach ($usersearnedcertificate as $user) {
                $existingcertificate = false;
                foreach ($certificates as $certificate) {
                    // Search through the certificates to see if this user has one existing.
                    if ($certificate->recipient->email == strtolower($user->email)) {
                        // This user has an existing certificate, no need to continue searching.
                        $existingcertificate = true;
                        break;
                    }
                }

                if (!$existingcertificate) {
                    if (!$unissuedheader) {
                        // The header has not been added to the form yet and is needed.
                        $mform->addElement('header', 'chooseunissuedusers', get_string('unissuedheader', 'accredible'));
                        $mform->addElement('static', 'unissueddescription', '', get_string('unissueddescription', 'accredible'));
                        $this->add_checkbox_controller(2, 'Select All/None');
                        $unissuedheader = true;
                    }
                    // No existing certificate, add this user to the unissued users list.
                    $mform->addElement('advcheckbox', 'unissuedusers['.$user->id.']',
                        $user->firstname . ' ' . $user->lastname . '    ' . $user->email, null, array('group' => 2));
                }

            }
        }

        // Manually issue certificates header.
        $mform->addElement('header', 'chooseusers', get_string('manualheader', 'accredible'));
        $mform->addElement('html', '<div id="users-container">');
        $this->add_checkbox_controller(1, 'Select All/None');

        if ($updatingcert) {
            // Grab existing credentials and cross-reference emails.
            if ($accrediblecertificate->achievementid) {
                $certificates = $credentialsclient->get_credentials($accrediblecertificate->achievementid);
            } else {
                $certificates = $credentialsclient->get_credentials($accrediblecertificate->groupid);
            }

            foreach ($users as $user) {
                $certid = null;
                // Check cert emails for this user.
                foreach ($certificates as $certificate) {
                    if ($certificate->recipient->email == strtolower($user->email)) {
                        $certid = $certificate->id;
                        if (isset($certificate->url)) {
                            $certlink = $certificate->url;
                        } else {
                            $certlink = 'https://www.credential.net/'.$certid;
                        }
                    }
                }
                // Show the certificate if they have a certificate.
                if ($certid) {
                    $mform->addElement('static', 'certlink'.$user->id,
                        $user->firstname . ' ' . $user->lastname . '    ' . $user->email,
                        "Certificate $certid - <a href='$certlink' target='_blank'>link</a>");
                    $mform->addElement('html', '<div class="hidden">');
                    $mform->addElement('advcheckbox', 'users['.$user->id.']',
                        $user->firstname . ' ' . $user->lastname . '    ' . $user->email, null, array('group' => 1));
                    $mform->addElement('html', '</div>');
                } else { // Show a checkbox if they don't.
                    $mform->addElement('advcheckbox', 'users['.$user->id.']',
                        $user->firstname . ' ' . $user->lastname . '    ' . $user->email, null, array('group' => 1));
                }
            }
        } else { // For new modules, just list all the users.
            foreach ($users as $user) {
                $mform->addElement('advcheckbox', 'users['.$user->id.']',
                    $user->firstname . ' ' . $user->lastname . '    ' . $user->email, null, array('group' => 1));
            }
        }
        $mform->addElement('html', '</div>');

        $mform->addElement('header', 'gradeissue', get_string('gradeissueheader', 'accredible'));
        $mform->addElement('select', 'finalquiz', get_string('chooseexam', 'accredible'), $quizchoices);
        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'accredible'));
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);

        $mform->addElement('header', 'completionissue', get_string('completionissueheader', 'accredible'));
        if ($updatingcert) {
            $mform->addElement('checkbox', 'completionactivities', get_string('completionissuecheckbox', 'accredible'));
            if (isset( $accrediblecertificate->completionactivities )) {
                $mform->setDefault('completionactivities', 1);
            }
        } else {
            $mform->addElement('checkbox', 'completionactivities', get_string('completionissuecheckbox', 'accredible'));
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
