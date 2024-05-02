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
}
