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

namespace mod_accredible\local;

/**
 * Unit tests for mod/accredible/classes/local/attributemapping.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_attributemapping_test extends \advanced_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test whether it validates table property.
     * @covers  ::validate_table
     */
    public function test_validate_table() {
        // When $table has an invalid value.
        $table = 'invalid';
        $accredibleattribute = 'grade';

        // Expect to raise an exception.
        $foundexception = false;
        try {
            $attributemapping = new attributemapping($table, $accredibleattribute);
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When $table has a valid value.
        $table = 'course';
        $field = 'fullname';

        // Expect $table value to be set.
        $attributemapping = new attributemapping($table, $accredibleattribute, $field);
        $this->assertEquals($table, $attributemapping->table);
    }

    /**
     * Test whether it validates field property.
     * @covers  ::validate_field
     */
    public function test_validate_field() {
        // When $table is course and $field has an invalid value.
        $table = 'course';
        $field = 'incorrect_field';
        $accredibleattribute = 'grade';

        // Expect to raise an exception.
        $foundexception = false;
        try {
            $attributemapping = new attributemapping($table, $accredibleattribute, $field);
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When $table is course and $field has a valid value.
        $table = 'course';
        $field = 'fullname';

        // Expect $field value to be set.
        $attributemapping = new attributemapping($table, $accredibleattribute, $field);
        $this->assertEquals($field, $attributemapping->field);
    }

    /**
     * Test whether it validates id property.
     * @covers  ::validate_id
     */
    public function test_validate_id() {
        // When $table is user_info_field and has no $id value.
        $table = 'user_info_field';
        $field = 'age';
        $accredibleattribute = 'grade';

        // Expect to raise an exception.
        $foundexception = false;
        try {
            $attributemapping = new attributemapping($table, $accredibleattribute, $field);
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When $table is customfield_field and has no $id value.
        $table = 'customfield_field';

        // Expect to raise an exception.
        $foundexception = false;
        try {
            $attributemapping = new attributemapping($table, $accredibleattribute, $field);
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When $table is user_info_field and $id has value.
        $table = 'user_info_field';
        $id = 120;

        // Expect $id value to be set.
        $attributemapping = new attributemapping($table, $accredibleattribute, $field, $id);
        $this->assertEquals($id, $attributemapping->id);

        // When $table is customfield_field and $id has value.
        $table = 'customfield_field';
        $id = 100;

        // Expect $id value to be set.
        $attributemapping = new attributemapping($table, $accredibleattribute, $field, $id);
        $this->assertEquals($id, $attributemapping->id);
    }

    /**
     * Test whether it returns an object with no null/empty values.
     * @covers  ::get_db_object
     */
    public function test_get_db_object() {
        // When $table has a valid value.
        $table = 'course';
        $field = 'fullname';
        $accredibleattribute = 'grade';

        // Expect returned object to not have id.
        $attributemapping = new attributemapping($table, $accredibleattribute, $field, null);
        $this->assertTrue(!property_exists($attributemapping->get_db_object(), 'id'));
    }
}
