<?php
// This file is part of Moodle - http://moodle.org/
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
 * Defines all the backup steps that will be used
 *
 * @package    mod_accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one accredible activity
 */
class restore_accredible_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define particular steps this activity can have
     */
    protected function define_structure() {

        $paths = [];

        $paths[] = new restore_path_element('accredible', '/activity/accredible');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process accredible activities to add new ones.
     * @param object $data old accredible activity
     */
    protected function process_accredible($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->gradeattributegradeitemid = $this->get_mappingid('grade_item', $data->gradeattributegradeitemid);
        $data->finalquiz = $this->get_mappingid('quiz', $data->finalquiz);

        // Insert the accredible record.
        $newitemid = $DB->insert_record('accredible', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Method to add any related file.
     */
    protected function after_execute() {
        // Add accredible related files, no need to match by itemname (just internally handled context).
    }
}
