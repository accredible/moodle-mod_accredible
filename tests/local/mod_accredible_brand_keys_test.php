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
use mod_accredible\client\client;
use mod_accredible\local\brand_keys;

/**
 * Unit tests for mod/accredible/classes/local/brand_keys.php
 *
 * There is no site-wide Accredible account: every request belongs to a brand,
 * and anything that cannot name one has to fail rather than fall back.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_brand_keys_test extends \advanced_testcase {
    /**
     * US API endpoint.
     */
    const US_ENDPOINT = 'https://api.accredible.com/v1/';

    /**
     * EU API endpoint.
     */
    const EU_ENDPOINT = 'https://eu.api.accredible.com/v1/';

    /**
     * Setup before every test. The three production brands, all on the EU
     * region, which is how they are deployed.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->set_brand(1, 'CEAC', 'ceacapikey', 1);
        $this->set_brand(2, 'DEUSTO_FORMACION', 'deustoformacionapikey', 1);
        $this->set_brand(3, 'DEUSTO_SALUD', 'deustosaludapikey', 1);

        // Unset the development environment variable, which would otherwise
        // override the endpoint the brand resolves to.
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');
    }

    /**
     * Configure a brand slot.
     *
     * @param int $slot
     * @param string $name
     * @param string $apikey
     * @param int $iseu
     * @return void
     */
    private function set_brand($slot, $name, $apikey, $iseu = 1) {
        set_config("accredible_brand{$slot}_name", $name);
        set_config("accredible_brand{$slot}_api_key", $apikey);
        set_config("accredible_brand{$slot}_is_eu", $iseu);
    }

    /**
     * Clear every brand slot.
     *
     * @return void
     */
    private function clear_brands() {
        for ($slot = 1; $slot <= brand_keys::SLOTS; $slot++) {
            $this->set_brand($slot, '', '', 0);
        }
    }

    /**
     * Return the Authorization header a client builds for a given key.
     *
     * @param string|null $apikey
     * @return string|null
     */
    private function auth_header($apikey) {
        $curl = new class {
            /**
             * Transport error, always none here.
             * @var string|null $error
             */
            public $error = null;
            /**
             * Response info.
             * @var array $info
             */
            public $info = ['http_code' => 200];
            /**
             * Options captured from the last request.
             * @var array|null $options
             */
            public $options = null;
            /**
             * Record the request options instead of hitting the network.
             *
             * @param string $url
             * @param mixed $data
             * @param array $options
             * @return string
             */
            public function get($url, $data = null, $options = []) {
                $this->options = $options;
                return '{}';
            }
        };

        $client = new client($curl, $apikey);
        $client->get('https://example.invalid/');

        foreach ($curl->options['CURLOPT_HTTPHEADER'] as $header) {
            if (strpos($header, 'Authorization:') === 0) {
                return $header;
            }
        }

        return null;
    }

    /**
     * The three brands resolve to their own account, on the EU region.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     * @covers \mod_accredible\apirest\apirest::for_brand
     */
    public function test_each_brand_resolves_to_its_own_account(): void {
        $expected = [
            'CEAC' => 'ceacapikey',
            'DEUSTO_FORMACION' => 'deustoformacionapikey',
            'DEUSTO_SALUD' => 'deustosaludapikey',
        ];

        foreach ($expected as $brand => $key) {
            $resolved = brand_keys::for_brand($brand);
            $this->assertSame($key, $resolved['api_key'], "brand {$brand} resolved to the wrong key");
            $this->assertTrue($resolved['is_eu'], "brand {$brand} lost its EU region");
            $this->assertEquals(self::EU_ENDPOINT, apirest::for_brand($brand)->apiendpoint);
            $this->assertSame("Authorization: Token {$key}", $this->auth_header($key));
        }

        // Three brands, three distinct keys.
        $this->assertCount(3, array_unique(array_values($expected)));
    }

    /**
     * Brand lookup ignores case, so a category idnumber that differs only in
     * casing still finds its account.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_lookup_is_case_insensitive(): void {
        $this->assertSame('ceacapikey', brand_keys::for_brand('ceac')['api_key']);
        $this->assertSame('deustosaludapikey', brand_keys::for_brand('Deusto_Salud')['api_key']);
    }

    /**
     * A request with no brand has nowhere to go and must fail.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_missing_brand_throws(): void {
        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand(null);
    }

    /**
     * The same for the empty string, which is what an unanswered form select
     * submits.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_empty_brand_throws(): void {
        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand('');
    }

    /**
     * A brand that is not configured must fail rather than resolve to some
     * other account.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_unknown_brand_throws(): void {
        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand('NOT_A_BRAND');
    }

    /**
     * A slot with a name but no key cannot authenticate, so it fails too.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     * @covers \mod_accredible\local\brand_keys::is_usable
     */
    public function test_brand_without_key_throws(): void {
        $this->set_brand(3, 'DEUSTO_SALUD', '', 1);

        $this->assertFalse(brand_keys::is_usable('DEUSTO_SALUD'));

        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand('DEUSTO_SALUD');
    }

    /**
     * is_usable() answers without throwing, for callers that need to branch.
     *
     * @covers \mod_accredible\local\brand_keys::is_usable
     */
    public function test_is_usable(): void {
        $this->assertTrue(brand_keys::is_usable('CEAC'));
        $this->assertTrue(brand_keys::is_usable('ceac'));
        $this->assertFalse(brand_keys::is_usable(''));
        $this->assertFalse(brand_keys::is_usable(null));
        $this->assertFalse(brand_keys::is_usable('NOT_A_BRAND'));
    }

    /**
     * A client that was built without a key must refuse to make the request
     * instead of sending an empty Authorization header.
     *
     * @covers \mod_accredible\client\client
     */
    public function test_client_without_key_refuses_to_send(): void {
        $client = new client(null, '');

        $this->expectException(\coding_exception::class);
        $client->get('https://example.invalid/');
    }

    /**
     * The listing skips empty slots and reports whether anything is set up.
     *
     * @covers \mod_accredible\local\brand_keys::all
     * @covers \mod_accredible\local\brand_keys::menu
     * @covers \mod_accredible\local\brand_keys::is_configured
     */
    public function test_listing(): void {
        $this->assertTrue(brand_keys::is_configured());
        $this->assertSame(
            ['CEAC', 'DEUSTO_FORMACION', 'DEUSTO_SALUD'],
            array_keys(brand_keys::menu())
        );

        $this->set_brand(2, '', '', 0);
        $this->assertSame(['CEAC', 'DEUSTO_SALUD'], array_keys(brand_keys::menu()));

        $this->clear_brands();
        $this->assertFalse(brand_keys::is_configured());
        $this->assertSame([], brand_keys::menu());
    }

    /**
     * A course sitting directly in a brand's root category resolves to that
     * brand.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_in_root_category_resolves_its_brand(): void {
        $root = $this->getDataGenerator()->create_category(['name' => 'CEAC', 'idnumber' => 'CEAC']);
        $course = $this->getDataGenerator()->create_course(['category' => $root->id]);

        $this->assertSame('CEAC', brand_keys::root_idnumber_for_course($course));
        $this->assertSame('CEAC', brand_keys::brand_from_course($course));
        $this->assertSame('CEAC', brand_keys::brand_from_course($course->id));
        $this->assertSame('ceacapikey', brand_keys::for_brand(brand_keys::brand_from_course($course))['api_key']);
    }

    /**
     * A course nested under subcategories resolves through its path up to the
     * root, which is the only level carrying an idnumber.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_in_nested_category_resolves_root_brand(): void {
        // CEAC > Musica > DJ, mirroring the real category tree.
        $root = $this->getDataGenerator()->create_category(['name' => 'CEAC', 'idnumber' => 'CEAC']);
        $middle = $this->getDataGenerator()->create_category(['name' => 'Musica', 'parent' => $root->id]);
        $leaf = $this->getDataGenerator()->create_category(['name' => 'DJ', 'parent' => $middle->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $leaf->id]);

        // The subcategories deliberately carry no idnumber of their own.
        $this->assertEmpty($middle->idnumber);
        $this->assertEmpty($leaf->idnumber);
        $this->assertEquals(3, $leaf->depth);

        $this->assertSame('CEAC', brand_keys::brand_from_course($course));
        $this->assertEquals(self::EU_ENDPOINT, apirest::for_brand(brand_keys::brand_from_course($course))->apiendpoint);
    }

    /**
     * A root category with no idnumber names no brand. The activity form turns
     * that into a validation error; nothing resolves to an account.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     */
    public function test_course_without_root_idnumber_names_no_brand(): void {
        $root = $this->getDataGenerator()->create_category(['name' => 'Sin idnumber']);
        $leaf = $this->getDataGenerator()->create_category(['name' => 'Interna', 'parent' => $root->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $leaf->id]);

        $this->assertSame('', brand_keys::root_idnumber_for_course($course));
        $this->assertNull(brand_keys::brand_from_course($course));

        $this->expectException(\moodle_exception::class);
        brand_keys::for_brand(brand_keys::brand_from_course($course));
    }

    /**
     * A root idnumber naming a brand that is not configured is reported
     * verbatim, but resolves to no brand.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_root_idnumber_not_matching_any_brand(): void {
        $root = $this->getDataGenerator()->create_category(['name' => 'Otra', 'idnumber' => 'CEAC_TYPO']);
        $course = $this->getDataGenerator()->create_course(['category' => $root->id]);

        // The raw idnumber is still visible, which is what tells a typo apart
        // from an unconfigured category.
        $this->assertSame('CEAC_TYPO', brand_keys::root_idnumber_for_course($course));
        $this->assertNull(brand_keys::brand_from_course($course));
    }

    /**
     * Courses outside the category tree must not blow up.
     *
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_outside_category_tree(): void {
        $this->assertSame('', brand_keys::root_idnumber_for_course(null));
        $this->assertSame('', brand_keys::root_idnumber_for_course(0));
        $this->assertSame('', brand_keys::root_idnumber_for_course(SITEID));
        $this->assertNull(brand_keys::brand_from_course(null));
    }
}
