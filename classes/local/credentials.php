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
     * Resolve a user id from an email so log events have a related user. Null when unknown.
     * @param string|null $email
     * @return int|null
     */
    private function userid_from_email($email) {
        global $DB;
        if (empty($email)) {
            return null;
        }
        $user = $DB->get_record('user', ['email' => $email], 'id', IGNORE_MULTIPLE);
        return $user ? $user->id : null;
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
        } catch (\Throwable $e) {
            throw new api_exception(
                'credentialcreateerror',
                'https://help.accredible.com/hc/en-us',
                (object) ['cause' => api_exception::cause_text($e)],
                // No $debuginfo: the cause is already in $a, and core would append it to the
                // message a second time on any box running DEBUG_DEVELOPER.
                null,
                $e
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
        } catch (\Throwable $e) {
            throw new api_exception(
                'credentialcreateerror',
                'https://help.accredible.com/hc/en-us',
                (object) ['cause' => api_exception::cause_text($e)],
                // No $debuginfo: the cause is already in $a, and core would append it to the
                // message a second time on any box running DEBUG_DEVELOPER.
                null,
                $e
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
        $userid = $this->userid_from_email($email);
        try {
            $loop = true;
            $count = 0;
            $credentials = [];
            // Query the Accredible API and loop until it returns that there is no next page.
            while ($loop === true) {
                $credentialspage = $this->apirest->get_credentials($groupid, $email, $pagesize, $page);

                $errmsg = $this->apirest->detect_error($credentialspage, $userid);
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
        } catch (\Throwable $e) {
            // The learner's email and the raw last response used to be passed here. Both now stay
            // out: this message is written to logstore_standard_log, relateduserid already
            // identifies the learner, and a decoded response is unbounded in size.
            throw new api_exception(
                'getcredentialserror',
                'https://help.accredible.com/hc/en-us',
                (object) ['cause' => api_exception::cause_text($e)],
                // No $debuginfo: the cause is already in $a, and core would append it to the
                // message a second time on any box running DEBUG_DEVELOPER.
                null,
                $e
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
        $userid = $this->userid_from_email($email);
        try {
            $credentials = $this->apirest->get_credentials($groupid, $email);

            $errmsg = $this->apirest->detect_error($credentials, $userid);
            if ($errmsg !== null) {
                throw new \Exception($errmsg);
            }

            if ($credentials->credentials && $credentials->credentials[0]) {
                return $credentials->credentials[0];
            } else {
                return false;
            }
        } catch (\Throwable $e) {
            // \Throwable, not \Exception: dereferencing an unexpected response shape below raises
            // an \Error on PHP 8, which would otherwise escape this catch entirely.
            throw new api_exception(
                'groupsyncerror',
                'https://help.accredible.com/hc/en-us',
                (object) ['cause' => api_exception::cause_text($e)],
                // No $debuginfo: the cause is already in $a, and core would append it to the
                // message a second time on any box running DEBUG_DEVELOPER.
                null,
                $e
            );
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
