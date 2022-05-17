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

namespace mod_accredible\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir  . '/externallib.php');
require_once($CFG->dirroot . '/mod/accredible/locallib.php');

use mod_accredible\local\users;

/**
 * Web service end point for the reload users filter.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @since      Moodle 27
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_helper extends \external_api {
    /**
     * Returns parameter types for reload_users function.
     *
     * @return \external_function_parameters Parameters
     */
    public static function reload_users_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course id.'),
            'groupid' => new \external_value(PARAM_INT, 'Group id.'),
        ]);
    }

    /**
     * Returns response schema for reload_users function.
     *
     * @return \external_description Result type
     */
    public static function reload_users_returns() {
        return new \external_multiple_structure(
            new \external_single_structure([
                    'id' => new \external_value(PARAM_RAW, 'User ID.'),
                    'email' => new \external_value(PARAM_RAW, 'User email'),
                    'name' => new \external_value(PARAM_RAW, 'User name'),
                    'credential_url' => new \external_value(PARAM_RAW, 'Credential URL.'),
                    'credential_id' => new \external_value(PARAM_RAW, 'Credential ID.'),
            ]));
    }

    /**
     * Get the list of users enrolled to a course and fetch their credential by groupid.
     *
     * @param int $courseid the course from moodle to load the enrolled users.
     * @param int $groupid the group from accredible to check users certificates.
     *
     * @return array of users.
     */
    public static function reload_users($courseid, $groupid) {
        $params = self::validate_parameters(self::reload_users_parameters(), array('courseid' => $courseid, 'groupid' => $groupid));
        $context = \context_course::instance($courseid);
        self::validate_context($context);

        $enrolledusers = get_enrolled_users($context, "mod/accredible:view", null, 'u.*', 'id');
        $userhelper = new users();
        $users = $userhelper->fetch_credentials_for_users($enrolledusers, $groupid);

        return $users;
    }
}
