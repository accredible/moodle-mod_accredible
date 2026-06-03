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
 * The credential_issue_skipped event class.
 *
 * Fired when an issuance handler runs for a relevant event but the eligibility
 * check declines to issue a credential. Carries a stable reason code in
 * other['reason'] so admins can diagnose why a user was not issued.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_issue_skipped extends \core\event\base {
    /**
     * Init function to assign variables.
     */
    protected function init() {
        $this->data['crud'] = 'r'; // ... read (an evaluation that did not create anything).
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'accredible';
    }

    /**
     * Get the event name.
     * @return string
     */
    public static function get_name() {
        return get_string('eventcredentialissueskipped', 'mod_accredible');
    }

    /**
     * Get the event description.
     * @return string
     */
    public function get_description() {
        $reason = $this->other['reason'] ?? 'unknown';
        return "Accredible credential issuance was skipped (reason: {$reason}) for user " .
            "{$this->relateduserid}.";
    }
}
