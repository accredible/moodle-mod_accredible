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

use mod_accredible\Html2Text\Html2Text;
use mod_accredible\local\credentials;
use mod_accredible\local\groups;
use mod_accredible\local\users;
use mod_accredible\local\attribute_keys;

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
        $attributekeysclient = new attribute_keys();
        $groupsclient = new groups();
        $usersclient = new users();

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

        if ($updatingcert) {
            // Grab existing certificates and cross-reference emails.
            if ($accrediblecertificate->achievementid) {
                $userswithcredential = $usersclient->get_users_with_credentials($users, $accrediblecertificate->achievementid);
            } else {
                $userswithcredential = $usersclient->get_users_with_credentials($users, $accrediblecertificate->groupid);
            }
        }

        // Load final quiz choices.
        $quizchoices = array('' => 'Select a Quiz');
        if ($quizes = $DB->get_records_select('quiz', 'course = :course_id', array('course_id' => $id), '', 'id, name')) {
            foreach ($quizes as $quiz) {
                $quizchoices[$quiz->id] = $quiz->name;
            }
        }

        // Load course assigments.
        $assigmentschoices = array('' => 'Select an Activity Grade');
        $assigments = $DB->get_records_select('grade_items', 'courseid = :course_id AND itemtype = :item_type',
            array('course_id' => $id, 'item_type' => 'mod'), '', 'id, itemname');
        if ($assigments) {
            foreach ($assigments as $assigment) {
                $assigmentschoices[$assigment->id] = $assigment->itemname;
            }
        }

        $inputstyle = array('style' => 'width: 399px');

        // Form start.
        $PAGE->requires->js_call_amd('mod_accredible/userlist_updater', 'init');
        $PAGE->requires->js_call_amd('mod_accredible/attribute_keys_displayer', 'init');
        $mform =& $this->_form;
        $mform->addElement('hidden', 'course', $id);
        if ($updatingcert) {
            $mform->addElement('hidden', 'instance-id', $cm->instance);
        } else {
            $mform->addElement('hidden', 'instance-id', 0);
        }
        $mform->setType('instance-id', PARAM_INT);
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'accredible'), $inputstyle);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', $course->fullname);

        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiesone', '', get_string('additionalactivitiesone', 'accredible'));
        }

        // Load available groups.
        $templates = array('' => 'Select a Group') + $groupsclient->get_groups();
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

        // Load Accredible attribute keys.
        $attributekeys = $attributekeysclient->get_attribute_keys();

        if (isset($attributekeys)) {
            $attributekeyschoices = array('' => 'Select a Custom Attribute') + $attributekeys;
            // Hidden element to check if we should disable the "gradeattributekeyname" select.
            $mform->addElement('hidden', 'attributekysnumber', 1);
        } else {
            $mform->addElement('hidden', 'attributekysnumber', 0);
        }
        $mform->setType('attributekysnumber', PARAM_INT);

        $mform->addElement('checkbox', 'includegradeattribute', get_string('includegradeattributedescription', 'accredible'),
            get_string('includegradeattributecheckbox', 'accredible'));

        $mform->setType('includegradeattribute', PARAM_INT);
        if (isset( $accrediblecertificate->includegradeattribute ) && $accrediblecertificate->includegradeattribute == 1) {
            $mform->setDefault('includegradeattribute', 1);
            $includegradewrapperhtml = '<div id="include-grade-select-container">';
        } else {
            $includegradewrapperhtml = '<div id="include-grade-select-container" class="hidden">';
        }

        $mform->addElement('html', $includegradewrapperhtml);
        $mform->addElement('select', 'gradeattributegradeitemid', get_string('gradeattributegradeitemselect', 'accredible'),
            $assigmentschoices, $inputstyle);
        $mform->addElement('select', 'gradeattributekeyname', get_string('gradeattributekeynameselect', 'accredible'),
            $attributekeyschoices, $inputstyle);
        $mform->disabledIf('gradeattributekeyname', 'attributekysnumber', 'eq', 0);
        $mform->addElement('static', 'emptygradeattributekeyname', '', get_string('emptygradeattributekeyname', 'accredible',
            $dashboardurl));
        $mform->addElement('html', '</div>');

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

        // Users who pass the requirements but not have credential.
        if (isset($userswithcredential) && count($userswithcredential) > 0) {
            $mform->addElement('header', 'chooseunissuedusers', get_string('unissuedheader', 'accredible'));
            $mform->addElement('html', '<div class="manual-issue-warning hidden">');
            $mform->addElement('static', 'nouserswarning', '', get_string('nouserswarning', 'accredible'));
            $mform->addElement('html', '</div>');
            $mform->addElement('static', 'unissueddescription', '', get_string('unissueddescription', 'accredible'));
            $this->add_checkbox_controller(2, 'Select All/None');
            $mform->addElement('html', '<div id="unissued-users-container">');

            $unissuedusers = $usersclient->get_unissued_users($userswithcredential, $cm->instance);

            foreach ($unissuedusers as $user) {
                // No existing certificate, add this user to the unissued users list.
                $mform->addElement('advcheckbox', 'unissuedusers['.$user['id'].']',
                    $user['name'] . '    ' . $user['email'], null, array('group' => 2));
            }
            $mform->addElement('html', '</div>');
        }

        // Manually issue certificates header.
        $mform->addElement('header', 'chooseusers', get_string('manualheader', 'accredible'));
        // Hidden message to be displayed with Javascript when no users are available.
        $mform->addElement('html', '<div class="manual-issue-warning hidden">');
        $mform->addElement('static', 'nouserswarning', '', get_string('nouserswarning', 'accredible'));
        $mform->addElement('html', '</div>');
        $this->add_checkbox_controller(1, 'Select All/None');
        $mform->addElement('html', '<div id="manual-issue-users-container">');

        if ($updatingcert) {
            foreach ($userswithcredential as $user) {
                // Show the certificate if they have a certificate.
                if ($user['credential_id']) {
                    $mform->addElement('static', 'certlink'.$user['id'],
                        $user['name'] . '    ' . $user['email'],
                        'Certificate '. $user['credential_id'].' - <a href='.$user['credential_url'].' target="_blank">link</a>');
                    $mform->addElement('html', '<div class="hidden">');
                    $mform->addElement('advcheckbox', 'users['.$user['id'].']',
                        $user['name'] . '    ' . $user['email'], null, array('group' => 1));
                    $mform->addElement('html', '</div>');
                } else { // Show a checkbox if they don't.
                    $mform->addElement('advcheckbox', 'users['.$user['id'].']',
                        $user['name'] . '    ' . $user['email'], null, array('group' => 1));
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

 
       if (isset($attributekeys)) {
        $mform->addElement('header', 'manualoptions', get_string('otheroptionsheader', 'accredible'));

        $defaults = false;
        $otheropts = $accrediblecertificate->otheroptions; // from db record
        if ($otheropts) {
            $otherats = json_decode($otheropts,true);
            foreach($otherats as $key => $value) {
                $otheratts[$this->slugify($key)] = $value;
            }
            $defaults = true;
        }

        foreach ($attributekeys as $attribute) {
        
            $key=$this->slugify($attribute);
            $mform->addElement('text', 'other'.$key, $attribute); //get_string('passinggrade', 'accredible'));
            $mform->setType('other'.$key, PARAM_TEXT);
            if ($defaults && array_key_exists($key, $otheratts)) {
                $mform->setDefault('other'.$key,  $otheratts[$key]);
            }    
            }
            
        }
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

      
    /**
     *  Return the slug of a string to be used in a URL.
     *
     *  @return String
     */
public function slugify($text){
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '_');

    // remove duplicated - symbols
    $text = preg_replace('~-+~', '_', $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
      return 'n-a';
    }

    return $text;
}


    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        $attributekeysclient = new attribute_keys();
        $attributekeys = $attributekeysclient->get_attribute_keys();
        $att = array();
        foreach ($attributekeys as $attribute) {
            $att[$this->slugify($attribute)] = $attribute;
        }

        $otherattrs = array();
        foreach ($data as $key => $value) { 
            $pos = strpos($key, 'other');
            if ($pos !== false) {
                $key = substr($key, 5); //remove other
                $otherattrs[$att[$key]] = $value;    
            }
        }
        $otherattrs = json_encode($otherattrs);
        $data->otheroptions = $otherattrs ;
    }
}
