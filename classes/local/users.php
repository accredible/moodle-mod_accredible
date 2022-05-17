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
    public function fetch_credentials_for_users($enrolledusers, $groupid = null) {
        $users = array();
        $certificates = array();

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
