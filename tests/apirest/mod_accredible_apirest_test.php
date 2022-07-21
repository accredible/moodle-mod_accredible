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

namespace mod_accredible\apirest;

use mod_accredible\apirest\apirest;
use mod_accredible\client\client;

/**
 * Unit tests for mod/accredible/classes/apirest/apirest.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_apirest_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

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
     * Tests that the api endpoint changes depending on the config.
     */
    public function test_api_endpoint() {
        // When is_eu is NOT enabled.
        $api = new apirest();
        $this->assertEquals($api->apiendpoint, 'https://api.accredible.com/v1/');

        // When is_eu is enabled.
        set_config('is_eu', 1);
        $api = new apirest();
        $this->assertEquals($api->apiendpoint, 'https://eu.api.accredible.com/v1/');

        // When the environemnt variable is set.
        putenv('ACCREDIBLE_DEV_API_ENDPOINT=http://host.docker.internal:3000/v1/');
        $api = new apirest();
        $this->assertEquals($api->apiendpoint, 'http://host.docker.internal:3000/v1/');
    }

    /**
     * Tests if `GET /v1/credentials/:id` is properly called.
     */
    public function test_get_credential() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/show_success.json');

        // Expect to call the endpoint once with id.
        $url = 'https://api.accredible.com/v1/credentials/1';
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->get_credential(1);
        $this->assertEquals($result, $resdata);

        // When the credential is not found.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 404 Not found';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/show_not_found.json');

        // Expect to call the endpoint once with id.
        $url = 'https://api.accredible.com/v1/credentials/9999';
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->get_credential(9999);
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with id.
        $url = 'https://api.accredible.com/v1/credentials/1';
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->get_credential(1);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `GET /v1/all_credentials` is properly called.
     */
    public function test_get_credentials() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success.json');

        // Expect to call the endpoint once with group_id, email, page and page_size.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=".
            rawurlencode("person2@example.com")."&page_size=&page=1";
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->get_credentials(9549, "PeRSon2@example.com");
        $this->assertEquals($result, $resdata);

        // When no credentials are returned from the API.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_empty.json');

        // Expect to call the endpoint once with id.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=".
            rawurlencode("person2@example.com")."&page_size=&page=1";
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return empty array.
        $api = new apirest($mockclient2);
        $result = $api->get_credentials(9549, "person2@example.com");
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with id.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=1000&email=".
            rawurlencode("person2@example.com")."&page_size=&page=1";
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->get_credentials(1000, "person2@example.com");
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/sso/generate_link` is properly called.
     */
    public function test_recipient_sso_link() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/recipient_sso_link_success.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/sso/generate_link";

        $reqdata = json_encode(array(
            "recipient_email" => "person@example.com",
            "group_id" => 45,
        ));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->recipient_sso_link(null, null, "PerSon@example.com", null, 45, null);
        $this->assertEquals($result, $resdata);

        // When the api returns not found error.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 404 Not found';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/recipient_sso_link_not_found.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/sso/generate_link";

        $reqdata = json_encode(array(
            "recipient_email" => "person@example.com",
            "group_id" => 45
        ));

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->recipient_sso_link(null, null, "PerSon@example.com", null, 45, null);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/credentials` is properly called.
     */
    public function test_create_credential() {
        // When the credential creation is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/credentials";

        $reqdata = json_encode(array(
            "credential" => array(
                "group_id" => 1,
                "recipient" => array(
                    "name" => "Jordan Smith",
                    "email" => "person2@example.com"
                ),
                "issued_on" => null,
                "expired_on" => null,
                "custom_attributes" => null
            )
        ));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->create_credential("Jordan Smith", "person2@example.com", 1);
        $this->assertEquals($result, $resdata);

        // When the credential creation fails and the api returns an error.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/credentials";
        $reqdata = json_encode(array(
            "credential" => array(
                "group_id" => 1,
                "recipient" => array(
                    "name" => "Jordan Smith",
                    "email" => "person2@example.com"
                ),
                "issued_on" => null,
                "expired_on" => null,
                "custom_attributes" => null
            )
        ));

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->create_credential("Jordan Smith", "person2@example.com", 1);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/credentials` is properly called.
     */
    public function test_create_credential_legacy() {
        // When the credential creation is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/credentials";

        $reqdata = json_encode(array(
            "credential" => array(
                "group_name" => "Example Certificate Design",
                "recipient" => array(
                    "name" => "Jordan Smith",
                    "email" => "person2@example.com"
                ),
                "issued_on" => null,
                "expired_on" => null,
                "custom_attributes" => null,
                "name" => null,
                "description" => null,
                "course_link" => null
            )
        ));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->create_credential_legacy("Jordan Smith", "person2@example.com", "Example Certificate Design");
        $this->assertEquals($result, $resdata);

        // When the credential creation fails and the api returns an error.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/credentials";

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->create_credential_legacy("Jordan Smith", "person2@example.com", "Example Certificate Design");
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/issuer/groups/search` is properly called.
     */
    public function test_search_groups() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/search_success.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10000));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->search_groups(10000, 1);
        $this->assertEquals($result, $resdata);

        // When the arguments are empty and the response is successful.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/search_success.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 50));

        // Expect to call the endpoint once with default page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->search_groups();
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10000));

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/search';
        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->search_groups(10000, 1);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/credentials/:credential_id/evidence_items`
     * is properly called.
     */
    public function test_create_evidence_item() {
        // When the throw_error is FALSE and the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/create_success.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';
        $evidenceitem = array(
            'evidence_item' => array(
                "string_object" => "100",
                "description" => "Quiz",
                "custom" => true,
                "category" => "grade"
            )
        );
        $reqdata = json_encode($evidenceitem);

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->create_evidence_item($evidenceitem, 1);
        $this->assertEquals($result, $resdata);

        // When the throw_error is FALSE and the response is NOT successful.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata without throwing an exception.
        $api = new apirest($mockclient2);
        $result = $api->create_evidence_item($evidenceitem, 1);
        $this->assertEquals($result, $resdata);

        // When the throw_error is TRUE and the response is NOT successful.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata without throwing an exception.
        $api = new apirest($mockclient3);
        $foundexception = false;
        try {
            $api->create_evidence_item($evidenceitem, 1, true);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Tests if `POST /v1/credentials/:credential_id/evidence_items`
     * is properly called when sending duration items.
     */
    public function test_create_evidence_item_duration() {
        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/create_success.json');

        // When the startdate == enddate.
        $startdate = strtotime('2022-04-15');
        $enddate = strtotime('2022-04-15');
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';
        $stringobject = array(
            "start_date"       => "2022-04-15",
            "end_date"         => "2022-04-15",
            "duration_in_days" => 1
        );
        $evidenceitem = array(
            "evidence_item" => array(
                "description"   => 'Completed in 1 day',
                "category"      => 'course_duration',
                "string_object" => json_encode($stringobject),
                "hidden"        => false
            )
        );
        $reqdata = json_encode($evidenceitem);

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->create_evidence_item_duration($startdate, $enddate, 1);
        $this->assertEquals($result, $resdata);

        // When the startdate < enddate.
        $startdate = strtotime('2022-04-15');
        $enddate = strtotime('2022-04-17');
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items';
        $stringobject = array(
            "start_date"       => "2022-04-15",
            "end_date"         => "2022-04-17",
            "duration_in_days" => 2
        );
        $evidenceitem = array(
            "evidence_item" => array(
                "description"   => 'Completed in 2 days',
                "category"      => 'course_duration',
                "string_object" => json_encode($stringobject),
                "hidden"        => false
            )
        );
        $reqdata = json_encode($evidenceitem);

        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->create_evidence_item_duration($startdate, $enddate, 1);
        $this->assertEquals($result, $resdata);

        // When startdate > enddate.
        $startdate = strtotime('2022-04-18');
        $enddate = strtotime('2022-04-15');

        // Expect to throw an exception.
        $api = new apirest();
        $foundexception = false;
        try {
            $api->create_evidence_item_duration($startdate, $enddate, 1);
        } catch (\InvalidArgumentException $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Tests if `PUT /v1/credentials/:credential_id/evidence_items/:id`
     * is properly called.
     */
    public function test_update_evidence_item_grade() {
        // When the grade is a valid number and the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['put'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/update_success.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items/1';
        $reqdata = '{"evidence_item":{"string_object":"100"}}';
        $mockclient1->expects($this->once())
            ->method('put')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->update_evidence_item_grade(1, 1, '100');
        $this->assertEquals($result, $resdata);

        // When the grade is a valid number but the evidence item is not found.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['put'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 404 Not found';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('evidence_items/update_not_found.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items/9999';
        $reqdata = '{"evidence_item":{"string_object":"100"}}';
        $mockclient2->expects($this->once())
            ->method('put')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->update_evidence_item_grade(1, 9999, '100');
        $this->assertEquals($result, $resdata);

        // When the grade is a valid number but the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['put'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with url and reqdata.
        $url = 'https://api.accredible.com/v1/credentials/1/evidence_items/2';
        $reqdata = '{"evidence_item":{"string_object":"100"}}';
        $mockclient3->expects($this->once())
            ->method('put')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->update_evidence_item_grade(1, 2, '100');
        $this->assertEquals($result, $resdata);

        // When the grade is NOT a number.
        $foundexception1 = false;
        try {
            $api->update_evidence_item_grade(1, 1, 'number');
        } catch (\InvalidArgumentException $error) {
            $foundexception1 = true;
        }
        $this->assertTrue($foundexception1);

        // When the grade is negative.
        $foundexception2 = false;
        try {
            $api->update_evidence_item_grade(1, 1, -1);
        } catch (\InvalidArgumentException $error) {
            $foundexception2 = true;
        }
        $this->assertTrue($foundexception2);

        // When the grade is greater than 100.
        $foundexception3 = false;
        try {
            $api->update_evidence_item_grade(1, 1, 101);
        } catch (\InvalidArgumentException $error) {
            $foundexception3 = true;
        }
        $this->assertTrue($foundexception3);
    }

    /**
     * Tests if `GET /v1/issuer/groups/:group_id` is properly called.
     */
    public function test_get_group() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/get_group_success.json');
        $groupid = 12472;

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/groups/' . $groupid;
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->get_group($groupid);
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint.
        $url = 'https://api.accredible.com/v1/issuer/groups/' . $groupid;
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->get_group($groupid);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `GET /v1/issuer/all_groups` is properly called.
     */
    public function test_get_groups() {
        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/all_groups_success.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=10000&page=1';
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->get_groups(10000, 1);
        $this->assertEquals($result, $resdata);

        // When the arguments are empty and the response is successful.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('groups/all_groups_success.json');

        // Expect to call the endpoint once with default page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=50&page=1';
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->get_groups();
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = 'https://api.accredible.com/v1/issuer/all_groups?page_size=10000&page=1';
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->get_groups(10000, 1);
        $this->assertEquals($result, $resdata);
    }

    /**
     * Tests if `POST /v1/attribute_keys/search` is properly called.
     */
    public function test_search_attribute_keys() {
        $url = 'https://api.accredible.com/v1/attribute_keys/search';

        // When the response is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('attribute_keys/search_success.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 20, 'kind' => 'text'));

        // Expect to call the endpoint once with page and page_size.
        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient1);
        $result = $api->search_attribute_keys(20, 1);
        $this->assertEquals($result, $resdata);

        // When the arguments are empty and the response is successful.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('attribute_keys/search_success.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 50, 'kind' => 'text'));

        // Expect to call the endpoint once with default page and page_size.
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient2);
        $result = $api->search_attribute_keys();
        $this->assertEquals($result, $resdata);

        // When the api key is invalid.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();
        $mockclient3->error = 'The requested URL returned error: 401 Unauthorized';

        // Mock API response data.
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        $reqdata = json_encode(array('page' => 1, 'page_size' => 10, 'kind' => 'text'));

        // Expect to call the endpoint once with page and page_size.
        $mockclient3->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url),
                   $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return resdata.
        $api = new apirest($mockclient3);
        $result = $api->search_attribute_keys(10, 1);
        $this->assertEquals($result, $resdata);
    }
}
