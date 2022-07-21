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
use mod_accredible\local\credentials;

/**
 * Unit tests for mod/accredible/classes/local/credentials.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_credentials_test extends \advanced_testcase {
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

        $this->user = $this->getDataGenerator()->create_user();
        $this->userwithemail = $this->getDataGenerator()->create_user(array('email' => 'person2@example.com'));
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Create credential test
     */
    public function test_create_credential() {
        // When the credential creation is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        $mockgroupid = 9549;

        // Expect to call the endpoint with this URL.
        $url = 'https://api.accredible.com/v1/credentials';

        $reqdata = json_encode(array(
            "credential" => array(
                "group_id" => $mockgroupid,
                "recipient" => array(
                    "name" => fullname($this->user),
                    "email" => $this->user->email
                ),
                "issued_on" => null,
                "expired_on" => null,
                "custom_attributes" => array(
                    "test" => 25
                )
            )
        ));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return the created credential.
        $api = new apirest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->create_credential($this->user, $mockgroupid, null, array("test" => 25));
        $this->assertEquals($result, $resdata->credential);

        // When the apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once.
        $url = 'https://api.accredible.com/v1/credentials';
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;

        try {
            $localcredentials->create_credential($this->user, $mockgroupid);
            $this->assertEquals($result, $resdata->credential);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Create credential legacy test
     */
    public function test_create_credential_legacy() {
        global $DB;
        // When the credential creation is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/create_success.json');

        $mockgroupid = 9549;

        $instanceid = $DB->insert_record('accredible', array("achievementid" => "moodle-course",
            'name' => 'Moodle Course',
            'course' => $this->course->id,
            'finalquiz' => false,
            'passinggrade' => 0,
            'groupid' => $mockgroupid));

        // Expect to call the endpoint once.
        $url = 'https://api.accredible.com/v1/credentials';

        $courselink = (new \moodle_url('/course/view.php', array('id' => $this->course->id)))->__toString();
        $completeddate = date('Y-m-d', (int) time());

        $reqdata = json_encode(array(
            "credential" => array(
                "group_name" => "moodle-course",
                "recipient" => array(
                    "name" => fullname($this->user),
                    "email" => $this->user->email
                ),
                "issued_on" => $completeddate,
                "expired_on" => null,
                "custom_attributes" => null,
                "name" => "",
                "description" => null,
                "course_link" => $courselink
            )
        ));

        $mockclient1->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url), $this->equalTo($reqdata))
            ->willReturn($resdata);

        // Expect to return the created credential.
        $api = new apirest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->create_credential_legacy($this->user, "moodle-course",
            "", null, $courselink, $completeddate);
        $this->assertEquals($result, $resdata->credential);

        // When the apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['post'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once.
        $url = 'https://api.accredible.com/v1/credentials';
        $mockclient2->expects($this->once())
            ->method('post')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;

        try {
            $localcredentials->create_credential_legacy($this->user, "moodle-course",
            "", null, $courselink, $completeddate);
            $this->assertEquals($result, $resdata->credential);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Get credentials test
     */
    public function test_get_credentials() {
        // When the credential search is successful.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdatapage1 = $this->mockapi->resdata('credentials/search_success.json');
        $resdatapage2 = $this->mockapi->resdata('credentials/search_success_page_2.json');

        // Expect to call the endpoint once with page and page_size.
        $urlpage1 = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=&page_size=50&page=1";
        $urlpage2 = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=&page_size=50&page=2";
        $mockclient1->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([$this->equalTo($urlpage1)], [$this->equalTo($urlpage2)])
            ->will($this->onConsecutiveCalls($resdatapage1, $resdatapage2));

        // Expect to return all credentials of the group_id in the param.
        $api = new apirest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->get_credentials(9549);
        $resdatapage12 = array_merge($resdatapage1->credentials, $resdatapage2->credentials);
        $this->assertEquals($result, $resdatapage12);

        // When apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=&page_size=50&page=1";
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;
        try {
            $localcredentials->get_credentials(9549);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When the credential search returns no credentials.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_empty.json');

        // Expect to call the endpoint once with page and page_size.
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localcredentials = new credentials($api);
        $result = $localcredentials->get_credentials(9549);
        $this->assertEquals($result, array());
    }

    /**
     * Check existing credential test
     */
    public function test_check_for_existing_credential() {
        // When an existing credential exists for a group_id and user_email.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success.json');

        // Expect to call the endpoint once with page and page_size.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=".
            rawurlencode($this->userwithemail->email)."&page_size=&page=1";
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return a credential belonging to the user.
        $api = new apirest($mockclient1);
        $localcredentials = new credentials($api);
        $result = $localcredentials->check_for_existing_credential(9549, $this->userwithemail->email);
        $this->assertEquals($result, $resdata->credentials[0]);

        // When apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once with page and page_size.
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;
        try {
            $localcredentials->check_for_existing_credential(9549, $this->userwithemail->email);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When there is no credential for the group_id.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_empty.json');

        // Expect to call the endpoint once with page and page_size.
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localcredentials = new credentials($api);
        $result = $localcredentials->check_for_existing_credential(9549, $this->userwithemail->email);
        $this->assertEquals($result, false);
    }

    /**
     * Check existing certificate test
     */
    public function test_check_for_existing_certificate() {
        // When an existing credential exists for a group_id and user_email.
        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_page_2.json');

        // Expect to call the endpoint once.
        $url = "https://api.accredible.com/v1/all_credentials?group_id=9549&email=".
            rawurlencode($this->userwithemail->email)."&page_size=50&page=1";
        $mockclient1->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return a credential belonging to the user.
        $api = new apirest($mockclient1);
        $localcredentials = new credentials($api);

        // Send the userwithemail as the function returns the credential only if the recipient email matches the user email.
        $result = $localcredentials->check_for_existing_certificate(9549, $this->userwithemail);

        $this->assertEquals($result, $resdata->credentials[1]);

        // When apirest returns an error response.
        $mockclient2 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $mockclient2->error = 'The requested URL returned error: 401 Unauthorized';
        $resdata = $this->mockapi->resdata('unauthorized_error.json');

        // Expect to call the endpoint once.
        $mockclient2->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to raise an exception.
        $api = new apirest($mockclient2);
        $localcredentials = new credentials($api);
        $foundexception = false;
        try {
            $localcredentials->check_for_existing_certificate(9549, $this->userwithemail);
        } catch (\moodle_exception $error) {
            $foundexception = true;
        }
        $this->assertTrue($foundexception);

        // When no credential exists for the group_id and user_id.
        $mockclient3 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdata = $this->mockapi->resdata('credentials/search_success_empty.json');

        // Expect to call the endpoint once.
        $mockclient3->expects($this->once())
            ->method('get')
            ->with($this->equalTo($url))
            ->willReturn($resdata);

        // Expect to return an empty array.
        $api = new apirest($mockclient3);
        $localcredentials = new credentials($api);
        $result = $localcredentials->check_for_existing_certificate(9549, $this->userwithemail);
        $this->assertEquals($result, false);
    }
}
