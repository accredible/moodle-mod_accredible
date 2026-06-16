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
 * The credential_issued event class.
 *
 * Fired when the modern create_credential path issues a credential.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_issued extends \core\event\base {
    /**
     * Init function to assign variables.
     */
    protected function init() {
        $this->data['crud'] = 'c'; // ... create.
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get the event name.
     * @return string
     */
    public static function get_name() {
        return get_string('eventcredentialissued', 'mod_accredible');
    }

    /**
     * Get the event description.
     * @return string
     */
    public function get_description() {
        $credentialid = $this->other['credentialid'] ?? '';
        return "An Accredible credential (id '{$credentialid}') was issued for user with id '{$this->relateduserid}'.";
    }
}
