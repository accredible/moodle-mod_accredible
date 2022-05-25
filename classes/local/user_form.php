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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Local function that will generate a form with a list of users.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_form extends \moodleform {
    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        $mform = $this->_form;
        $users = $this->_customdata['users'];

        $mform->addElement('html', '<div id="users-list">');
        foreach ($users as $user) {
            // Show the certificate if they have a certificate.
            if ($user['credential_id']) {
                $mform->addElement('static', 'certlink'.$user['id'], $user['name'] . '    ' . $user['email'],
                    'Certificate '. $user['credential_id'].' - <a href='.$user['credential_url'].' target="_blank">link</a>');
            } else {
                // Show a checkbox if they don't.
                $mform->addElement('advcheckbox', 'users['.$user['id'].']',
                    $user['name'] . '    ' . $user['email'], null, array('group' => 1));
            }
        }
        $mform->addElement('html', '</div>');
    }
}
