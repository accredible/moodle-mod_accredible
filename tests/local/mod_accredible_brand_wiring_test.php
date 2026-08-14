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

use mod_accredible\local\accredible;
use mod_accredible\local\brand_keys;

/**
 * Tests that the brand reaches the account actually used: persistence of the
 * brand on the activity, and no crossing between brands.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_brand_wiring_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('accredible_brand1_name', 'CEAC');
        set_config('accredible_brand1_api_key', 'ceacapikey');
        set_config('accredible_brand1_is_eu', 1);
        set_config('accredible_brand2_name', 'DEUSTO_FORMACION');
        set_config('accredible_brand2_api_key', 'deustoformacionapikey');
        set_config('accredible_brand2_is_eu', 1);
        set_config('accredible_brand3_name', 'DEUSTO_SALUD');
        set_config('accredible_brand3_api_key', 'deustosaludapikey');
        set_config('accredible_brand3_is_eu', 1);

        putenv('ACCREDIBLE_DEV_API_ENDPOINT');
    }

    /**
     * Build the minimum post object save_record() needs.
     *
     * @param int $courseid
     * @param string|null $brand
     * @return \stdClass
     */
    private function post_for($courseid, $brand) {
        $post = new \stdClass();
        $post->name = 'Certificado';
        $post->course = $courseid;
        $post->finalquiz = 0;
        $post->passinggrade = 0;
        $post->groupid = 111;
        $post->certificatename = 'Certificado';
        if ($brand !== null) {
            $post->brand = $brand;
        }
        return $post;
    }

    /**
     * The brand chosen on the form is stored on the activity.
     *
     * @covers \mod_accredible\local\accredible::save_record
     */
    public function test_brand_is_persisted_on_the_activity(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $recordid = (new accredible())->save_record($this->post_for($course->id, 'CEAC'));

        $stored = $DB->get_record('accredible', ['id' => $recordid], 'id, brand, groupid');
        $this->assertSame('CEAC', $stored->brand);
    }

    /**
     * A stored brand resolves to its own account, even when the course
     * category names a different one.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_stored_brand_selects_its_own_account(): void {
        global $DB;

        $category = $this->getDataGenerator()->create_category(['name' => 'CEAC', 'idnumber' => 'CEAC']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $recordid = (new accredible())->save_record($this->post_for($course->id, 'DEUSTO_SALUD'));
        $record = $DB->get_record('accredible', ['id' => $recordid]);

        // The manual override wins over the category, which says CEAC.
        $this->assertSame('deustosaludapikey', brand_keys::for_brand($record->brand)['api_key']);
    }

    /**
     * An activity saved without a brand has no account to reach, so resolving
     * it fails instead of quietly using someone else's.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_activity_without_brand_has_no_account(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $recordid = (new accredible())->save_record($this->post_for($course->id, ''));
        $record = $DB->get_record('accredible', ['id' => $recordid]);

        $this->assertNull($record->brand);

        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand($record->brand);
    }

    /**
     * An apirest double that answers get_groups() with a single page of the
     * given groups, as one brand's account would.
     *
     * @param array<int, string> $groups id => name
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function apirest_serving_groups(array $groups) {
        $response = (object) [
            'groups' => array_map(
                static fn($id, $name) => (object) ['id' => $id, 'name' => $name],
                array_keys($groups),
                array_values($groups)
            ),
            'meta' => (object) ['next_page' => null],
        ];

        $api = $this->getMockBuilder(\mod_accredible\apirest\apirest::class)
            ->onlyMethods(['get_groups', 'detect_error'])
            ->disableOriginalConstructor()
            ->getMock();
        $api->method('get_groups')->willReturn($response);
        $api->method('detect_error')->willReturn(null);

        return $api;
    }

    /**
     * The group picker only ever offers the selected brand's groups.
     *
     * The search box on that field filters in the browser over the options
     * already rendered, so whatever the account does not return simply cannot
     * be found -- which is what keeps one brand's groups out of another's list.
     *
     * @covers \mod_accredible\local\groups::get_groups
     */
    public function test_group_list_is_scoped_to_the_brand_account(): void {
        $ceac = (new groups($this->apirest_serving_groups([
            93516 => 'Prueba plataforma',
            90001 => 'CEAC Diseño',
        ])))->get_groups();

        $salud = (new groups($this->apirest_serving_groups([
            93518 => 'Prueba plataforma deusto salud',
            90002 => 'Deusto Salud Nutrición',
        ])))->get_groups();

        $this->assertEqualsCanonicalizing([93516, 90001], array_keys($ceac));
        $this->assertEqualsCanonicalizing([93518, 90002], array_keys($salud));

        // Listed by name, which is the order the picker shows and searches.
        $this->assertSame(['CEAC Diseño', 'Prueba plataforma'], array_values($ceac));
        $this->assertSame(['Deusto Salud Nutrición', 'Prueba plataforma deusto salud'], array_values($salud));

        // No group id and no group name is offered by both accounts.
        $this->assertEmpty(array_intersect_key($ceac, $salud));
        $this->assertEmpty(array_intersect($ceac, $salud));
    }

    /**
     * With no brand resolved there is nothing to search: the field is built
     * from an empty list rather than from some other account's groups.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     */
    public function test_no_brand_means_no_groups_to_search(): void {
        $category = $this->getDataGenerator()->create_category(['name' => 'Sin marca']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        $this->assertNull(brand_keys::brand_from_course($course));
        $this->assertFalse(brand_keys::is_usable(brand_keys::brand_from_course($course)));
    }

    /**
     * The three brands never resolve to each other's account.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_no_crossing_between_the_three_brands(): void {
        $expected = [
            'CEAC' => 'ceacapikey',
            'DEUSTO_FORMACION' => 'deustoformacionapikey',
            'DEUSTO_SALUD' => 'deustosaludapikey',
        ];

        $seen = [];
        foreach ($expected as $brand => $key) {
            $resolved = brand_keys::for_brand($brand);
            $this->assertSame($key, $resolved['api_key'], "brand {$brand} resolved to the wrong key");
            $this->assertTrue($resolved['is_eu']);
            $seen[] = $resolved['api_key'];

            // No other brand's key can answer for this one.
            foreach ($expected as $otherbrand => $otherkey) {
                if ($otherbrand !== $brand) {
                    $this->assertNotSame($otherkey, $resolved['api_key']);
                }
            }
        }

        // Three brands, three distinct keys.
        $this->assertCount(3, array_unique($seen));
    }

    /**
     * Each of the three brand categories preselects its own brand, and a course
     * outside them preselects none.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     */
    public function test_each_category_preloads_its_own_brand(): void {
        foreach (['CEAC', 'DEUSTO_FORMACION', 'DEUSTO_SALUD'] as $brand) {
            $category = $this->getDataGenerator()->create_category(['name' => $brand, 'idnumber' => $brand]);
            $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

            $this->assertSame($brand, brand_keys::brand_from_course($course));
        }

        $other = $this->getDataGenerator()->create_category(['name' => 'Sin marca']);
        $othercourse = $this->getDataGenerator()->create_course(['category' => $other->id]);
        $this->assertNull(brand_keys::brand_from_course($othercourse));
    }

    /**
     * The brand is derived from the activity, or from its course category
     * while the activity does not exist yet. This is what lets the web service
     * pick the right account without the client ever sending a brand.
     *
     * @covers \mod_accredible\local\brand_keys::brand_for_activity
     */
    public function test_brand_is_derived_without_the_client_sending_it(): void {
        $category = $this->getDataGenerator()->create_category(['name' => 'CEAC', 'idnumber' => 'CEAC']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        // Creating a new activity: the category answers.
        $this->assertSame('CEAC', brand_keys::brand_for_activity(0, $course->id));
        $this->assertSame('CEAC', brand_keys::brand_for_activity(null, $course->id));

        // Editing one with a manual override: the stored value wins.
        $recordid = (new accredible())->save_record($this->post_for($course->id, 'DEUSTO_SALUD'));
        $this->assertSame('DEUSTO_SALUD', brand_keys::brand_for_activity($recordid, $course->id));
        $this->assertSame(
            'deustosaludapikey',
            brand_keys::for_brand(brand_keys::brand_for_activity($recordid, $course->id))['api_key']
        );

        // An activity stored without a brand falls back to what its category
        // says, which is the only answer available.
        $nobrandid = (new accredible())->save_record($this->post_for($course->id, ''));
        $this->assertSame('CEAC', brand_keys::brand_for_activity($nobrandid, $course->id));
    }
}
