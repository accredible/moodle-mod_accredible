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
 * Defines the complete accredible structure for backup, with file and id annotations
 */
class backup_accredible_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {
        // XML nodes declaration.
        $accredible = new backup_nested_element('accredible', ['id'], [
            'name', 'course', 'achievementid', 'description', 'finalquiz', 'passinggrade', 'completionactivities',
            'includegradeattribute', 'gradeattributegradeitemid', 'gradeattributekeyname', 'groupid',
            'attributemapping',
        ]);

        // Data sources - non-user data.
        $accredible->set_source_table('accredible', ['id' => backup::VAR_ACTIVITYID]);

        // Id annotations.
        $accredible->annotate_ids('quiz', 'finalquiz');
        $accredible->annotate_ids('grade_item', 'gradeattributegradeitemid');

        return $this->prepare_activity_structure($accredible);
    }
}
