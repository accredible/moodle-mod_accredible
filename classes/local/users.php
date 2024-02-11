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

require_once($CFG->dirroot . '/mod/accredible/locallib.php');
require_once($CFG->libdir  . '/grade/grade_item.php');

use mod_accredible\apirest\apirest;
use mod_accredible\local\credentials;

/**
 * Local functions related to users.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users {
    /**
     * HTTP request apirest.
     * @var apirest
     */
    private $apirest;

    /**
     * Constructor method
     *
     * @param stdObject $apirest a mock apirest for testing.
     */
    public function __construct($apirest = null) {
        // A mock apirest is passed when unit testing.
        if ($apirest) {
            $this->apirest = $apirest;
        } else {
            $this->apirest = new apirest();
        }
    }

    /**
     * Receive a list of users and fetch their credentials from
     * the accredible group provided.
     *
     * @param array $enrolledusers array of users
     * @param int $groupid accredible group id
     * @return array the list of users
     */
    public function get_users_with_credentials($enrolledusers, $groupid = null) {
        $users = array();
        $certificates = array();

        if (!$enrolledusers) {
            return $users;
        }

        $certificatesmemo = array();
        if ($groupid) {
            try {
                $credentialsclient = new credentials($this->apirest);
                $certificates = $credentialsclient->get_credentials($groupid);
            } catch (\moodle_exception $e) {
                return $users;
            }

            foreach ($certificates as $certificate) {
                if (isset($certificate->url)) {
                    $credentialurl = $certificate->url;
                } else {
                    $credentialurl = 'https://www.credential.net/' . $certificate->id;
                }
                $certificatesmemo[$certificate->recipient->email] = array(
                    'credentialid' => $certificate->id,
                    'credentialurl' => $credentialurl
                );
            }
        }

        foreach ($enrolledusers as $user) {
            if (isset($certificatesmemo[strtolower($user->email)])) {
                $certificate = $certificatesmemo[strtolower($user->email)];
            } else {
                $certificate = null;
            }

            $credentialurl = isset($certificate) ? $certificate['credentialurl'] : null;
            $credentialid = isset($certificate) ? $certificate['credentialid'] : null;
            $user = array(
                'id'             => $user->id,
                'email'          => $user->email,
                'name'           => $user->firstname . ' ' . $user->lastname,
                'credential_url' => $credentialurl,
                'credential_id'  => $credentialid
            );
            array_push($users, $user);
        }
        return $users;
    }

    /**
     * Receive a list of users and return only those who don't have a credential
     * and they have pass the requirements for the course.
     *
     * @param array $users array of users
     * @param int $accredibleinstanceid accredible module id
     * @return array list of users
     */
    public function get_unissued_users($users, $accredibleinstanceid = null) {
        global $DB;
        $unissuedusers = array();

        if ($accredibleinstanceid || $accredibleinstanceid != 0) {
            $accrediblecertificate = $DB->get_record('accredible', array('id' => $accredibleinstanceid), '*', MUST_EXIST);

            foreach ($users as $user) {
                if (!$user['credential_id'] && accredible_check_if_cert_earned($accrediblecertificate, $user)) {
                    array_push($unissuedusers, $user);
                }
            }
        }

        return $unissuedusers;
    }

    /**
     * Get user grades from a grade item.
     * @param stdObject $accredible the accredible activity object.
     * @param array|int $userids array of user IDs or a single ID.
     * @return array[stdClass] $usergrades
     */
    public function get_user_grades($accredible, $userids) {
        global $DB;

        if (!isset($accredible) || !isset($accredible->includegradeattribute) || !$accredible->gradeattributegradeitemid
            || empty($accredible->gradeattributekeyname) || empty($userids)) {
            return;
        }

        $usergrades = array();
        $gradeitemdb = $DB->get_record('grade_items', array('id' => $accredible->gradeattributegradeitemid), '*', MUST_EXIST);
        $gradeitem = new \grade_item($gradeitemdb);

        $queryparams = array('gradeitem' => $gradeitem->id);
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $queryparams += $params;
        $grades = $DB->get_records_select('grade_grades', 'itemid = :gradeitem AND userid '.$insql, $queryparams);

        foreach ($grades as $grade) {
            if ($grade->finalgrade) {
                $usergrades[$grade->userid] = grade_format_gradevalue($grade->finalgrade, $gradeitem);
            }
        }

        return $usergrades;
    }

    /**
     * Find user grade and map it with custom attribute name.
     * @param stdObject $accredible the accredible activity object.
     * @param array $grades array of user grades.
     * @param int $userid
     * @return array $customattributes
     */
    public function load_user_grade_as_custom_attributes($accredible, $grades, $userid) {

        if (isset($grades) && isset($grades[$userid])) {
            $customattributes = array($accredible->gradeattributekeyname => $grades[$userid]);
        } else {
            $customattributes = null;
        }

        $otherattrs = null;
        if ($accredible->otheroptions) { //not null
            $otherattrs = json_decode($accredible->otheroptions, true);
        }

        if ($otherattrs) { 
            if ($customattributes) {    
                $customattributes = array_merge($otherattrs, $customattributes);
            } else {
                $customattributes = $otherattrs;
            }

        }

        return $customattributes;
    }
}
