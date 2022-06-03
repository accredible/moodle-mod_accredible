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

namespace mod_accredible\apirest;

use mod_accredible\client\client;

/**
 * Class to make requests to Accredible API.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apirest {
    /**
     * API base URL.
     * Use `public` to make unit testing possible.
     * @var string $apiendpoint
     */
    public $apiendpoint;

    /**
     * HTTP request client.
     * @var stdObject $client
     */
    private $client;

    /**
     * Constructor method to define correct endpoints
     *
     * @param stdObject $client a mock client for testing
     */
    public function __construct($client = null) {
        global $CFG;

        $this->apiendpoint = 'https://api.accredible.com/v1/';

        if ($CFG->is_eu) {
            $this->apiendpoint = 'https://eu.api.accredible.com/v1/';
        }

        $devapiendpoint = getenv('ACCREDIBLE_DEV_API_ENDPOINT');
        if ($devapiendpoint) {
            $this->apiendpoint = $devapiendpoint;
        }

        // A mock client is passed when unit testing.
        if ($client) {
            $this->client = $client;
        } else {
            $this->client = new client();
        }
    }

    /**
     * Get Credentials
     * @param string|null $groupid
     * @param string|null $email
     * @param string|null $pagesize
     * @param string $page
     * @return stdObject
     */
    public function get_credentials($groupid = null, $email = null, $pagesize = null, $page = 1) {
        if ($email) {
            $email = strtolower($email);
        }
        return $this->client->get("{$this->apiendpoint}all_credentials?group_id={$groupid}&email=" .
            rawurlencode($email) . "&page_size={$pagesize}&page={$page}");
    }

    /**
     * Get a Credential with EnvidenceItems
     * @param int $credentialid
     * @return stdObject
     */
    public function get_credential($credentialid) {
        return $this->client->get("{$this->apiendpoint}credentials/{$credentialid}");
    }

    /**
     * Generaate a Single Sign On Link for a recipient for a particular credential.
     * @param string|null $credentialid
     * @param string|null $recipientid
     * @param string|null $recipientemail
     * @param string|null $walletview
     * @param string|null $groupid
     * @param string|null $redirectto
     * @return stdObject
     */
    public function recipient_sso_link($credentialid = null, $recipientid = null,
        $recipientemail = null, $walletview = null, $groupid = null, $redirectto = null) {

        if ($recipientemail) {
            $recipientemail = strtolower($recipientemail);
        }
        $data = array(
            "credential_id" => $credentialid,
            "recipient_id" => $recipientid,
            "recipient_email" => $recipientemail,
            "wallet_view" => $walletview,
            "group_id" => $groupid,
            "redirect_to" => $redirectto,
        );

        $data = $this->strip_empty_keys($data);

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}sso/generate_link", $data);
    }

    /**
     * Get attribute keys
     * @param int $pagesize
     * @param int $page
     * @return stdObject
     */
    public function search_attribute_keys($pagesize = 50, $page = 1) {
        $data = json_encode(array('page' => $page, 'page_size' => $pagesize, 'kind' => 'text'));
        return $this->client->post("{$this->apiendpoint}attribute_keys/search", $data);
    }

    /**
     * Creates a Credential given an existing Group
     * @param string $recipientname
     * @param string $recipientemail
     * @param string $courseid
     * @param date|null $issuedon
     * @param date|null $expiredon
     * @param stdObject|null $customattributes
     * @return stdObject
     */
    public function create_credential($recipientname, $recipientemail, $courseid,
        $issuedon = null, $expiredon = null, $customattributes = null) {

        $data = array(
            "credential" => array(
                "group_id" => $courseid,
                "recipient" => array(
                    "name" => $recipientname,
                    "email" => $recipientemail
                ),
                "issued_on" => $issuedon,
                "expired_on" => $expiredon,
                "custom_attributes" => $customattributes
            )
        );

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}credentials", $data);
    }

    /**
     * Creates an evidence item on a given credential. This is a general method used by more specific evidence item creations.
     * @param stdObject $evidenceitem
     * @param string $credentialid
     * @param bool $throwerror
     * @return stdObject
     */
    public function create_evidence_item($evidenceitem, $credentialid, $throwerror = false) {
        $data = json_encode($evidenceitem);
        $result = $this->client->post("{$this->apiendpoint}credentials/{$credentialid}/evidence_items", $data);
        if ($throwerror && $this->client->error) {
            throw new \moodle_exception(
                'evidenceadderror', 'accredible', 'https://help.accredible.com/hc/en-us', $credentialid, $this->client->error
            );
        }
        return $result;
    }

    /**
     * Creates a Grade evidence item on a given credential.
     * @param int $startdate
     * @param int $enddate
     * @param string $credentialid
     * @param bool $hidden
     * @return stdObject
     */
    public function create_evidence_item_duration($startdate, $enddate, $credentialid, $hidden = false) {

        $durationinfo = array(
            'start_date' => date("Y-m-d", $startdate),
            'end_date' => date("Y-m-d", $enddate),
            'duration_in_days' => floor( ($enddate - $startdate) / 86400)
        );

        // Multi day duration.
        if ($durationinfo['duration_in_days'] && $durationinfo['duration_in_days'] > 0) {

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => 'Completed in ' . $durationinfo['duration_in_days'] . ' days',
                    "category" => "course_duration",
                    "string_object" => json_encode($durationinfo),
                    "hidden" => $hidden
                )
            );

            $result = $this->create_evidence_item($evidenceitem, $credentialid);

            return $result;
            // It may be completed in one day.
        } else if ($durationinfo['end_date'] >= $durationinfo['start_date']) {
            $durationinfo['duration_in_days'] = 1;

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => 'Completed in 1 day',
                    "category" => "course_duration",
                    "string_object" => json_encode($durationinfo),
                    "hidden" => $hidden
                )
            );

            $result = $this->create_evidence_item($evidenceitem, $credentialid);

            return $result;

        } else {
            throw new \InvalidArgumentException("Enrollment duration must be greater than 0.");
        }
    }

    /**
     * Creates a Credential given an existing Group. This legacy method uses achievement names rather than group IDs.
     * @param string $recipientname
     * @param string $recipientemail
     * @param string $achievementname
     * @param date|null $issuedon
     * @param date|null $expiredon
     * @param string|null $coursename
     * @param string|null $coursedescription
     * @param string|null $courselink
     * @param stdObject|null $customattributes
     * @return stdObject
     */
    public function create_credential_legacy($recipientname, $recipientemail,
        $achievementname, $issuedon = null, $expiredon = null, $coursename = null,
        $coursedescription = null, $courselink = null, $customattributes = null) {

        $data = array(
            "credential" => array(
                "group_name" => $achievementname,
                "recipient" => array(
                    "name" => $recipientname,
                    "email" => $recipientemail
                ),
                "issued_on" => $issuedon,
                "expired_on" => $expiredon,
                "custom_attributes" => $customattributes,
                "name" => $coursename,
                "description" => $coursedescription,
                "course_link" => $courselink
            )
        );

        $data = json_encode($data);

        return $this->client->post("{$this->apiendpoint}credentials", $data);
    }

    /**
     * Get Group
     * @param int $id
     * @return stdObject
     */
    public function get_group($id) {
        return $this->client->get($this->apiendpoint.'issuer/groups/' . $id);
    }

    /**
     * Get all Groups
     * @param string $pagesize
     * @param string $page
     * @return stdObject
     */
    public function get_groups($pagesize = 50, $page = 1) {
        return $this->client->get($this->apiendpoint.'issuer/all_groups?page_size=' . $pagesize . '&page=' . $page);
    }

    /**
     * Get all Groups
     * @param int $pagesize
     * @param int $page
     * @return stdObject
     */
    public function search_groups($pagesize = 50, $page = 1) {
        $data = json_encode(array('page' => $page, 'page_size' => $pagesize));
        return $this->client->post("{$this->apiendpoint}issuer/groups/search", $data);
    }

    /**
     * Creates a Grade evidence item on a given credential.
     * @param string $grade - value must be between 0 and 100
     * @param string $description
     * @param string $credentialid
     * @param bool $hidden
     * @return stdObject
     */
    public function create_evidence_item_grade($grade, $description, $credentialid, $hidden = false) {

        if (is_numeric($grade) && intval($grade) >= 0 && intval($grade) <= 100) {

            $evidenceitem = array(
                "evidence_item" => array(
                    "description" => $description,
                    "category" => "grade",
                    "string_object" => (string) $grade,
                    "hidden" => $hidden
                )
            );

            return $this->create_evidence_item($evidenceitem, $credentialid);
        } else {
            throw new \InvalidArgumentException("$grade must be a numeric value between 0 and 100.");
        }
    }

    /**
     * Updates an evidence item on a given credential.
     * @param int $credentialid
     * @param int $evidenceitemid
     * @param string $grade - value must be between 0 and 100
     * @return stdObject
     */
    public function update_evidence_item_grade($credentialid, $evidenceitemid, $grade) {
        if (is_numeric($grade) && intval($grade) >= 0 && intval($grade) <= 100) {
            $evidenceitem = array('evidence_item' => array('string_object' => $grade));
            $data = json_encode($evidenceitem);
            $url = "{$this->apiendpoint}credentials/{$credentialid}/evidence_items/{$evidenceitemid}";
            return $this->client->put($url, $data);
        } else {
            throw new \InvalidArgumentException("$grade must be a numeric value between 0 and 100.");
        }
    }

    /**
     * Strip out keys with a null value from an object http://stackoverflow.com/a/15953991
     * @param stdObject $object
     * @return stdObject
     */
    private function strip_empty_keys($object) {

        $json = json_encode($object);
        $json = preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $json);
        $object = json_decode($json);

        return $object;
    }
}
