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

defined('MOODLE_INTERNAL') || die();

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
     * @param stdClass|null $accrediblecertificate Optional. Existing certificate data to be updated.
     * @param int|null $groupid Optional. The group ID associated with the plugin instance.
     * @return bool|int Returns the new record ID on insert, or true on update success.
     */
    public function save_record($post, $accrediblecertificate = null, $groupid = null) {
        global $DB;
        
        $dbrecord = new \stdClass();
        $dbrecord->completionactivities = isset($post->completionactivities) ? $post->completionactivities : null;
        $dbrecord->name = $post->name;
        $dbrecord->course = $post->course;
        $dbrecord->finalquiz = $post->finalquiz;
        $dbrecord->passinggrade = $post->passinggrade;
        $dbrecord->includegradeattribute = isset($post->includegradeattribute) ? $post->includegradeattribute : 0;
        $dbrecord->gradeattributegradeitemid = $post->gradeattributegradeitemid;
        $dbrecord->gradeattributekeyname = $post->gradeattributekeyname;
        $dbrecord->groupid = $post->groupid;
       
        if ($post->instance) {
            // Update the existing record if an instance ID is present.
            $dbrecord->id = $post->instance;

            if ($accrediblecertificate->achievementid) {
                $dbrecord->certificatename = $post->certificatename;
                $dbrecord->description = $post->description;
                $dbrecord->achievementid = $post->achievementid;
            } else {
                $dbrecord->course = $post->course;
                $dbrecord->groupid = $groupid;
                $dbrecord->timecreated = time();
            }

            return $DB->update_record('accredible', $dbrecord);
        } else {
            // Insert a new record if no instance ID is present.
            $dbrecord->timecreated = time();

            return $DB->insert_record('accredible', $dbrecord);
        }
    }
}
