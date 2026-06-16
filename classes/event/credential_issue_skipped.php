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
 * Fired when an eligibility check declines to issue a credential.
 * Carries a reason code in other['reason'].
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
        $this->data['crud'] = 'r'; // ... read.
        $this->data['edulevel'] = self::LEVEL_OTHER;
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
        $reasontext = get_string_manager()->string_exists('reason_' . $reason, 'mod_accredible')
            ? get_string('reason_' . $reason, 'mod_accredible')
            : $reason;
        $description = "Accredible credential issuance was skipped (reason: {$reasontext}) for user with id " .
            "'{$this->relateduserid}'.";
        return $description;
    }
}
