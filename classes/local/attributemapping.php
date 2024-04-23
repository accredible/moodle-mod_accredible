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
 * Local functions related to attributemapping.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attributemapping {

    /**
     * The name of the table (must be one of 'course', 'user_info_field', 'customfield_field')
     * @var string table
     */
    public $table;

    /**
     * The field name in the table (null for 'user_info_field' and 'customfield_field')
     * @var string|null field
     */
    public $field;

    /**
     * The unique identifier (required for 'user_info_field' and 'customfield_field')
     * @var int|null id
     */
    public $id;

    /**
     * The name of the attribute in Accredible
     * @var string accredibleattribute
     */
    public $accredibleattribute;

    /**
     * Constructor method
     *
     * @param string $table
     * @param string $accredibleattribute
     * @param string|null $field
     * @param int|null $id
     * @throws InvalidArgumentException If any validation fails
     */
    public function __construct($table, $accredibleattribute, $field = null, $id = null) {
        // Handle validation.
        $this->validate_table($table);
        $this->validate_field($table, $field);
        $this->validate_id($table, $id);

        $this->table = $table;
        $this->field = $field;
        $this->id = $id;
        $this->accredibleattribute = $accredibleattribute;
    }

    /**
     * Updates the attributemapping object into an object suitable for saving into the db.
     * @return stdObject updated attributemapping object
     */
    public function get_db_object() {
        // Remove empty values.
        $object = (object) array_filter((array) $this);
        return $object;
    }

    /**
     * Validates if the provided table name is valid.
     * @param string $table
     * @throws InvalidArgumentException If the table name is invalid
     */
    private function validate_table($table) {
        $validtables = ["course", "user_info_field", "customfield_field"];
        if (!in_array($table, $validtables)) {
            throw new \InvalidArgumentException("Invalid table value");
        }
    }

    /**
     * Validates if the field value is correct based on the table name.
     * @param string $table
     * @param string|null $field
     * @throws InvalidArgumentException If the field name is invalid
     */
    private function validate_field($table, $field) {
        if ($table === "course") {
            // Valid fields for the 'course' table.
            $validfields = ["fullname", "shortname", "startdate", "enddate"];
            if (!in_array($field, $validfields)) {
                throw new \InvalidArgumentException("Invalid field value for the 'course' table");
            }
        }
    }

    /**
     * Validates if the ID is required and correct based on the table name.
     * @param string $table
     * @param int|null $id
     * @throws InvalidArgumentException If the ID is invalid
     */
    private function validate_id($table, $id) {
        if (($table === "user_info_field" || $table === "customfield_field") && $id === null) {
            throw new \InvalidArgumentException("Id is required for the '$table' table");
        }
    }

}
