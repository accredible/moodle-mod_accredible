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
 * Local functions related to attribute keys.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attribute_keys {
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
    public function __construct($apirest = null) {
        // An apirest with a mock client is passed when unit testing.
        if ($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apirest();
        }
    }

    /**
     * Get the attribute keys for the issuer
     * @return array[stdClass] $attributekeys
     */
    public function get_attribute_keys() {
        $pagesize = 50;
        $page = 1;

        try {
            $attributekeys = array();
            // Query the Accredible API and loop until it returns that there is no next page.
            for ($i = 0; $i <= 100; $i++) {
                $response = $this->apirest->search_attribute_keys($pagesize, $page);
                foreach ($response->attribute_keys as $attributekey) {
                    $attributekeys[$attributekey->name] = $attributekey->name;
                }

                $page++;
                if ($response->meta->next_page === null) {
                    // If the Accredible API returns that there
                    // is no next page, end the loop.
                    break;
                }
            }
            return $attributekeys;
        } catch (\Exception $e) {
            throw new \moodle_exception('getattributekeysserror', 'accredible', 'https://help.accredible.com/hc/en-us');
        }
    }
}
