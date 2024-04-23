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
 * Unit tests for mod/accredible/classes/local/attributemapping_list.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_attributemapping_list_test extends \advanced_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test whether it validates attribute mappings.
     */
    public function test_validate_attributemapping() {
        // When $accredibleattribute in mappings is duplicated.
        $accredibleattribute = 'grade';
        $mapping1 = new attributemapping('course', $accredibleattribute, 'fullname');
        $mapping2 = new attributemapping('user_info_field', $accredibleattribute, 'mooodle_user_id', 100);

        // Expect to raise an exception.
        $foundexception = false;
        try {
            $attributemappinglist = new attributemapping_list(array($mapping1, $mapping2));
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When $accredibleattribute in mappings is unique.
        $mapping2->accredibleattribute = 'user_id';

        // Expect attribute mappings to be set.
        $attributemappinglist = new attributemapping_list(array($mapping1, $mapping2));
        $this->assertEquals($mapping1, $attributemappinglist->attributemappings[0]);
        $this->assertEquals($mapping2, $attributemappinglist->attributemappings[1]);
    }

    /**
     * Test whether it converts attributemappings into a string.
     */
    public function test_get_text_content() {
        // When attributemapping_list has a valid value
        $mapping1 = new attributemapping('course', 'grade', 'fullname');
        $mapping2 = new attributemapping('user_info_field', 'user_id', 'mooodle_user_id', 100);

        $mapping1string = '{"table":"'.$mapping1->table.'","field":"'.$mapping1->field.'","accredibleattribute":"'.$mapping1->accredibleattribute.'"}';
        $mapping2string = '{"table":"'.$mapping2->table.'","field":"'.$mapping2->field.'","id":'.$mapping2->id.',"accredibleattribute":"'.$mapping2->accredibleattribute.'"}';

        $result = "[$mapping1string,$mapping2string]";
        
        // Expect strings to match.
        $attributemappinglist = new attributemapping_list(array($mapping1, $mapping2));
        $this->assertEquals($result, $attributemappinglist->get_text_content());
    }
}
