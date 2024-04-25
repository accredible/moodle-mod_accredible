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
use mod_accredible\Html2Text\Html2Text;
use mod_accredible\local\groups;

/**
 * Unit tests for mod/accredible/classes/local/groups.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_groups_test extends \advanced_testcase {
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
     * Test whether it returns groups
     * @covers ::get_groups
     */
    public function test_get_groups() {
        // When the apirest returns groups.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata1 = $this->mockapi->resdata('groups/all_groups_page1.json');
        $resdata2 = $this->mockapi->resdata('groups/all_groups_page2.json');

        // Expect to call the endpoint once with page and page_size.
        $url1 = 'https://api.accredible.com/v1/issuer/all_groups?page_size=50&page=1';
        $url2 = 'https://api.accredible.com/v1/issuer/all_groups?page_size=50&page=2';

        $mockclient1->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([$this->equalTo($url1)], [$this->equalTo($url2)])
            ->will($this->onConsecutiveCalls($resdata1, $resdata2));

        // Expect to return groups.
        $api = new apirest($mockclient1);
        $localgroups = new groups($api);
        $result = $localgroups->get_groups();
        $this->assertEquals($result, array(
            '12473' => 'new group1',
            '12472' => 'new group2',
            '12474' => 'new group3',
            '12475' => 'new group4',
        ));

        // When the apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=50&page=1';
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->get_groups();
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When the apirest returns no groups.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/all_groups_success_empty.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=50&page=1';
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localgroups = new groups($api);
        $result = $localgroups->get_groups();
        $this->assertEquals($result, array());
    }

    /**
     * Test whether it returns group name arrays
     * @covers ::get_templates
     */
    public function test_get_templates() {
        // When the apirest returns groups.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata1 = $this->mockapi->resdata('groups/search_success_page1.json');
        $resdata2 = $this->mockapi->resdata('groups/search_success_page2.json');

        $reqdata1 = json_encode(array('page' => 1, 'page_size' => 50));
        $reqdata2 = json_encode(array('page' => 2, 'page_size' => 50));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';

        $mockclient1->expects($this->exactly(2))
            ->method('post')
            ->withConsecutive([$this->equalTo($url), $this->equalTo($reqdata1)], [$this->equalTo($url), $this->equalTo($reqdata2)])
            ->will($this->onConsecutiveCalls($resdata1, $resdata2));

        // Expect to return group name arrays.
        $api = new apirest($mockclient1);
        $localgroups = new groups($api);
        $result = $localgroups->get_templates();
        $this->assertEquals($result, array(
            'new group1' => 'new group1',
            'new group2' => 'new group2',
            'new group3' => 'new group3',
            'new group4' => 'new group4'
        ));

        // When the apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 50));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localgroups = new groups($api);
        $foundexception = false;
        try {
            $localgroups->get_templates();
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When the apirest returns no groups.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/search_success_empty.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 50));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localgroups = new groups($api);
        $result = $localgroups->get_templates();
        $this->assertEquals($result, array());
    }
}
