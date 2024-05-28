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

use mod_accredible\local\attributemapping;

/**
 * Defines local functions for handling interactions with the 'accredible' database table.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accredible {

    /**
     * Saves or updates an Accredible plugin instance record in the 'accredible' table.
     * This function handles both the creation of new records and the updating of existing ones.
     *
     * @param stdClass $post An object containing the incoming data from the form submission.
     * @param stdClass|null $existingrecord Optional. Existing certificate data to be updated.
     * @return bool|int Returns the new record ID on insert, or true on update success.
     */
    public function save_record($post, $existingrecord = null) {
        global $DB;

        $dbrecord = (object) [
            'completionactivities' => $post->completionactivities ?? null,
            'finalgradetopass' => $post->finalgradetopass,
            'name' => $post->name,
            'finalquiz' => $post->finalquiz,
            'passinggrade' => $post->passinggrade,
            'includegradeattribute' => $post->includegradeattribute ?? 0,
            'gradeattributegradeitemid' => $post->gradeattributegradeitemid,
            'gradeattributekeyname' => $post->gradeattributekeyname,
            'groupid' => $post->groupid,
            'attributemapping' => $this->build_attribute_mapping_list($post),
            'timecreated' => time()
        ];

        if ($existingrecord) {
            $dbrecord->id = $post->instance;

            if ($existingrecord->achievementid) {
                $dbrecord->certificatename = $post->certificatename;
                $dbrecord->description = $post->description;
                $dbrecord->achievementid = $post->achievementid;
                // Keep the existing groupid.
                $dbrecord->groupid = $existingrecord->groupid;
            } else {
                $dbrecord->course = $post->course;
            }

            return $DB->update_record('accredible', $dbrecord);
        } else {
            $dbrecord->course = $post->course;
            return $DB->insert_record('accredible', $dbrecord);
        }
    }

    /**
     * Loads custom attributes for a credential based on the attribute mappings stored in the 'accredible' object.
     *
     * @param stdClass $accredible An object containing the 'accredible' record data including attribute mappings.
     * @param int $userid The ID of the user for whom the credential is being loaded.
     * @return array An associative array of custom attributes for an Accredible credential.
     */
    public function load_credential_custom_attributes($accredible, $userid) {
        $customattributes = [];
        if (!isset($accredible->attributemapping) || empty($accredible->attributemapping)) {
            return $customattributes;
        }

        $decodedmapping = json_decode($accredible->attributemapping);
        foreach ($decodedmapping as $mapping) {
            if (!isset($mapping->accredibleattribute) || empty($mapping->accredibleattribute)) {
                continue;
            }
            if ($mapping->table === 'course' && !isset($mapping->field)) {
                continue;
            }
            $idrequiredtables = ['customfield_field', 'user_info_field'];
            if (in_array($mapping->table, $idrequiredtables) && !isset($mapping->id)) {
                continue;
            }
            $value = null;
            switch ($mapping->table) {
                case 'course':
                    $value = $this->load_course_field_value($mapping->field, $accredible->course);
                    break;
                case 'customfield_field':
                    $value = $this->load_customfield_field_value($mapping->id, $accredible->course);
                    break;
                case 'user_info_field':
                    $value = $this->load_user_info_field_value($mapping->id, $userid);
                    break;
            }

            if ($value !== null && $value !== '') {
                $customattributes[$mapping->accredibleattribute] = $value;
            }
        }
        return $customattributes;
    }

    /**
     * Loads the value of a specified field from a course record.
     * If the course object is not provided, it fetches the course record from the database using the course ID.
     *
     * @param string $field The name of the field to retrieve from the course record.
     * @param int $courseid The ID of the course from which to retrieve the field value.
     * @return mixed|null Returns the value of the specified field if found, or null if the course or field is not found.
     */
    private function load_course_field_value($field, $courseid) {
        global $DB;

        $course = $DB->get_record(
            'course',
            array('id' => $courseid),
            '*',
            IGNORE_MISSING
        );
        if (!$course) {
            return;
        }

        $value = $course->{$field};
        if (in_array($field, attributemapping::VALID_COURSE_DATE_FIELDS)) {
            return $this->date($value);
        } else {
            return $value;
        }
    }

    /**
     * Loads the value of a specified custom field for a given course.
     *
     * This function retrieves the value of a custom field based on the field ID and the instance ID of the course.
     * It queries the 'customfield_data' table to find the relevant data.
     *
     * @param int $customfieldfieldid The ID of the custom field.
     * @param int $courseid The ID of the course instance.
     * @return mixed|null Returns the value of the custom field if found, or null if not found.
     */
    private function load_customfield_field_value($customfieldfieldid, $courseid) {
        global $DB;

        $customfielddata = $DB->get_record(
            'customfield_data',
            array(
                'fieldid' => $customfieldfieldid,
                'instanceid' => $courseid
            ),
            '*',
            IGNORE_MISSING
        );
        if (!$customfielddata) {
            return null;
        }

        $value = $customfielddata->value;
        if ($value === null || $value === '') {
            return;
        }

        $customfield = $DB->get_record(
            'customfield_field',
            array('id' => $customfieldfieldid),
            '*',
            MUST_EXIST
        );
        if ($customfield->type === 'date') {
            return $this->date($value);
        } else if ($customfield->type === 'textarea') {
            return strip_tags($value);
        } else {
            return $value;
        }
    }

    /**
     * Loads the value of a specified user info field for a given user.
     *
     * This function retrieves the value of a user info field based on the field ID and the user ID.
     * It queries the 'user_info_data' table to find the relevant data.
     *
     * @param int $userinfofieldid The ID of the user info field.
     * @param int $userid The ID of the user.
     * @return mixed|null Returns the value of the user info field if found, or null if not found.
     */
    private function load_user_info_field_value($userinfofieldid, $userid) {
        global $DB;

        $userinfodata = $DB->get_record(
            'user_info_data',
            array(
                'fieldid' => $userinfofieldid,
                'userid' => $userid
            ),
            '*',
            IGNORE_MISSING
        );
        if (!$userinfodata) {
            return null;
        }

        $userinfofield = $DB->get_record(
            'user_info_field',
            array('id' => $userinfofieldid),
            '*',
            MUST_EXIST
        );
        if ($userinfofield->datatype === 'datetime') {
            return $this->date($userinfodata->data);
        } else if ($userinfofield->datatype === 'textarea') {
            return strip_tags($userinfodata->data);
        } else {
            return $userinfodata->data;
        }
    }

    /**
     * Formats a timestamp into a human-readable date string based on the site's locale settings.
     *
     * @param int $value The timestamp to be formatted.
     * @return string The formatted date string.
     */
    private function date($value) {
        if ($value === null || $value === '') {
            return;
        }
        $accredibledateformat = 'Y-m-d';
        return date($accredibledateformat, $value);
    }

    /**
     * Builds a JSON encoded attribute mapping list to be stored in the DB based on the provided post data.
     *
     * @param object $post The post data containing the course field mappings, course custom field mappings,
     * and user field mappings.
     * @return string JSON encoded attribute mapping list.
     */
    private function build_attribute_mapping_list($post) {
        $coursefieldmapping = $this->parse_attributemapping(
            'course',
            isset($post->coursefieldmapping) ? $post->coursefieldmapping : []
        );
        $coursecustomfieldmapping = $this->parse_attributemapping(
            'customfield_field',
            isset($post->coursecustomfieldmapping) ? $post->coursecustomfieldmapping : []
        );
        $userprofilefieldmapping = $this->parse_attributemapping(
            'user_info_field',
            isset($post->userprofilefieldmapping) ? $post->userprofilefieldmapping : []
        );
        $mergedmappings = array_merge($coursefieldmapping, $coursecustomfieldmapping, $userprofilefieldmapping);
        if (empty($mergedmappings)) {
            return null;
        }

        $attributemappings = array_map(function($mapping) {
            return new attributemapping($mapping->table, $mapping->accredibleattribute, $mapping->field, $mapping->id);
        }, $mergedmappings);

        $attributemappinglist = new attributemapping_list($attributemappings);
        return $attributemappinglist->get_text_content();
    }

    /**
     * Parses attribute mappings from posted data into a structured array of objects.
     *
     * This function takes a table name and the posted mapping data, then constructs
     * an array of objects where each object represents a mapping configuration.
     * The structure and fields of the object depend on the table type specified.
     *
     * @param string $table The type of table the mapping applies to ('course', 'user_info_field', or 'customfield_field').
     * @param array $postedmapping The array of mappings posted from the form.
     * @return array An array of objects, each representing a mapping configuration.
     */
    private function parse_attributemapping($table, $postedmapping) {
        $parsedmapping = [];
        foreach ($postedmapping as $mapping) {
            $entry = (object) [
                'table' => $table,
                'accredibleattribute' => $mapping['accredibleattribute']
            ];

            if ($table === 'course') {
                $entry->id = null;
                $entry->field = $mapping['field'];
            }
            if ($table === 'user_info_field' || $table === 'customfield_field') {
                $entry->id = (int) $mapping['id'];
                $entry->field = null;
            }
            $parsedmapping[] = $entry;
        }
        return $parsedmapping;
    }
}
