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
     * Builds a JSON encoded attribute mapping list to be stored in the DB based on the provided post data.
     *
     * @param object $post The post data containing the course field mappings, course custom field mappings,
     * and user field mappings.
     * @return string JSON encoded attribute mapping list.
     */
    private function build_attribute_mapping_list($post) {
        // Combine all the mappings into a single array. Expects empty arrays if no mappings are present.
        $mergedmappings = array_merge($post->coursefieldmapping, $post->coursecustomfieldmapping, $post->userfieldmapping);

        if (empty($mergedmappings)) {
            return null;
        }

        $attributemappings = array_map(function($mapping) {
            return new attributemapping($mapping->table, $mapping->accredibleattribute, $mapping->field, $mapping->id);
        }, $mergedmappings);

        $attributemappinglist = new attributemapping_list($attributemappings);
        return $attributemappinglist->get_text_content();
    }
}
