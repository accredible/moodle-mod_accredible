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
 * The credential_issue_failed event class.
 *
 * Fired when an exception is caught while attempting to issue a credential
 * (the business-level "we tried, it didn't happen" event).
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_issue_failed extends \core\event\base {
    /**
     * Init function to assign variables.
     */
    protected function init() {
        $this->data['crud'] = 'c'; // ... create (an attempted create that failed).
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'accredible';
    }

    /**
     * Get the event name.
     * @return string
     */
    public static function get_name() {
        return get_string('eventcredentialissuefailed', 'mod_accredible');
    }

    /**
     * Get the event description.
     * @return string
     */
    public function get_description() {
        $reason = $this->other['reason'] ?? 'unknown';
        $message = $this->other['message'] ?? '';
        return "Accredible credential issuance failed (reason: {$reason}) for user " .
            "{$this->relateduserid}. {$message}";
    }
}
