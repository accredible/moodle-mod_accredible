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
use mod_accredible\local\attributemapping;

/**
 * Local functions related to attributemapping list.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attributemapping_list {
    /**
     * The apirest object used to call API requests.
     * @var apirest
     */
    private $apirest;

    /**
     * Array of attribute mapping objects.
     * @var $attributemapping[] attributemappings
     */
    public $attributemappings = array();


    /**
     * Constructor method
     *
     * @param attributemapping[] $attributemappings an array of mappings.
     * @param stdObject $apirest a mock apirest for testing.
     */
    public function __construct($attributemappings, $apirest = null) {
        // Handle validation.
        $this->validate_attributemapping($attributemappings);

        $this->attributemappings = $attributemappings;

        // A mock apirest is passed when unit testing.
        if ($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apirest();
        }
    }

    /**
     * Converts the attributemappings array into a string
     * @return string stringified version of attributemappings array
     */
    public function get_text_content() {
        $jsonarray = array();
        foreach ($this->attributemappings as $mapping) {
            $jsonarray[] = $mapping->get_db_object();
        }
        return json_encode($jsonarray);
    }

    /**
     * Validates a list of attribute mappings to ensure no duplicate accredible attributes exist.
     * @param attributemapping[] $attributemappings array of attribute mapping objects.
     * @throws InvalidArgumentException If a duplicate accredible attribute is found.
     */
    private function validate_attributemapping($attributemappings) {
        $uniqueattributes = [];
        foreach ($attributemappings as $attributemapping) {
            if (!$attributemapping->accredibleattribute) {
                continue;
            }

            if (in_array($attributemapping->accredibleattribute, $uniqueattributes)) {
                throw new \InvalidArgumentException(
                    "Duplicate accredibleattribute found: {$attributemapping->accredibleattribute}"
                );
            }
            $uniqueattributes[] = $attributemapping->accredibleattribute;
        }
    }
}
