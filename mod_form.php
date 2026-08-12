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

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/accredible/lib.php');
require_once($CFG->dirroot . '/mod/accredible/locallib.php');

use mod_accredible\Html2Text\Html2Text;
use mod_accredible\apirest\apirest;
use mod_accredible\local\brand_keys;
use mod_accredible\local\credentials;
use mod_accredible\local\groups;
use mod_accredible\local\users;
use mod_accredible\local\formhelper;

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
        global $DB, $COURSE, $CFG, $PAGE, $OUTPUT;

        // The clients are built further down, once the course is known: which
        // Accredible account they talk to depends on the activity's brand.
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

        // Make sure at least one brand is set up: without one there is no
        // account to issue against at all.
        if (!brand_keys::is_configured()) {
            throw new moodle_exception('nobrandsconfigured', 'accredible');
        }

        // Update form init.
        if (optional_param('update', '', PARAM_INT)) {
            $updatingcert = true;
            $cmid = optional_param('update', '', PARAM_INT);
            $cm = get_coursemodule_from_id('accredible', $cmid, 0, false, MUST_EXIST);
            $id = $cm->course;
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            $accrediblecertificate = $DB->get_record('accredible', ['id' => $cm->instance], '*', MUST_EXIST);
        } else if (optional_param('course', '', PARAM_INT)) { // New form init.
            $id = optional_param('course', '', PARAM_INT);
            $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
            // See if other accredible certificates already exist for this course.
            $alreadyexists = $DB->record_exists('accredible', ['course' => $id]);
        }

        // Resolve the brand before anything talks to the API: every client below
        // is bound to the selected brand's account.
        $selectedbrand = self::resolve_selected_brand($accrediblecertificate ?? null, $course);

        // The course category may name no brand, or one that is not configured.
        // The form still renders so a brand can be picked, but nothing is
        // fetched from Accredible until there is one: showing another brand's
        // groups would be worse than showing none.
        $hasbrand = ($selectedbrand !== '');
        $brandapi = $hasbrand ? apirest::for_brand($selectedbrand) : null;

        $dashboardurl = ($hasbrand && brand_keys::for_brand($selectedbrand)['is_eu'])
            ? 'https://eu.dashboard.accredible.com/'
            : 'https://dashboard.accredible.com/';

        $credentialsclient = $hasbrand ? new credentials($brandapi) : null;
        $groupsclient = $hasbrand ? new groups($brandapi) : null;
        $usersclient = $hasbrand ? new users($brandapi) : null;
        $formhelper = new formhelper($brandapi);

        // Load user data.
        $context = context_course::instance($course->id);
        $users = get_enrolled_users($context, "mod/accredible:view", null, 'u.*');

        if ($updatingcert && $hasbrand) {
            // Grab existing certificates and cross-reference emails.
            if ($accrediblecertificate->achievementid) {
                $userswithcredential = $usersclient->get_users_with_credentials($users, $accrediblecertificate->achievementid);
            } else {
                $userswithcredential = $usersclient->get_users_with_credentials($users, $accrediblecertificate->groupid);
            }
        }

        // Load final quiz choices.
        $quizchoices = ['' => 'Select a Quiz'];
        if ($quizes = $DB->get_records_select('quiz', 'course = :course_id', ['course_id' => $id], '', 'id, name')) {
            foreach ($quizes as $quiz) {
                $quizchoices[$quiz->id] = $quiz->name;
            }
        }

        $inputstyle = ['style' => 'width: 399px'];

        // Load template contexts. The attribute keys belong to the brand's
        // account too, so they wait until there is a brand.
        $attributekeyschoices = $hasbrand ? $formhelper->get_attributekeys_choices() : null;

        $accredibleoptions = $formhelper->map_select_options($attributekeyschoices);
        $coursefieldoptions = $formhelper->load_course_field_options();
        $coursecustomfieldoptions = $formhelper->load_course_custom_field_options();
        $userprofilefieldoptions = $formhelper->load_user_profile_field_options();

        // Form start.
        $PAGE->requires->js_call_amd('mod_accredible/userlist_updater', 'init');
        $PAGE->requires->js_call_amd('mod_accredible/attribute_keys_displayer', 'init');
        $PAGE->requires->js_call_amd('mod_accredible/mappings', 'init');

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

        // Brand selector. It governs which Accredible account the group list
        // below and the credential issuing talk to. The element is not rendered
        // at all when no brand is configured, so installs that never set one up
        // keep exactly the form they had before.
        $brandoptions = ['' => get_string('brandchoose', 'accredible')] + brand_keys::menu();
        $mform->addElement('select', 'brand', get_string('brandlabel', 'accredible'), $brandoptions, $inputstyle);
        $mform->setType('brand', PARAM_TEXT);
        $mform->setDefault('brand', $selectedbrand);
        $mform->addRule('brand', null, 'required', null, 'client');
        $mform->addElement('static', 'branddescription', '', get_string('branddescription', 'accredible'));

        if (!$hasbrand) {
            $mform->addElement('static', 'brandunresolved', '', get_string('brandunresolved', 'accredible'));
        }

        // No-submit button: reposts the form so the group list is rebuilt
        // against the newly selected brand, without any JavaScript.
        $mform->registerNoSubmitButton('reloadbrand');
        $mform->addElement('submit', 'reloadbrand', get_string('brandreload', 'accredible'));

        // Load available groups. These come from the selected brand's account,
        // so a group belonging to another brand is simply not offered. With no
        // brand yet there is nothing to list.
        //
        // Rendered as an autocomplete rather than a plain select: an account can
        // hold hundreds of groups, and this filters them as you type. The search
        // runs in the browser over the options already on the page, so it costs
        // no API calls and can only ever match this brand's groups. Changing the
        // brand reposts the form, which rebuilds the element from scratch and
        // clears whatever was typed.
        //
        // Keep the placeholder short. core/form_autocomplete_input sizes the
        // input to exactly the placeholder's character count and takes no
        // account of the dropdown arrow drawn over its right edge, so a long
        // placeholder ends up running underneath the arrow.
        $templates = $hasbrand ? $groupsclient->get_groups() : [];
        $mform->addElement(
            'autocomplete',
            'groupid',
            get_string('accrediblegroup', 'accredible'),
            $templates,
            [
                'multiple' => false,
                'casesensitive' => false,
                'noselectionstring' => get_string('groupnoselection', 'accredible'),
                'placeholder' => get_string('groupsearchplaceholder', 'accredible'),
            ]
        );
        $mform->addRule('groupid', null, 'required', null, 'client');
        $mform->addElement('static', 'groupsearchhelp', '', get_string('groupsearchhelp', 'accredible', count($templates)));
        if ($updatingcert && $accrediblecertificate->groupid) {
            $mform->setDefault('groupid', $accrediblecertificate->groupid);
        }

        $mform->addElement(
            'static',
            'overview',
            '',
            get_string('activitygroupdescription', 'accredible', $dashboardurl)
        );

        if ($alreadyexists) {
            $mform->addElement('static', 'additionalactivitiestwo', '', get_string('additionalactivitiestwo', 'accredible'));
        }

        if (isset($attributekeyschoices)) {
            // Hidden element to check if we should disable the "gradeattributekeyname" select.
            $mform->addElement('hidden', 'attributekysnumber', 1);
        } else {
            $mform->addElement('hidden', 'attributekysnumber', 0);
        }
        $mform->setType('attributekysnumber', PARAM_INT);

        $mform->addElement(
            'checkbox',
            'includegradeattribute',
            get_string('includegradeattributedescription', 'accredible'),
            get_string('includegradeattributecheckbox', 'accredible')
        );

        $mform->setType('includegradeattribute', PARAM_INT);
        if (isset($accrediblecertificate->includegradeattribute) && $accrediblecertificate->includegradeattribute == 1) {
            $mform->setDefault('includegradeattribute', 1);
            $includegradewrapperhtml = '<div id="include-grade-select-container">';
        } else {
            $includegradewrapperhtml = '<div id="include-grade-select-container" class="hidden">';
        }

        $mform->addElement('html', $includegradewrapperhtml);
        $mform->addElement(
            'select',
            'gradeattributegradeitemid',
            get_string('gradeattributegradeitemselect', 'accredible'),
            $formhelper->load_grade_item_options($id),
            $inputstyle
        );
        $mform->addElement(
            'select',
            'gradeattributekeyname',
            get_string('gradeattributekeynameselect', 'accredible'),
            $attributekeyschoices,
            $inputstyle
        );
        $mform->disabledIf('gradeattributekeyname', 'attributekysnumber', 'eq', 0);
        $mform->addElement(
            'static',
            'emptygradeattributekeyname',
            '',
            get_string(
                'emptygradeattributekeyname',
                'accredible',
                $dashboardurl
            )
        );
        $mform->addElement('html', '</div>');

        if ($updatingcert && $accrediblecertificate->achievementid) {
            // Grab the list of templates available.
            $templates = $hasbrand ? $groupsclient->get_templates() : [];
            $mform->addElement('static', 'usestemplatesdescription', '', get_string('usestemplatesdescription', 'accredible'));
            $mform->addElement('select', 'achievementid', get_string('groupselect', 'accredible'), $templates);
            $mform->addRule('achievementid', null, 'required', null, 'client');
            $mform->setDefault('achievementid', $course->shortname);

            if ($alreadyexists) {
                $mform->addElement(
                    'static',
                    'additionalactivitiesthree',
                    '',
                    get_string('additionalactivitiesthree', 'accredible')
                );
            }
            $mform->addElement(
                'text',
                'certificatename',
                get_string('certificatename', 'accredible'),
                ['style' => 'width: 399px']
            );
            $mform->addRule('certificatename', null, 'required', null, 'client');
            $mform->setType('certificatename', PARAM_TEXT);
            $mform->setDefault('certificatename', $course->fullname);

            $mform->addElement(
                'textarea',
                'description',
                get_string('description', 'accredible'),
                ['cols' => '64', 'rows' => '10', 'wrap' => 'virtual', 'maxlength' => '1000']
            );
            $mform->addRule('description', null, 'required', null, 'client');
            $mform->setType('description', PARAM_RAW);
            $mform->setDefault('description', $description);
            if ($updatingcert) {
                $mform->addElement(
                    'static',
                    'dashboardlink',
                    get_string('dashboardlink', 'accredible'),
                    get_string('dashboardlinktext', 'accredible')
                );
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
                $mform->addElement(
                    'advcheckbox',
                    'unissuedusers[' . $user['id'] . ']',
                    $user['name'] . '    ' . $user['email'],
                    null,
                    ['group' => 2]
                );
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
                    $mform->addElement(
                        'static',
                        'certlink' . $user['id'],
                        $user['name'] . '    ' . $user['email'],
                        'Certificate ' . $user['credential_id'] .
                            ' - <a href=' . $user['credential_url'] . ' target="_blank">link</a>'
                    );
                    $mform->addElement('html', '<div class="hidden">');
                    $mform->addElement(
                        'advcheckbox',
                        'users[' . $user['id'] . ']',
                        $user['name'] . '    ' . $user['email'],
                        null,
                        ['group' => 1]
                    );
                    $mform->addElement('html', '</div>');
                } else { // Show a checkbox if they don't.
                    $mform->addElement(
                        'advcheckbox',
                        'users[' . $user['id'] . ']',
                        $user['name'] . '    ' . $user['email'],
                        null,
                        ['group' => 1]
                    );
                }
            }
        } else { // For new modules, just list all the users.
            foreach ($users as $user) {
                $mform->addElement(
                    'advcheckbox',
                    'users[' . $user->id . ']',
                    $user->firstname . ' ' . $user->lastname . '    ' . $user->email,
                    null,
                    ['group' => 1]
                );
            }
        }
        $mform->addElement('html', '</div>');

        $mform->addElement('header', 'gradeissue', get_string('gradeissueheader', 'accredible'));
        $mform->addElement('select', 'finalquiz', get_string('chooseexam', 'accredible'), $quizchoices);
        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'accredible'));
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);

        $mform->addElement('header', 'completionissue', get_string('completionissueheader', 'accredible'));
        $mform->addElement('checkbox', 'completionactivities', get_string('completionissuecheckbox', 'accredible'));
        if ($updatingcert && isset($accrediblecertificate->completionactivities)) {
            $mform->setDefault('completionactivities', 1);
        }

        $attributemappingdefaultvalues =
            $formhelper->attributemapping_default_values(
                $updatingcert ? $accrediblecertificate->attributemapping : null
            );

        // Attribute mapping: course fields.
        $mform->addElement('header', 'attributemappingcoursefields', get_string('attributemappingcoursefields', 'accredible'));

        $coursefieldmappings = $attributemappingdefaultvalues['coursefieldmapping'];
        $coursefieldmappingcontent = [
            'mappings' => $coursefieldmappings,
            'section' => 'coursefieldmapping',
            'hasmappings' => isset($coursefieldmappings),
            'accredibleoptions' => $accredibleoptions,
            'moodleoptions' => $coursefieldoptions,
        ];

        $mform->addElement('html', $OUTPUT->render_from_template('mod_accredible/mappings', $coursefieldmappingcontent));

        // Attribute mapping: course custom fields.
        $mform->addElement(
            'header',
            'attributemappingcoursecustomfields',
            get_string('attributemappingcoursecustomfields', 'accredible')
        );

        $coursecustomfieldmappings = $attributemappingdefaultvalues['coursecustomfieldmapping'];
        $coursecustomfieldmappingcontent = [
            'mappings' => $coursecustomfieldmappings,
            'section' => 'coursecustomfieldmapping',
            'hasid' => true,
            'hasmappings' => isset($coursecustomfieldmappings),
            'accredibleoptions' => $accredibleoptions,
            'moodleoptions' => $coursecustomfieldoptions,
            'nocoursecustomoptions' => count($coursecustomfieldoptions) == 1,
        ];

        $mform->addElement('html', $OUTPUT->render_from_template('mod_accredible/mappings', $coursecustomfieldmappingcontent));

        // Attribute mapping: user profile fields.
        $mform->addElement(
            'header',
            'attributemappinguserprofilefields',
            get_string('attributemappinguserprofilefields', 'accredible')
        );

        $userprofilefieldmappings = $attributemappingdefaultvalues['userprofilefieldmapping'];
        $userprofilefieldmappingcontent = [
            'mappings' => $userprofilefieldmappings,
            'section' => 'userprofilefieldmapping',
            'hasid' => true,
            'hasmappings' => isset($userprofilefieldmappings),
            'accredibleoptions' => $accredibleoptions,
            'moodleoptions' => $userprofilefieldoptions,
            'noprofileoptions' => count($userprofilefieldoptions) == 1,
        ];

        $mform->addElement('html', $OUTPUT->render_from_template('mod_accredible/mappings', $userprofilefieldmappingcontent));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Which brand the form works against.
     *
     * Priority: what the user just picked (a no-submit repost carries it in the
     * request), then what the activity has stored, then the brand its course
     * category implies. A brand that is no longer configured resolves to
     * nothing rather than offering a value the select cannot show.
     *
     * @param stdClass|null $record the existing activity record when editing
     * @param stdClass $course
     * @return string the brand name, or an empty string when none could be derived
     */
    private static function resolve_selected_brand($record, $course) {
        $configured = brand_keys::menu();

        $submitted = optional_param('brand', null, PARAM_TEXT);
        if ($submitted !== null) {
            return isset($configured[$submitted]) ? $submitted : '';
        }

        if ($record && !empty($record->brand)) {
            return isset($configured[$record->brand]) ? $record->brand : '';
        }

        return brand_keys::brand_from_course($course) ?? '';
    }

    /**
     * The brand is mandatory, and the group has to belong to it.
     *
     * The group select is rebuilt whenever the brand changes, so a stale pair
     * can only arrive from a hand-crafted post. Checked without calling the
     * API: if the brand changed and the group did not, the group is stale.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $newbrand = isset($data['brand']) ? trim((string) $data['brand']) : '';

        // No account to fall back on: an activity without a usable brand has
        // nowhere to issue, so it cannot be saved.
        if (!brand_keys::is_usable($newbrand)) {
            $errors['brand'] = get_string('brandrequired', 'accredible');
            return $errors;
        }

        if (empty($this->_instance)) {
            return $errors;
        }

        $existing = $DB->get_record('accredible', ['id' => $this->_instance], 'id, brand, groupid');
        if (!$existing) {
            return $errors;
        }

        $oldbrand = (string) ($existing->brand ?? '');

        if ($newbrand !== $oldbrand
            && !empty($data['groupid'])
            && (string) $data['groupid'] === (string) $existing->groupid) {
            $errors['groupid'] = get_string('brandgroupmismatch', 'accredible');
        }

        return $errors;
    }

    /**
     * Called right before form submission.
     * We use it to include missing form data from mustache templates.
     *
     * @param  stdClass $data passed by reference
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $submitteddata = $this->_form->getSubmitValues();

        $formhelper = new formhelper();
        $data->coursefieldmapping = isset($submitteddata['coursefieldmapping']) ?
            $formhelper->reindexarray($submitteddata['coursefieldmapping']) : [];
        $data->coursecustomfieldmapping = isset($submitteddata['coursecustomfieldmapping']) ?
            $formhelper->reindexarray($submitteddata['coursecustomfieldmapping']) : [];
        $data->userprofilefieldmapping = isset($submitteddata['userprofilefieldmapping']) ?
            $formhelper->reindexarray($submitteddata['userprofilefieldmapping']) : [];
    }

    /**
     * Sets the default value for a mapping field in the form.
     *
     * @param MoodleQuickForm $mform         The form instance to modify.
     * @param array           $defaultvalues The default values for the form fields.
     * @param string          $mappingname   The name of the mapping field.
     * @param string          $fieldname     The specific field within the mapping to set.
     * @param int             $num           The index of the field in case of multiple fields with the same name.
     */
    private function set_mapping_field_default($mform, $defaultvalues, $mappingname, $fieldname, $num = 0) {
        $value = '';
        if (isset($defaultvalues[$mappingname][$num][$fieldname])) {
            $value = $defaultvalues[$mappingname][$num][$fieldname];
        }
        $mform->setDefault(
            $mappingname . '[' . $num . '][' . $fieldname . ']',
            $value
        );
    }
}
