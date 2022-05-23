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

use mod_accredible\local\user;
use mod_accredible\client\client;
use mod_accredible\apirest\apirest;

/**
 * Unit tests for mod/accredible/classes/helpers/user_helper.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_accredible_users_test extends \advanced_testcase {
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

        $this->user = $this->getDataGenerator()->create_user(array('email' => 'person1@example.com'));
        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($this->course->id);
    }

    /**
     * Generate list of users with their credentials from a course
     */
    public function test_get_users_with_credentials() {
        $userhelper = new users();

        // When there are not users.
        $result = $userhelper->get_users_with_credentials(array());
        $this->assertEquals($result, array());

        // When there are users but not groupid.
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id);

        $userrespone = array('id'             => $this->user->id,
                             'email'          => $this->user->email,
                             'name'           => $this->user->firstname . ' ' . $this->user->lastname,
                             'credential_url' => null,
                             'credential_id'  => null);
        $expectedresponse = array('0' => $userrespone);
        $enrolledusers = get_enrolled_users($this->context, "mod/accredible:view", null, 'u.*', 'id');
        $result = $userhelper->get_users_with_credentials($enrolledusers);
        $this->assertEquals($result, $expectedresponse);

        // When there users and groupid.
        $user2 = $this->getDataGenerator()->create_user(array('email' => 'person2@example.com'));
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id);
        $user2respone = array('id'             => $user2->id,
                              'email'          => $user2->email,
                              'name'           => $user2->firstname . ' ' . $user2->lastname,
                              'credential_url' => 'https://www.credential.net/10250012',
                              'credential_id'  => 10250012);
        $expectedresponse = array('0' => $userrespone, '1' => $user2respone);

        $mockclient1 = $this->getMockBuilder('client')
            ->setMethods(['get'])
            ->getMock();

        // Mock API response data.
        $resdatapage1 = $this->mockapi->resdata('credentials/search_success.json');
        $resdatapage2 = $this->mockapi->resdata('credentials/search_success_page_2.json');

        // Expect to call the endpoint once with page and page_size.
        $urlpage1 = "https://api.accredible.com/v1/all_credentials?group_id=123&email=&page_size=50&page=1";
        $urlpage2 = "https://api.accredible.com/v1/all_credentials?group_id=123&email=&page_size=50&page=2";
        $mockclient1->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([$this->equalTo($urlpage1)], [$this->equalTo($urlpage2)])
            ->will($this->onConsecutiveCalls($resdatapage1, $resdatapage2));

        $api = new apirest($mockclient1);
        $userhelper = new users($api);
        $enrolledusers = get_enrolled_users($this->context, "mod/accredible:view", null, 'u.*', 'id');
        $result = $userhelper->get_users_with_credentials($enrolledusers, 123);
        $this->assertEquals($result, $expectedresponse);
    }
}
