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
use mod_accredible\local\attribute_keys;

/**
 * Helper class for mod_form.php.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formhelper {
    /**
     * Load grade item options for the custom attribute mapping dropdown.
     *
     * This function retrieves grade items associated with the course and formats them for use in a select element.
     *
     * @param int $courseid The ID of the course to retrieve
     * @return array Associative array of grade item IDs and their names, suitable for form dropdown.
     */
    public function load_grade_item_options($courseid) {
        global $DB;

        $options = array('' => 'Select an Activity Grade');

        $coursegradeitem = $DB->get_record(
            'grade_items',
            array(
                'courseid' => $courseid,
                'itemtype' => 'course'
            ),
            'id',
            IGNORE_MULTIPLE
        );
        if ($coursegradeitem) {
            $options[$coursegradeitem->id] = get_string('coursetotal', 'accredible');
        }

        $modgradeitems = $DB->get_records_select(
            'grade_items',
            'courseid = :course_id AND itemtype = :item_type',
            array('course_id' => $courseid, 'item_type' => 'mod'),
            '',
            'id, itemname'
        );
        if ($modgradeitems) {
            foreach ($modgradeitems as $item) {
                $options[$item->id] = $item->itemname;
            }
        }

        return $options;
    }

    /**
     * Load course field options for dropdown selection.
     *
     * This function retrieves predefined valid course field names from the attributemapping class
     * and formats them as options for a select element.
     *
     * @return array Associative array of course field names suitable for form dropdown.
     */
    public function load_course_field_options() {
        $options = [
            [
                'value' => '',
                'name' => 'Select a Moodle course field',
            ]
        ];
        $fields = attributemapping::VALID_COURSE_FIELDS;
        foreach ($fields as $field) {
            $options[] = [
                'value' => $field,
                'name' => $field,
            ];
        }
        return $options;
    }

    /**
     * Load course custom field options for dropdown selection.
     *
     * This function retrieves all custom fields associated with Moodle courses
     * from the 'customfield_field' table and formats them as options for a select element.
     *
     * @return array Associative array of custom field IDs and their names, suitable for form dropdown.
     */
    public function load_course_custom_field_options() {
        global $DB;

        $options = [
            [
                'value' => '',
                'name' => 'Select a Moodle course custom field',
            ]
        ];
        $customfields = $DB->get_records('customfield_field', array(), '', 'id, name');
        foreach ($customfields as $field) {
            $options[] = [
                'value' => $field->id,
                'name' => $field->name,
            ];
        }
        return $options;
    }

    /**
     * Load user profile field options for dropdown selection.
     *
     * This function retrieves all user profile fields from the 'user_info_field' table
     * and formats them as options for a select element, mapping field IDs to their names.
     *
     * @return array Associative array of user profile field IDs and their names, suitable for form dropdown.
     */
    public function load_user_profile_field_options() {
        global $DB;

        $options = [
            [
                'value' => '',
                'name' => 'Select a Moodle user profile field',
            ]
        ];
        $profilefields = $DB->get_records('user_info_field', array(), '', 'id, name');
        foreach ($profilefields as $field) {
            $options[] = [
                'value' => $field->id,
                'name' => $field->name,
            ];
        }
        return $options;
    }

    /**
     * Maps an associative array to an array of select options.
     *
     * This function converts an associative array of options into an array
     * of associative arrays with 'name' and 'value' keys, suitable for use
     * in select elements in forms.
     *
     * @param array $options An associative array of options where the key is the option name and the value is the option value.
     * @return array An array of associative arrays with 'name' and 'value' keys.
     */
    public function map_select_options($options) {
        $selectoptions = [];
        if (!isset($options)) {
            return $selectoptions;
        }
        $keys = array_keys($options);
        foreach ($keys as $key) {
            $selectoptions[] = [
                'name' => $options[$key],
                'value' => $key,
            ];
        }

        return $selectoptions;
    }

    /**
     * Retrieves attribute keys choices for a select input.
     *
     * This function fetches attribute keys of type 'text' and 'date' using the
     * attribute_keys client, merges them, and returns them as choices for a select input.
     * It includes a prompt option as the first element in the choices array.
     *
     * @return array An associative array of attribute key choices for a select input.
     */
    public function get_attributekeys_choices() {
        $attributekeysclient = $this->get_attribute_keys_client();
        $firstoption = ['' => get_string('accrediblecustomattributeselectprompt', 'accredible')];

        $textattributekeys = $attributekeysclient->get_attribute_keys('text');
        $dateattributekeys = $attributekeysclient->get_attribute_keys('date');
        $attributekeys = array_merge($textattributekeys, $dateattributekeys);

        return isset($attributekeys) ? $firstoption + $attributekeys : $attributekeys;
    }

    /**
     * Generate default values for attribute mapping based on a JSON string.
     *
     * This function decodes a JSON string representing attribute mappings and organizes them into
     * structured arrays for each type of mapping (course fields, course custom fields, and user profile fields).
     *
     * @param string $attributemapping JSON string containing the attribute mappings.
     * @return array Associative array with keys 'coursefieldmapping', 'coursecustomfieldmapping', and 'userprofilefieldmapping',
     *               each containing an array of mappings relevant to that category.
     */
    public function attributemapping_default_values($attributemapping) {
        $defaultvalues = [
            'coursefieldmapping' => [],
            'coursecustomfieldmapping' => [],
            'userprofilefieldmapping' => []
        ];
        if (!$attributemapping) {
            return $defaultvalues;
        }
        $coursefieldmappingindex = 0;
        $coursecustomfieldmappingindex = 0;
        $userprofilefieldmappingindex = 0;

        $decodedmapping = json_decode($attributemapping);
        foreach ($decodedmapping as $mapping) {
            switch ($mapping->table) {
                case 'course':
                    $defaultvalues['coursefieldmapping'][] = [
                        'index' => $coursefieldmappingindex,
                        'field' => isset($mapping->field) ? $mapping->field : '',
                        'accredibleattribute' => isset($mapping->accredibleattribute) ? $mapping->accredibleattribute : '',
                    ];
                    $coursefieldmappingindex++;
                    break;
                case 'customfield_field':
                    $defaultvalues['coursecustomfieldmapping'][] = [
                        'index' => $coursecustomfieldmappingindex,
                        'id' => isset($mapping->id) ? $mapping->id : '',
                        'accredibleattribute' => isset($mapping->accredibleattribute) ? $mapping->accredibleattribute : '',
                    ];
                    $coursecustomfieldmappingindex++;
                    break;
                case 'user_info_field':
                    $defaultvalues['userprofilefieldmapping'][] = [
                        'index' => $userprofilefieldmappingindex,
                        'id' => isset($mapping->id) ? $mapping->id : '',
                        'accredibleattribute' => isset($mapping->accredibleattribute) ? $mapping->accredibleattribute : '',
                    ];
                    $userprofilefieldmappingindex++;
                    break;
            }
        }
        return $defaultvalues;
    }

    /**
     * Retrieves an instance of attribute_keys.
     *
     * @return attribute_keys An instance of the attribute_keys client.
     */
    public function get_attribute_keys_client() {
        return  new attribute_keys();
    }

    /**
     * Reindex an associative array to have sequential numeric keys starting from 0.
     *
     * This function takes an associative array and returns a new array with
     * the same values but with sequential numeric keys starting from 0.
     *
     * @param array $array The associative array to be reindexed.
     * @return array The reindexed array with sequential numeric keys.
     */
    public function reindexarray($array) {
        if (!isset($array)) {
            return [];
        }
        $reindexarray = [];
        foreach ($array as $value) {
            $reindexarray[] = $value;
        }
        return $reindexarray;
    }
}
