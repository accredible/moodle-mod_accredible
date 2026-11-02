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

namespace mod_accredible\backup;

/**
 * Unit tests for restoring accredible activities, run through Duplicate activity.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_restore_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Values this plugin writes survive a duplicate unchanged.
     *
     * @dataProvider duplicate_keeps_completionactivities_provider
     * @covers \restore_accredible_activity_structure_step::process_accredible
     * @param string|null $completionactivities stored value
     */
    public function test_duplicate_keeps_completionactivities($completionactivities): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course->id, $completionactivities);

        $newcm = duplicate_module($course, $cm);

        $restored = $DB->get_record('accredible', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame($completionactivities, $restored->completionactivities);
        $this->assertEquals(70, $restored->passinggrade);
    }

    /**
     * Stored completionactivities values to duplicate.
     *
     * @return array
     */
    public static function duplicate_keeps_completionactivities_provider(): array {
        return [
            'checked' => ['1'],
            'unchecked' => ['0'],
            'null' => [null],
            'legacy map' => [base64_encode(serialize([12 => false, 34 => true]))],
        ];
    }

    /**
     * Create an accredible activity with its course module.
     *
     * @param int $courseid
     * @param string|null $completionactivities
     * @return \stdClass course module
     */
    private function create_activity($courseid, $completionactivities): \stdClass {
        global $DB;

        $instanceid = $DB->insert_record('accredible', (object) [
            'name' => 'Accredible Test',
            'course' => $courseid,
            'finalquiz' => 0,
            'passinggrade' => 70,
            'completionactivities' => $completionactivities,
            'includegradeattribute' => 0,
            'timecreated' => time(),
            'certificatename' => 'Accredible Test',
        ]);
        $cm = (object) [
            'course' => $courseid,
            'module' => $DB->get_field('modules', 'id', ['name' => 'accredible'], MUST_EXIST),
            'instance' => $instanceid,
            'section' => 0,
        ];
        $cmid = add_course_module($cm);
        course_add_cm_to_section($courseid, $cmid, 0);
        return get_coursemodule_from_id('accredible', $cmid, 0, false, MUST_EXIST);
    }
}
