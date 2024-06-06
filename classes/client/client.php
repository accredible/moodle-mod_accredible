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
     * The options object for the requests.
     * @var string|null $error
     */
    public $error;

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
        $this->curloptions = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FAILONERROR'    => true,
            'CURLOPT_HTTPHEADER'     => [
                'Authorization: Token ' . $token,
                'Content-Type: application/json; charset=utf-8',
                'Accredible-Integration: Moodle',
            ],
        ];

        $error = null;
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
     * @return stdObject
     */
    private function send_req($url, $method, $reqdata = null) {
        $curl = $this->curl;
        $response = $curl->$method($url, $reqdata, $this->curloptions);

        if ($curl->error) {
            $this->error = $curl->error;
            debugging('<div style="padding-top: 70px; font-size: 0.9rem;"><b>ACCREDIBLE API ERROR</b> ' .
                $curl->error . '<br />' . $method . ' ' . $url . '</div>', DEBUG_DEVELOPER);
        };

        return json_decode($response);
    }
}
