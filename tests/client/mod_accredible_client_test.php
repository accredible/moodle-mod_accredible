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

namespace mod_accredible\client;

use mod_accredible\client\client;

/**
 * Unit tests for mod/accredible/classes/client/client.php
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_client_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // Add plugin settings.
        set_config('accredible_api_key', 'sometestapikey');
    }

    /**
     * Tests whether it calls the curl get function.
     * @covers  ::get
     */
    public function test_get(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token sometestapikey',
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];

        // Mock curl.
        $mockcurl = $this->getMockBuilder('curl')
            ->onlyMethods(['get'])
            ->getMock();

        $mockcurl->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo($url),
                $this->equalTo(null),
                $this->equalTo($options)
            );

        // Expect to call curl get.
        $client = new client($mockcurl);
        $client->get($url);
    }

    /**
     * Tests whether it calls the curl post function.
     * @covers  ::post
     */
    public function test_post(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token sometestapikey',
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];

        // Mock curl.
        $mockcurl = $this->getMockBuilder('curl')
            ->onlyMethods(['post'])
            ->getMock();

        $reqdata = '{"evidence_item":{"string_object":"100"}}';
        $mockcurl->expects($this->once())
            ->method('post')
            ->with(
                $this->equalTo($url),
                $this->equalTo($reqdata),
                $this->equalTo($options)
            );

        // Expect to call curl post.
        $client = new client($mockcurl);
        $client->post($url, $reqdata);
    }

    /**
     * Tests whether it calls the curl put function.
     * @covers  ::put
     */
    public function test_put(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token sometestapikey',
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];

        // Mock curl.
        $mockcurl = $this->getMockBuilder('curl')
            ->onlyMethods(['put'])
            ->getMock();

        $reqdata = '{"evidence_item":{"string_object":"100"}}';
        $mockcurl->expects($this->once())
            ->method('put')
            ->with(
                $this->equalTo($url),
                $this->equalTo($reqdata),
                $this->equalTo($options)
            );

        // Expect to call curl put.
        $client = new client($mockcurl);
        $client->put($url, $reqdata);
    }

    /**
     * Tests whether it returns an error messages when the request fails.
     * @coversNothing
     */
    public function test_error(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token sometestapikey',
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];
        $error = 'The requested URL returned error: 401 Unauthorized';

        // Mock curl.
        $mockcurl = $this->getMockBuilder('curl')
            ->onlyMethods(['get'])
            ->getMock();
        $mockcurl->error = $error;

        $mockcurl->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo($url),
                $this->equalTo(null),
                $this->equalTo($options)
            );

        // Expect to call debugging.
        $client = new client($mockcurl);
        $this->assertDebuggingCalled($client->get($url));

        // Expect to return an error message.
        $this->assertEquals($client->error, $error);
    }

    /**
     * The error property is reset at the start of each request on a reused client.
     * @coversNothing
     */
    public function test_error_is_reset_between_requests(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';

        $mockcurl = $this->getMockBuilder('curl')->onlyMethods(['get'])->getMock();
        $mockcurl->method('get')->willReturn('{"ok":true}');
        $client = new client($mockcurl);

        // First request fails at the transport layer.
        $mockcurl->error = 'Network down';
        $client->get($url);
        $this->assertEquals('Network down', $client->error);
        $this->assertDebuggingCalled();

        // Second request succeeds; the stale error must not leak through.
        $mockcurl->error = '';
        $client->get($url);
        $this->assertNull($client->error);
    }

    /**
     * The HTTP status code is captured into respcode from the curl info.
     * @coversNothing
     */
    public function test_respcode_is_captured(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';

        $mockcurl = $this->getMockBuilder('curl')->onlyMethods(['get'])->getMock();
        $mockcurl->info = ['http_code' => 404];
        $mockcurl->method('get')->willReturn('{"success":false,"data":"Group does not exist"}');
        $client = new client($mockcurl);

        $client->get($url);
        $this->assertEquals(404, $client->respcode);
        // A 4xx body is preserved (no FAILONERROR) and is not a transport error.
        $this->assertNull($client->error);
    }

    /**
     * Null-return case 1: a curl transport error returns null and sets error.
     * @coversNothing
     */
    public function test_curl_error_returns_null_with_error(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';

        $mockcurl = $this->getMockBuilder('curl')->onlyMethods(['get'])->getMock();
        $mockcurl->error = 'Could not resolve host';
        $mockcurl->method('get')->willReturn(false);
        $client = new client($mockcurl);

        $result = $client->get($url);
        $this->assertNull($result);
        $this->assertEquals('Could not resolve host', $client->error);
        $this->assertDebuggingCalled();
    }

    /**
     * Null-return case 2: an empty body returns null without flagging an error.
     * @coversNothing
     */
    public function test_empty_body_returns_null_without_error(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';

        $mockcurl = $this->getMockBuilder('curl')->onlyMethods(['get'])->getMock();
        $mockcurl->method('get')->willReturn('');
        $client = new client($mockcurl);

        $result = $client->get($url);
        $this->assertNull($result);
        $this->assertNull($client->error);
    }

    /**
     * Null-return case 3: malformed JSON returns null and sets a distinct error.
     * @coversNothing
     */
    public function test_malformed_json_returns_null_with_error(): void {
        $url = 'https://api.accredible.com/v1/all_credentials';

        $mockcurl = $this->getMockBuilder('curl')->onlyMethods(['get'])->getMock();
        $mockcurl->method('get')->willReturn('<html>Bad Gateway</html>');
        $client = new client($mockcurl);

        $result = $client->get($url);
        $this->assertNull($result);
        $this->assertStringContainsString('Malformed JSON', $client->error);
    }
}
