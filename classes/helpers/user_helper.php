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

namespace mod_accredible\helpers;

use mod_accredible\apirest\apirest;
use mod_accredible\local\credentials;

/**
 * Local functions related to credentials.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_helper {
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
     * Load list of users from a course and fetch their credentials from
     * the accredible group provided.
     *
     * @param context $context course context
     * @param int $groupid accredible group id
     * @return array the list of users
     */
    public function load_users_with_credentials_from_course_context($context, $groupid = null) {
        $users = array();
        $certificates = array();
        $enrolledusers = get_enrolled_users($context, "mod/accredible:view", null, 'u.*', 'id');

        if ($enrolledusers) {
            if ($groupid) {
                $credentialsclient = new credentials($this->apirest);
                $certificates = $credentialsclient->get_credentials($groupid);
            }

            foreach ($enrolledusers as $user) {
                $credentialurl = null;
                $credentialid = null;
                foreach ($certificates as $certificate) {
                    if ($certificate->recipient->email == strtolower($user->email)) {
                        $credentialid = $certificate->id;

                        if (isset($certificate->url)) {
                            $credentialurl = $certificate->url;
                        } else {
                            $credentialurl = 'https://www.credential.net/' . $certificate->id;
                        }
                        break;
                    }
                }
                $user = array(
                    'id'             => $user->id,
                    'email'          => $user->email,
                    'name'           => $user->firstname . ' ' . $user->lastname,
                    'credential_url' => $credentialurl,
                    'credential_id'  => $credentialid
                );
                array_push($users, $user);
            }
        }
        return $users;
    }
}
