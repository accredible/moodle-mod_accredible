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
 * Unit tests for mod/accredible/classes/local/accredible.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_accredible_test extends \advanced_testcase {
    /**
     * @var The accredible instance.
     */
    protected $accredible;

    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->accredible = new accredible();
    }

    /**
     * Test save_record method.
     * @covers ::save_record
     */
    public function test_save_record() {
        global $DB;

        // When creating a new record.
        $post = $this->generatePostObject();

        // Set up the expectation for the insert_record method.
        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('insert_record')
            ->with('accredible', $this->anything())
            ->willReturn(1);

        $result = $this->accredible->save_record($post);
        $this->assertEquals(1, $result);

        // When updating an existing record.
        $overrides = new \stdClass();
        $overrides->name = 'Updated Certificate';
        $overrides->instance = 1;
        $post = $this->generatePostObject($overrides);

        $accrediblecertificate = new \stdClass();
        $accrediblecertificate->achievementid = null;

        // Simulating update_record return value.
        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('update_record')
            ->with('accredible', $this->anything())
            ->willReturn(true);

        $result = $this->accredible->save_record($post, $accrediblecertificate);
        $this->assertTrue($result);

        // When attribute mappings are empty.
        $overrides = new \stdClass();
        $overrides->coursefieldmapping = [];
        $overrides->coursecustomfieldmapping = [];
        $overrides->userfieldmapping = [];
        $post = $this->generatePostObject($overrides);

        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('insert_record')
            ->with(
                'accredible',
                $this->callback(function($subject) {
                    return $subject->attributemapping === null;
                })
            )
            ->willReturn(true);

        $result = $this->accredible->save_record($post);
        $this->assertEquals(1, $result);

        // When attribute mappings are present.
        $attributemapping = new attributemapping('course', 'Moodle Course Start Date', 'startdate');

        $overrides = new \stdClass();
        $overrides->coursefieldmapping = [$attributemapping];
        $overrides->coursecustomfieldmapping = [];
        $overrides->userfieldmapping = [];
        $post = $this->generatePostObject($overrides);

        $DB = $this->createMock(\moodle_database::class);
        $DB->expects($this->once())
            ->method('insert_record')
            ->with(
            'accredible',
            $this->callback(function($subject) {
                // Check if attributemapping is a string and is an array afer decoding.
                return is_string($subject->attributemapping) && is_array(json_decode($subject->attributemapping, true));
            })
        )
        ->willReturn(true);

        $result = $this->accredible->save_record($post);
        $this->assertEquals(1, $result);
    }

    /**
     * Generates a mock $post object for testing.
     *
     * @param stdClass $overrides An object with properties to override.
     * @return stdClass The generated $post object.
     */
    private function generatepostobject(\stdClass $overrides = null): \stdClass {
        $post = (object) [
            'name' => 'New Certificate',
            'instance' => null,
            'course' => 101,
            'finalquiz' => 5,
            'passinggrade' => 70,
            'completionactivities' => null,
            'includegradeattribute' => 1,
            'gradeattributegradeitemid' => 10,
            'gradeattributekeyname' => 'Final Grade',
            'groupid' => 1,
            'coursefieldmapping' => [],
            'coursecustomfieldmapping' => [],
            'userfieldmapping' => []
        ];

        // Apply overrides.
        if ($overrides) {
            foreach ($overrides as $property => $value) {
                $post->$property = $value;
            }
        }

        return $post;
    }
}
