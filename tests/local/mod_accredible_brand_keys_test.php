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
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // The legacy global account every fallback should land on.
        set_config('accredible_api_key', 'globaltestapikey');
        set_config('is_eu', 0);

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
    private function set_brand($slot, $name, $apikey, $iseu = 0) {
        set_config("accredible_brand{$slot}_name", $name);
        set_config("accredible_brand{$slot}_api_key", $apikey);
        set_config("accredible_brand{$slot}_is_eu", $iseu);
    }

    /**
     * Return the Authorization header a client builds for a given key.
     *
     * The header is assembled in the constructor and kept private, so we drive
     * a request through a recording curl double to read it back.
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
     * With no brands configured the plugin must behave exactly as it did
     * before multi-brand support: the global key, for every input.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     * @covers \mod_accredible\local\brand_keys::is_configured
     */
    public function test_no_brands_configured_uses_global(): void {
        $this->assertFalse(brand_keys::is_configured());
        $this->assertSame([], brand_keys::menu());

        $this->assertSame('globaltestapikey', brand_keys::for_brand('Northius')['api_key']);
        $this->assertSame('globaltestapikey', brand_keys::for_brand(null)['api_key']);

        $api = new apirest();
        $this->assertEquals(self::US_ENDPOINT, $api->apiendpoint);
        $this->assertSame('Authorization: Token globaltestapikey', $this->auth_header(null));

        // The global region still drives the endpoint.
        set_config('is_eu', 1);
        $api = new apirest();
        $this->assertEquals(self::EU_ENDPOINT, $api->apiendpoint);
    }

    /**
     * An activity with no brand stored (the state every pre-existing row is in
     * after the upgrade) falls back to the global account.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_null_brand_falls_back_to_global(): void {
        $this->set_brand(1, 'Northius', 'northiusapikey', 1);

        $this->assertSame('globaltestapikey', brand_keys::for_brand(null)['api_key']);
        $this->assertFalse(brand_keys::for_brand(null)['is_eu']);

        $this->assertSame('globaltestapikey', brand_keys::for_brand('')['api_key']);

        $api = apirest::for_brand(null);
        $this->assertEquals(self::US_ENDPOINT, $api->apiendpoint);
    }

    /**
     * A brand name that matches no configured slot falls back to the global
     * account rather than failing.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_unknown_brand_falls_back_to_global(): void {
        $this->set_brand(1, 'Northius', 'northiusapikey', 1);

        $this->assertSame('globaltestapikey', brand_keys::for_brand('DoesNotExist')['api_key']);
        $this->assertFalse(brand_keys::for_brand('DoesNotExist')['is_eu']);

        $api = apirest::for_brand('DoesNotExist');
        $this->assertEquals(self::US_ENDPOINT, $api->apiendpoint);
    }

    /**
     * A slot with a name but no key is half-configured. It must degrade to the
     * global account instead of sending an empty Authorization header.
     *
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_brand_without_key_falls_back_to_global(): void {
        $this->set_brand(1, 'BrandWithoutKey', '', 1);

        $resolved = brand_keys::for_brand('BrandWithoutKey');
        $this->assertSame('globaltestapikey', $resolved['api_key']);
        $this->assertFalse($resolved['is_eu']);

        // The slot is still listed: it is configured, just not usable yet.
        $this->assertArrayHasKey('BrandWithoutKey', brand_keys::menu());

        $this->assertSame(
            'Authorization: Token globaltestapikey',
            $this->auth_header($resolved['api_key'])
        );
    }

    /**
     * Key and region always travel together, so a brand can never authenticate
     * against the wrong regional endpoint.
     *
     * @covers \mod_accredible\apirest\apirest::for_brand
     * @covers \mod_accredible\local\brand_keys::for_brand
     */
    public function test_key_and_region_are_resolved_together(): void {
        $this->set_brand(1, 'Northius', 'northiusapikey', 1);
        $this->set_brand(2, 'CampusTraining', 'campusapikey', 0);

        // EU brand: EU endpoint, EU key.
        $this->assertSame('northiusapikey', brand_keys::for_brand('Northius')['api_key']);
        $this->assertTrue(brand_keys::for_brand('Northius')['is_eu']);
        $this->assertEquals(self::EU_ENDPOINT, apirest::for_brand('Northius')->apiendpoint);
        $this->assertSame('Authorization: Token northiusapikey', $this->auth_header('northiusapikey'));

        // US brand: US endpoint, US key. Note the global is_eu is 0 here and
        // the EU brand above still resolved to the EU endpoint, so the region
        // comes from the brand and not from the global setting.
        $this->assertSame('campusapikey', brand_keys::for_brand('CampusTraining')['api_key']);
        $this->assertFalse(brand_keys::for_brand('CampusTraining')['is_eu']);
        $this->assertEquals(self::US_ENDPOINT, apirest::for_brand('CampusTraining')->apiendpoint);

        // Flipping the global region must not affect a resolved brand.
        set_config('is_eu', 1);
        $this->assertEquals(self::EU_ENDPOINT, apirest::for_brand('Northius')->apiendpoint);
        $this->assertEquals(self::US_ENDPOINT, apirest::for_brand('CampusTraining')->apiendpoint);
    }

    /**
     * Brand lookup ignores case, and empty slots are skipped in the listing.
     *
     * @covers \mod_accredible\local\brand_keys::all
     * @covers \mod_accredible\local\brand_keys::menu
     */
    public function test_listing_and_case_insensitive_lookup(): void {
        $this->set_brand(1, 'Northius', 'northiusapikey', 1);
        $this->set_brand(3, 'CampusTraining', 'campusapikey', 0);

        $this->assertTrue(brand_keys::is_configured());
        $this->assertSame(['Northius', 'CampusTraining'], array_keys(brand_keys::menu()));

        $this->assertSame('northiusapikey', brand_keys::for_brand('northius')['api_key']);
        $this->assertSame('campusapikey', brand_keys::for_brand('CAMPUSTRAINING')['api_key']);
    }

    /**
     * A course sitting directly in a brand's root category resolves to that
     * brand.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_in_root_category_resolves_its_brand(): void {
        $this->set_brand(1, 'MARCA2', 'marca2apikey', 0);

        $root = $this->getDataGenerator()->create_category(['name' => 'MARCA2', 'idnumber' => 'MARCA2']);
        $course = $this->getDataGenerator()->create_course(['category' => $root->id]);

        $this->assertSame('MARCA2', brand_keys::root_idnumber_for_course($course));
        $this->assertSame('MARCA2', brand_keys::brand_from_course($course));
        $this->assertSame('MARCA2', brand_keys::brand_from_course($course->id));

        $this->assertSame('marca2apikey', brand_keys::for_brand(brand_keys::brand_from_course($course))['api_key']);
    }

    /**
     * A course nested under subcategories resolves through its path up to the
     * root, which is the only level carrying an idnumber.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_in_nested_category_resolves_root_brand(): void {
        $this->set_brand(1, 'MARCA1', 'marca1apikey', 1);

        // MARCA1 > Musica > DJ, mirroring the real category tree.
        $root = $this->getDataGenerator()->create_category(['name' => 'MARCA1', 'idnumber' => 'MARCA1']);
        $middle = $this->getDataGenerator()->create_category(['name' => 'Musica', 'parent' => $root->id]);
        $leaf = $this->getDataGenerator()->create_category(['name' => 'DJ', 'parent' => $middle->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $leaf->id]);

        // The subcategories deliberately carry no idnumber of their own.
        $this->assertEmpty($middle->idnumber);
        $this->assertEmpty($leaf->idnumber);
        $this->assertEquals(3, $leaf->depth);

        $this->assertSame('MARCA1', brand_keys::root_idnumber_for_course($course));
        $this->assertSame('MARCA1', brand_keys::brand_from_course($course));

        // And it must reach the brand's own account, not the global one.
        $resolved = brand_keys::for_brand(brand_keys::brand_from_course($course));
        $this->assertSame('marca1apikey', $resolved['api_key']);
        $this->assertTrue($resolved['is_eu']);
        $this->assertEquals(self::EU_ENDPOINT, apirest::for_brand(brand_keys::brand_from_course($course))->apiendpoint);
    }

    /**
     * A root category with no idnumber must degrade to the global account
     * rather than fail.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_course_without_root_idnumber_falls_back_to_global(): void {
        $this->set_brand(1, 'MARCA1', 'marca1apikey', 1);

        $root = $this->getDataGenerator()->create_category(['name' => 'Sin idnumber']);
        $leaf = $this->getDataGenerator()->create_category(['name' => 'Interna', 'parent' => $root->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $leaf->id]);

        $this->assertSame('', brand_keys::root_idnumber_for_course($course));
        $this->assertNull(brand_keys::brand_from_course($course));

        // Null brand is the documented "use the global account" case.
        $resolved = brand_keys::for_brand(brand_keys::brand_from_course($course));
        $this->assertSame('globaltestapikey', $resolved['api_key']);
        $this->assertEquals(self::US_ENDPOINT, apirest::for_brand(brand_keys::brand_from_course($course))->apiendpoint);
    }

    /**
     * A root idnumber naming a brand that is not configured is reported
     * verbatim, but does not resolve to a brand.
     *
     * @covers \mod_accredible\local\brand_keys::brand_from_course
     * @covers \mod_accredible\local\brand_keys::root_idnumber_for_course
     */
    public function test_root_idnumber_not_matching_any_brand(): void {
        $this->set_brand(1, 'MARCA1', 'marca1apikey', 1);

        $root = $this->getDataGenerator()->create_category(['name' => 'Otra', 'idnumber' => 'MARCA_TYPO']);
        $course = $this->getDataGenerator()->create_course(['category' => $root->id]);

        // The raw idnumber is still visible, which is what tells a typo apart
        // from an unconfigured category.
        $this->assertSame('MARCA_TYPO', brand_keys::root_idnumber_for_course($course));
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

    /**
     * The existing single-argument constructors the current test suite relies
     * on must keep working unchanged.
     *
     * @covers \mod_accredible\apirest\apirest::__construct
     * @covers \mod_accredible\client\client::__construct
     */
    public function test_existing_constructor_signatures_still_work(): void {
        $mockclient = new \stdClass();
        $api = new apirest($mockclient);
        $this->assertEquals(self::US_ENDPOINT, $api->apiendpoint);

        $client = new client(null);
        $this->assertInstanceOf(client::class, $client);
    }
}
