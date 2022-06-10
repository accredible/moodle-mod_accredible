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

use mod_accredible\apirest\apirest;

/**
 * Local functions related to users.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users {
    /**
     * The apirest object used to call API requests.
     * @var apirest
     */
    private $apirest;

    /**
     * Constructor method.
     *
     * @param stdObject $apirest a mock apirest for testing.
     */
    public function __construct($apirest = null, $rand = null) {
        // An apirest with a mock client is passed when unit testing.
        if ($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apirest();
        }

        // A fixed value is passed when unit testing.
        if ($rand) {
            $this->rand = $rand;
        } else {
            $this->rand = mt_rand();
        }
    }

    /**
     * Get user grades from grade item.
     * @param stdObject $data data from the submission of mod_form.
     * @return array[stdClass] $gradeattributes
     */
    public function get_user_grades($data) {
        global $DB;

        if (isset($data->includegradeattribute) && isset($data->gradeattributegradeitemid) && isset($data->gradeattributekeyname)) {
            $users = array_keys($data->users);
            $assigment = $DB->get_record('grade_items', array('id' => $data->gradeattributegradeitemid), '*', MUST_EXIST);
            $grades = grade_get_grades($data->course, $assigment->itemtype, $assigment->itemmodule, $assigment->iteminstance, $users);
            $gradeattributes = isset($grades->items[0]->grades) ? $grades->items[0]->grades : null;

            return $gradeattributes;
        }
    }
}
