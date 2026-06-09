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

/**
 * Local functions related to credentials.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
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
     * Create a credential given a user and an existing group
     * @param stdObject $user
     * @param int $groupid
     * @param date|null $issuedon
     * @param array $customattributes
     * @param \context|null $context Moodle context for log events; defaults to system context.
     * @return stdObject|null null when pre-flight rejects issuance; caller should treat as skip.
     */
    public function create_credential(
        $user,
        $groupid,
        $issuedon = null,
        $customattributes = null,
        $context = null
    ) {
        global $CFG;

        $ctx = $context ?? \context_system::instance();

        if (empty($user->email)) {
            \mod_accredible\event\credential_issue_skipped::create([
                'context'  => $ctx,
                'relateduserid' => $user->id,
                'other' => ['reason' => 'missing_email', 'groupid' => $groupid],
            ])->trigger();
            return null;
        }

        if (empty($groupid)) {
            \mod_accredible\event\credential_issue_skipped::create([
                'context'  => $ctx,
                'relateduserid' => $user->id,
                'other' => ['reason' => 'missing_groupid'],
            ])->trigger();
            return null;
        }

        try {
            $credential = $this->apirest->create_credential(
                fullname($user),
                $user->email,
                $groupid,
                $issuedon,
                null,
                $customattributes
            );

            $errmsg = $this->apirest->detect_error($credential, $user->id);
            if ($errmsg !== null) {
                throw new \Exception($errmsg);
            }

            \mod_accredible\event\credential_issued::create([
                'context'  => $ctx,
                'relateduserid' => $user->id,
                'other' => ['credentialid' => $credential->credential->id, 'groupid' => $groupid],
            ])->trigger();

            return $credential->credential;
        } catch (\Exception $e) {
            throw new \moodle_exception(
                'credentialcreateerror',
                'accredible',
                'https://help.accredible.com/hc/en-us',
                $user->email,
                $groupid
            );
        }
    }

    /**
     * Create a credential given a user and an existing group
     * @param stdObject $user
     * @param string $achievementname
     * @param string $coursename
     * @param string $coursedescription
     * @param int $courselink
     * @param int $issuedon
     * @param array $customattributes
     * @return stdObject
     */
    public function create_credential_legacy(
        $user,
        $achievementname,
        $coursename,
        $coursedescription,
        $courselink,
        $issuedon,
        $customattributes = null
    ) {
        global $CFG;
        try {
            $credential = $this->apirest->create_credential_legacy(
                fullname($user),
                $user->email,
                $achievementname,
                $issuedon,
                null,
                $coursename,
                $coursedescription,
                $courselink,
                $customattributes
            );

            $errmsg = $this->apirest->detect_error($credential, $user->id);
            if ($errmsg !== null) {
                throw new \Exception($errmsg);
            }

            // The legacy (achievement-name) path emits certificate_created from its
            // callers, not credential_issued (which is the modern group-based event).
            return $credential->credential;
        } catch (\Exception $e) {
            throw new \moodle_exception(
                'credentialcreateerror',
                'accredible',
                'https://help.accredible.com/hc/en-us',
                $user->email,
                $achievementname
            );
        }
    }

    /**
     * List all of the certificates with a specific achievement id
     * @param string $groupid Limit the returned Credentials to a specific group ID.
     * @param string|null $email Limit the returned Credentials to a specific recipient's email address.
     * @return array[stdClass] $credentials
     */
    public function get_credentials($groupid, $email = null) {
        global $CFG;
        $pagesize = 50;
        $page = 1;

        // Maximum number of pages to request to avoid possible infinite loop.
        $looplimit = 100;
        try {
            $loop = true;
            $count = 0;
            $credentials = [];
            // Query the Accredible API and loop until it returns that there is no next page.
            while ($loop === true) {
                $credentialspage = $this->apirest->get_credentials($groupid, $email, $pagesize, $page);

                $errmsg = $this->apirest->detect_error($credentialspage);
                if ($errmsg !== null) {
                    throw new \Exception($errmsg);
                }

                foreach ($credentialspage->credentials as $credential) {
                    $credentials[] = $credential;
                }

                $page++;
                $count++;
                if ($credentialspage->meta->next_page === null || $count >= $looplimit) {
                    // If the Accredible API returns that there
                    // is no next page, end the loop.
                    $loop = false;
                }
            }
            return $credentials;
        } catch (\Exception $e) {
            // Throw API exception.
            // Include the achievement id that triggered the error.
            // Direct the user to accredible's support.
            // Dump the achievement id to debug_info.
            $exceptionparam = new \stdClass();
            $exceptionparam->groupid = $groupid;
            $exceptionparam->email = $email;
            if (isset($credentialspage)) {
                $exceptionparam->last_response = $credentialspage;
            }
            throw new \moodle_exception(
                'getcredentialserror',
                'accredible',
                'https://help.accredible.com/hc/en-us',
                $exceptionparam
            );
        }
    }

    /**
     * Check's if a credential exists for an email in a particular group
     * @param int $groupid
     * @param string $email
     * @return array[stdClass] || false
     */
    public function check_for_existing_credential($groupid, $email) {
        global $CFG;
        try {
            $credentials = $this->apirest->get_credentials($groupid, $email);

            $errmsg = $this->apirest->detect_error($credentials);
            if ($errmsg !== null) {
                throw new \Exception($errmsg);
            }

            if ($credentials->credentials && $credentials->credentials[0]) {
                return $credentials->credentials[0];
            } else {
                return false;
            }
        } catch (\Exception $e) {
            // Throw API exception
            // include the achievement id that triggered the error
            // direct the user to accredible's support
            // dump the achievement id to debug_info.
            throw new \moodle_exception('groupsyncerror', 'accredible', 'https://help.accredible.com/hc/en-us', $groupid, $groupid);
        }
    }

    /**
     * Check's if a credential exists for an user in a particular group
     * @param int $achievementid
     * @param stdObject $user
     * @return array[stdClass] || false
     */
    public function check_for_existing_certificate($achievementid, $user) {
        global $DB;
        $existingcertificate = false;
        $certificates = $this->get_credentials($achievementid, $user->email);

        foreach ($certificates as $certificate) {
            if ($certificate->recipient->email == $user->email) {
                $existingcertificate = $certificate;
            }
        }
        return $existingcertificate;
    }
}
