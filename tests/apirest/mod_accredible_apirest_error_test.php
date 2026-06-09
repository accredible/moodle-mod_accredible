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

/**
 * Unit tests for apirest::normalize_error() across the documented v1 error shapes.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @category   test
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_accredible_apirest_error_test extends \advanced_testcase {
    /**
     * Setup before every test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Errors are detected by HTTP status (>= 400) and normalized from any v1 body shape.
     * @dataProvider normalize_error_provider
     * @covers \mod_accredible\apirest\apirest::normalize_error
     * @param \stdClass|null $response
     * @param int|null $respcode
     * @param string|null $expected
     */
    public function test_normalize_error($response, $respcode, $expected): void {
        $this->assertSame($expected, apirest::normalize_error($response, $respcode));
    }

    /**
     * Data provider for test_normalize_error.
     * @return array
     */
    public static function normalize_error_provider(): array {
        return [
            // No error: 2xx, or no status code to judge by.
            'success 2xx empty body' => [null, 200, null],
            'status-based: success=false on a 2xx is NOT an error' => [
                (object)['success' => false, 'data' => 'ignored'], 200, null,
            ],
            'no status code means no error' => [
                (object)['success' => false, 'data' => 'ignored'], null, null,
            ],
            // 401/404 — {"success": false, "data": "..."}.
            '401 success/data shape' => [
                (object)['success' => false, 'data' => 'issuer_token not valid'], 401, 'issuer_token not valid',
            ],
            // 403 — {"errors": "..."} as a string.
            '403 errors-string shape' => [
                (object)['errors' => 'Private Certificate, use key'], 403, 'Private Certificate, use key',
            ],
            // 422 — {"errors": {field: [messages]}} validation object.
            '422 errors-object shape' => [
                (object)['errors' => (object)[
                    'course_name' => ["can't be blank"],
                    'recipient_email' => ['is invalid'],
                ]],
                422,
                "course_name: can't be blank; recipient_email: is invalid",
            ],
            // 400 — {"code", "message", "status", "errors": {}}.
            '400 code/message/status shape' => [
                (object)['code' => 400, 'message' => 'invalid_search_query', 'status' => 'Bad Request', 'errors' => (object)[]],
                400,
                'invalid_search_query',
            ],
            // Fallbacks when the body is empty or unrecognized.
            '4xx with no body' => [null, 404, 'HTTP 404'],
            '5xx unrecognized body' => [(object)['weird' => 'shape'], 500, 'HTTP 500'],
        ];
    }
}
