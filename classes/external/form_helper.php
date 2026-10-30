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
            'courseid' => new \external_value(PARAM_INT, 'Course ID.'),
            'groupid' => new \external_value(PARAM_INT, 'Group ID.'),
            'instanceid' => new \external_value(PARAM_INT, 'Accredible Module ID.'),
        ]);
    }

    /**
     * Returns response schema for reload_users function.
     *
     * @return \external_description Result type
     */
    public static function reload_users_returns() {
        return new \external_single_structure([
            'users' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new \external_value(PARAM_RAW, 'User ID.'),
                    'email' => new \external_value(PARAM_RAW, 'User email'),
                    'name' => new \external_value(PARAM_RAW, 'User name'),
                    'credential_url' => new \external_value(PARAM_RAW, 'Credential URL.'),
                    'credential_id' => new \external_value(PARAM_RAW, 'Credential ID.'),
                ])
            ),
            'unissued_users' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new \external_value(PARAM_RAW, 'User ID.'),
                    'email' => new \external_value(PARAM_RAW, 'User email'),
                    'name' => new \external_value(PARAM_RAW, 'User name'),
                    'credential_url' => new \external_value(PARAM_RAW, 'Credential URL.'),
                    'credential_id' => new \external_value(PARAM_RAW, 'Credential ID.'),
                ])
            ),
        ]);
    }

    /**
     * Get the list of users enrolled to a course and fetch their credential by groupid.
     *
     * @param int $courseid the course from moodle to load the enrolled users.
     * @param int $groupid the group from accredible to check users certificates.
     * @param int $instanceid ID of the accredible activity instance.
     *
     * @return array of users.
     * @throws \required_capability_exception when the user cannot add or edit the activity.
     * @throws \dml_missing_record_exception when the activity does not belong to the course.
     */
    public static function reload_users($courseid, $groupid, $instanceid = null) {
        global $DB;

        $params = self::validate_parameters(
            self::reload_users_parameters(),
            ['courseid' => $courseid, 'groupid' => $groupid, 'instanceid' => $instanceid]
        );
        $coursecontext = \context_course::instance($params['courseid']);

        // Only the activity edit form needs this list, so require what that form requires.
        if ($params['instanceid']) {
            $accredible = $DB->get_record(
                'accredible',
                ['id' => $params['instanceid'], 'course' => $params['courseid']],
                'id',
                MUST_EXIST
            );
            $cm = get_coursemodule_from_instance('accredible', $accredible->id, $params['courseid'], false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            self::validate_context($context);
            require_capability('moodle/course:manageactivities', $context);
        } else {
            self::validate_context($coursecontext);
            // Same pair as can_add_moduleinfo() and course_allowed_module() in course/modedit.php.
            require_capability('moodle/course:manageactivities', $coursecontext);
            require_capability('mod/accredible:addinstance', $coursecontext);
        }

        $enrolledusers = get_enrolled_users($coursecontext, "mod/accredible:view", null, 'u.*', 'id');
        $users = [
            'users' => [],
            'unissued_users' => [],
        ];

        $userhelper = new users();
        $users['users'] = $userhelper->get_users_with_credentials($enrolledusers, $params['groupid']);
        $users['unissued_users'] = $userhelper->get_unissued_users($users['users'], $params['instanceid']);

        return $users;
    }
}
