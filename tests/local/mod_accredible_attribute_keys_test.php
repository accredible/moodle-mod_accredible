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
 * Unit tests for mod/accredible/classes/local/attribute_keys.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_attribute_keys_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        // Add plugin settings.
        set_config('accredible_api_key', 'sometestapikey');
        set_config('is_eu', 0);

        // Unset the devlopment environment variable.
        putenv('ACCREDIBLE_DEV_API_ENDPOINT');

        $this->mockapi = new class {
            /**
             * Returns a mock API response based on the fixture json.
             * @param string $jsonpath
             * @return array
             */
            public function resdata($jsonpath) {
                global $CFG;
                $fixturedir = $CFG->dirroot . '/mod/accredible/tests/fixtures/mockapi/v1/';
                $filepath = $fixturedir . $jsonpath;
                return json_decode(file_get_contents($filepath));
            }
        };
    }

    /**
     * Test whether it returns attribute keys.
     * @covers ::get_attribute_keys
     */
    public function test_get_attribute_keys() {
        // When the apirest returns attribute keys.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata1 = $this->mockapi->resdata('attribute_keys/search_success.json');
        $resdata2 = $this->mockapi->resdata('attribute_keys/search_success_page_2.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/attribute_keys/search';

        $reqdata1 = json_encode(array('page' => 1, 'page_size' => 50, 'kind' => 'text'));
        $reqdata2 = json_encode(array('page' => 2, 'page_size' => 50, 'kind' => 'text'));

        $mockclient1->expects($this->exactly(2))
            ->method('post')
            ->withConsecutive([$this->equalTo($url), $this->equalTo($reqdata1)], [$this->equalTo($url), $this->equalTo($reqdata2)])
            ->will($this->onConsecutiveCalls($resdata1, $resdata2));

        // Expect to return attribute keys.
        $api = new apirest($mockclient1);
        $localattributekeys = new attribute_keys($api);
        $result = $localattributekeys->get_attribute_keys();
        $this->assertEquals($result, array(
            'Custom Attribute Key 1' => 'Custom Attribute Key 1',
            'Custom Attribute Key 2' => 'Custom Attribute Key 2',
            'Custom Attribute Key 3' => 'Custom Attribute Key 3',
            'Custom Attribute Key 4' => 'Custom Attribute Key 4'
        ));

        // When the apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/attribute_keys/search';
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata1))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localattributekeys = new attribute_keys($api);
        $foundexception = false;
        try {
            $localattributekeys->get_attribute_keys();
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When the apirest returns no attribute keys.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('attribute_keys/search_empty_results.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/attribute_keys/search';
        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata1))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localattributekeys = new attribute_keys($api);
        $result = $localattributekeys->get_attribute_keys();
        $this->assertEquals($result, array());

        // When apirest returns attribute keys for the given kind.
        $mockclient4 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        $reqdata1 = json_encode(array('page' => 1, 'page_size' => 50, 'kind' => 'date'));
        $reqdata2 = json_encode(array('page' => 2, 'page_size' => 50, 'kind' => 'date'));

        $mockclient4->expects($this->exactly(2))
            ->method('post')
            ->withConsecutive([$this->equalTo($url), $this->equalTo($reqdata1)], [$this->equalTo($url), $this->equalTo($reqdata2)])
            ->will($this->onConsecutiveCalls($resdata1, $resdata2));

        // Expect to return attribute keys.
        $api = new apirest($mockclient4);
        $localattributekeys = new attribute_keys($api);
        $result = $localattributekeys->get_attribute_keys('date');
        $this->assertEquals($result, array(
            'Custom Attribute Key 1' => 'Custom Attribute Key 1',
            'Custom Attribute Key 2' => 'Custom Attribute Key 2',
            'Custom Attribute Key 3' => 'Custom Attribute Key 3',
            'Custom Attribute Key 4' => 'Custom Attribute Key 4'
        ));

    }
}
