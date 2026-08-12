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
 * Language strings for the accredible module
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

$string['achievementid'] = 'Achievement id / cohort name (must be unique)';
$string['activityname'] = 'Activity name';
$string['accrediblecustomattributename'] = "Choose an Accredible design attribute:";
$string['accrediblecustomattributeselectprompt'] = 'Select an Accredible custom attribute';
$string['additionalactivitiesone'] = 'Warning: You are adding more than one activity to a course.<br/>Both activities are viewable by students, so be sure to give them different names.';
$string['additionalactivitiestwo'] = 'Certificates/Badges will only be listed on the activity page if they were issued with this achievement id.';
$string['additionalactivitiesthree'] = 'This is the name that will appear on the ceriticate.';
$string['autoissueheader'] = 'Automatic issuing criteria';
$string['attributemappingcoursefields'] = "Attribute mapping: course fields";
$string['attributemappingcoursecustomfields'] = "Attribute mapping: course custom fields";
$string['attributemappinguserprofilefields'] = "Attribute mapping: user profile fields";
$string['brandsheading'] = 'Brands';
$string['brandsheadinghelp'] = 'Configure one Accredible account per brand. Activities select a brand and are issued against that account. Leave a slot empty to disable it; activities with no brand use the API key above.';
$string['brandnamelabel'] = 'Brand {$a}: name';
$string['brandnamehelp'] = 'Name shown when selecting a brand on the activity. Leave empty to disable this slot.';
$string['brandapikeylabel'] = 'Brand {$a}: API key';
$string['brandapikeyhelp'] = 'API key of this brand\'s Accredible account.';
$string['brandlabel'] = 'Brand';
$string['brandchoose'] = 'Choose a brand';
$string['branddescription'] = 'The Accredible account this activity issues against. Preselected from the course category; change it and reload to pick a group from that account.';
$string['brandreload'] = 'Reload groups for this brand';
$string['brandgroupmismatch'] = 'You changed the brand, so choose a group from the new account.';
$string['brandrequired'] = 'Choose a brand: the activity issues against its Accredible account.';
$string['brandunresolved'] = 'This course category does not name a brand, so none could be preselected. Choose one and reload to list its groups.';
$string['brandmissing'] = 'No brand was given, and there is no default Accredible account.';
$string['brandunknown'] = 'Brand "{$a}" is not configured in the plugin settings.';
$string['brandwithoutkey'] = 'Brand "{$a}" has no API key configured.';
$string['nobrandsconfigured'] = 'No Accredible brand is configured. Add at least one in the plugin settings before creating this activity.';
$string['brandeulabel'] = 'Brand {$a}: EU (Frankfurt) Server';
$string['brandeuhelp'] = 'Select if this brand\'s data is hosted in the EU (Frankfurt) instead of the USA.';
$string['accredible:addinstance'] = 'Add a certificate/badge instance';
$string['accredible:manage'] = 'Manage a certificate/badge instance';
$string['accredible:student'] = 'Retrieve a certificate or badge';
$string['accredible:view'] = 'View a certificate or badge';
$string['certificatename'] = 'Certificate/Badge name';
$string['certificateurl'] = 'Certificate/Badge url';
$string['chooseexam'] = 'Choose final quiz';
$string['completionissueheader'] = 'Auto-issue criteria: by course completion';
$string['completionissuecheckbox'] = 'Yes, issue upon course completion';
$string['coursetotal'] = 'Course Total';
$string['includegradeattributedescription'] = "Include Student's Grade in Credential";
$string['includegradeattributecheckbox'] = "Yes, include grade in Credential.";
$string['gradeattributegradeitemselect'] = "Choose Moodle grade to include:";
$string['gradeattributekeynameselect'] = "Choose an Accredible design attribute:";
$string['dashboardlink'] = 'Accredible dashboard link';
$string['dashboardlinktext'] = 'To delete or style credentials, log in to the <a href="https://dashboard.accredible.com" target="_blank">dashboard</a>';
$string['datecreated'] = 'Date created';
$string['description'] = 'Description';
$string['eventcertificatecreated'] = 'A credential was posted to Accredible';
$string['eventcredentialissued'] = 'Accredible Credential issued';
$string['eventcredentialissuefailed'] = 'Accredible Credential issuance failed';
$string['eventcredentialissueskipped'] = 'Accredible Credential issuance skipped';
$string['eventapirequestfailed'] = 'Accredible API request failed';
$string['reason_completion_not_met'] = 'Required completion activities were not all complete';
$string['reason_exception'] = 'An unexpected error occurred during issuance';
$string['reason_grade_below_threshold'] = 'The grade was below the passing threshold';
$string['reason_missing_email'] = 'The user has no email address';
$string['reason_missing_groupid'] = 'No Accredible group id is configured';
$string['reason_nothing_to_check'] = 'The activity has no final quiz or completion activities configured';
$string['reason_repeat_attempt'] = 'The deciding quiz was attempted more than once';
$string['reason_malformed_completion_record'] = 'The stored completion activities record is corrupt';
$string['manualissuefailures'] = 'Credential issuance failed for {$a->count} user(s): {$a->users}. See Site administration → Reports → Logs (component mod_accredible) for details.';
$string['credentialloaderror'] = 'Could not load credentials from Accredible right now. Please try again later; details are in the logs (component mod_accredible).';
$string['gradeissueheader'] = 'Auto-issue criteria: by final quiz grade';
$string['id'] = 'ID';
$string['indexheader'] = 'All certificates/badges for {$a}';
$string['issued'] = 'Issued';
$string['manualheader'] = 'Manually issue certificates/badges';
$string['modulename'] = 'Accredible certificates & badges';
$string['modulename_help'] = 'The Accredible certificate & badge activity module allows you to issue course certificates or badges to students on accredible.com.

