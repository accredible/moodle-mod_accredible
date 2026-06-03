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

/**
 * The curl object used to make the request.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /**
     * The curl object used to make the request.
     * @var curl $curl
     */
    private $curl;

    /**
     * The options object for the requests.
     * @var array $curloptions
     */
    private $curloptions;

    /**
     * Last transport-level error message, or null. Reset each request.
     * @var string|null $error
     */
    public $error;

    /**
     * HTTP status code of the last response, or null if none completed.
     * @var int|null $resp_code
     */
    public $resp_code;

    /**
     * Latency of the last request in milliseconds.
     * @var int|null $latencyms
     */
    public $latencyms;

    /**
     * URL of the last request, or null if none has been made.
     * @var string|null $last_url
     */
    public $last_url;

    /**
     * Constructor method
     *
     * @param stdObject $curl a mock curl for testing
     */
    public function __construct($curl = null) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // A mock curl is passed when unit testing.
        if ($curl) {
            $this->curl = $curl;
        } else {
            $this->curl = new \curl();
        }

        $token = $CFG->accredible_api_key;
        // No CURLOPT_FAILONERROR: keep 4xx/5xx bodies so apirest can read them.
        $this->curloptions = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token ' . $token,
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];

        $this->error = null;
    }

    /**
     * Make a GET request.
     * @param string $url
     * @return stdObject
     */
    public function get($url) {
        return $this->send_req($url, 'get');
    }

    /**
     * Make a POST request.
     * @param string $url
     * @param string $reqdata a JSON encoded string
     * @return stdObject
     */
    public function post($url, $reqdata) {
        return $this->send_req($url, 'post', $reqdata);
    }

    /**
     * Make a PUT request.
     * @param string $url
     * @param string $reqdata a JSON encoded string
     * @return stdObject
     */
    public function put($url, $reqdata) {
        return $this->send_req($url, 'put', $reqdata);
    }

    /**
     * Call $curl method.
     * @param string $url
     * @param string $method
     * @param string $reqdata a JSON encoded string
     * @return \stdClass|null
     */
    private function send_req($url, $method, $reqdata = null) {
        $curl = $this->curl;

        // Reset per-request state; the client instance is reused across calls.
        $this->error = null;
        $this->resp_code = null;
        $this->latencyms = null;
        $this->last_url = $url;

        $starttime = microtime(true);
        $response = $curl->$method($url, $reqdata, $this->curloptions);
        $this->latencyms = (int) round((microtime(true) - $starttime) * 1000);

        // Capture the HTTP status for callers and events.
        if (isset($curl->info['http_code'])) {
            $this->resp_code = (int) $curl->info['http_code'];
        }

        // Transport-level (curl) error.
        if ($curl->error) {
            $this->error = $curl->error;
            debugging('<div style="padding-top: 70px; font-size: 0.9rem;"><b>ACCREDIBLE API ERROR</b> ' .
                $curl->error . '<br />' . $method . ' ' . $url . '</div>', DEBUG_DEVELOPER);
            return null;
        }

        // Empty body: return null without flagging an error (resp_code disambiguates).
        if ($response === false || is_null($response) || $response === '') {
            return null;
        }

        // Malformed JSON: record distinctly so it isn't mistaken for an empty body.
        $decoded = json_decode($response);
        if (is_null($decoded) && json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Malformed JSON response: ' . json_last_error_msg();
            return null;
        }

        return $decoded;
    }
}
