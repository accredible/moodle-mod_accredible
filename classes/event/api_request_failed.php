<?php
// This file is part of Moodle - http://moodle.org/
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

namespace mod_accredible\event;

/**
 * The api_request_failed event class.
 *
 * Fired when an Accredible API request fails (curl error, non-2xx, or error body).
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_request_failed extends \core\event\base {
    /**
     * Init function to assign variables.
     */
    protected function init() {
        $this->data['crud'] = 'r'; // ... read.
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get the event name.
     * @return string
     */
    public static function get_name() {
        return get_string('eventapirequestfailed', 'mod_accredible');
    }

    /**
     * Get the event description.
     * @return string
     */
    public function get_description() {
        $endpoint = $this->other['endpoint'] ?? '';
        $status = $this->other['http_status'] ?? '';
        $error = $this->other['error'] ?? '';
        return "An Accredible API request to '{$endpoint}' failed " .
            "(HTTP status: '{$status}') for user {$this->relateduserid}. {$error}";
    }
}