Add the activity wherever you want your students see their certificate or badge.';
$string['modulename_link'] = 'mod/accredible/view';
$string['modulenameplural'] = 'Accredible certificates/badges';

$string['moodlecoursefield'] = 'Choose Moodle course field to include';
$string['moodlecoursecustomfield'] = 'Choose Moodle custom course field to include';
$string['moodleuserprofilefield'] = 'Choose Moodle user profile field to include';

$string['nocertificates'] = 'There are no certificates/badges';
$string['passinggrade'] = 'Percentage grade needed to pass course (%)';
$string['pluginadministration'] = 'Accredible certificates/badges administration';
$string['pluginname'] = 'Accredible certificates & badges';
$string['recipient'] = 'Recipient';
$string['templatename'] = 'Cohort name (from dashboard)';
$string['groupselect'] = 'Group';
$string['unissuedheader'] = 'Unissued certificates/badges';
$string['unissueddescription'] = 'These users have met the requirements for this certificate but have not yet been issued a certificate. Select those you would like to issue certificates for.';
$string['usestemplatesdescription'] = 'Make sure you have a cohort on the dashboard with the same name as your achievement id.';
$string['viewheader'] = 'Certificates & badges for {$a}';
$string['viewimgcomplete'] = 'Click to view your certificate or badge';
$string['viewimgincomplete'] = 'Course still in progress';
$string['viewsubheaderold'] = 'Achievement ID: {$a}';
$string['viewsubheader'] = 'Group ID: {$a}';

$string['gotodashboard'] = 'To update the appearance of your badges and certificates, visit: <a href="https://dashboard.accredible.com" target="_blank">https://dashboard.accredible.com</a>';
$string['overview'] = 'Overview';
$string['activitygroupdescription'] = 'Credentials groups need to have been created in the <a href="{$a}" target="_blank">Accredible Dashboard</a> before credentials can be issued. If none appear, check your API Key to make sure integration is set up correctly.';
$string['accrediblegroup'] = 'Accredible Group';
$string['emptygradeattributekeyname'] = 'The final course grade will be mapped to the selected Accredible custom attribute. If you have not yet created a custom attribute, you can do so on the <a href="{$a}" target="_blank">Accredible Platform</a>.';

$string['privacy:metadata:accredible'] = 'In order to integrate with Accredible, user data needs to be exchanged with that service.';
$string['privacy:metadata:accredible:email'] = 'Your email address is sent to Accredible to issue a credential.';
$string['privacy:metadata:accredible:fullname'] = 'Your full name is sent to Accredible to issue a credential.';
$string['privacy:metadata:accredible:quizgrade'] = 'Your quiz grade may be sent to Accredible to issue a credential.';
$string['nouserswarning'] = 'You need to choose an Accredible group in order to see the list of users.';

// phpcs:enable moodle.Files.LangFilesOrdering.IncorrectOrder
