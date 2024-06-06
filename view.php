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

/**
 * Handles viewing a certificate
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("$CFG->dirroot/mod/accredible/lib.php");
use mod_accredible\local\credentials;

$id = required_param('id', PARAM_INT); // Course Module ID.

$cm = get_coursemodule_from_id('accredible', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$accrediblecertificate = $DB->get_record('accredible', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course->id, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/accredible:view', $context);

// Initialize $PAGE, compute blocks.
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/accredible/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(format_string($accrediblecertificate->name));
$PAGE->set_heading(format_string($course->fullname));

$localcredentials = new credentials();

// User has admin privileges, show table of certificates.
if (has_capability('mod/accredible:manage', $context)) {

    // Get array of certificates.
    if ($accrediblecertificate->achievementid) { // Legacy achievment ID.
        $certificates = $localcredentials->get_credentials($accrediblecertificate->achievementid);
    } else { // Group id.
        $certificates = $localcredentials->get_credentials($accrediblecertificate->groupid);
    }

    $table = new html_table();
    $table->head = [
        get_string('id', 'accredible'),
        get_string('recipient', 'accredible'),
        get_string('certificateurl', 'accredible'),
        get_string('datecreated', 'accredible'),
    ];

    foreach ($certificates as $certificate) {
        $issuedate = date_format( date_create($certificate->issued_on), "M d, Y" );
        if (isset($certificate->url)) {
            $certificatelink = $certificate->url;
        } else {
            $certificatelink = 'https://www.credential.net/'.$certificate->id;
        }
          $table->data[] = [
              $certificate->id,
              $certificate->recipient->name,
              "<a href='$certificatelink' target='_blank'>$certificatelink</a>",
              $issuedate,
          ];
    }

    echo $OUTPUT->header();
    echo html_writer::tag( 'h3', get_string('viewheader', 'accredible', $accrediblecertificate->name) );
    if ($accrediblecertificate->groupid) {
        echo html_writer::tag( 'h5', get_string('viewsubheader', 'accredible', $accrediblecertificate->groupid) );
    } else {
        echo html_writer::tag( 'h5', get_string('viewsubheaderold', 'accredible', $accrediblecertificate->achievementid) );
    }

    echo html_writer::tag( 'p', get_string('gotodashboard', 'accredible') );

    echo html_writer::tag( 'br', null );
    echo html_writer::table($table);
    echo $OUTPUT->footer($course);
} else {
    // Regular user, Check for this user's certificate.
    $userscertificatelink = null;

    if ($accrediblecertificate->achievementid) { // Legacy achievment ID.
        $certificates = $localcredentials->get_credentials($accrediblecertificate->achievementid, $USER->email);
    } else { // Group id.
        $certificates = $localcredentials->get_credentials($accrediblecertificate->groupid, $USER->email);
    }

    if ($accrediblecertificate->groupid) {
        $userscertificatelink = accredible_get_recipient_sso_linik($accrediblecertificate->groupid, $USER->email);
    } else { // Legacy achievment ID.
        foreach ($certificates as $certificate) {
            if ($certificate->recipient->email == $USER->email) {
                if (isset($certificate->url)) {
                    $userscertificatelink = $certificate->url;
                } else {
                    $userscertificatelink = 'https://www.credential.net/'.$certificate->id;
                }
            }
        }
    }

    // Echo the page.
    echo $OUTPUT->header();

    if ($userscertificatelink) {

        if (method_exists($PAGE->theme, 'image_url')) {
            $src = $OUTPUT->image_url('incomplete_cert', 'accredible');
        } else {
            $src = $OUTPUT->pix_url('incomplete_cert', 'accredible');
        }

        echo html_writer::start_div('text-center');
        echo html_writer::tag( 'br', null );
        if ($certificates && $certificates[0] && $certificates[0]->seo_image) {
            // If we have a certificate, display a large image - else a small one for a badge.
            if ($certificates && $certificates[0] && $certificates[0]->certificate->image->preview &&
                strlen($certificates[0]->certificate->image->preview) > 0) {
                $img = html_writer::img($certificates[0]->seo_image,
                    get_string('viewimgcomplete', 'accredible'), ['width' => '90%'] );
            } else {
                $img = html_writer::img($certificates[0]->seo_image,
                    get_string('viewimgcomplete', 'accredible'), ['width' => '25%'] );
            }
        } else {
            $img = html_writer::img($src, get_string('viewimgcomplete', 'accredible'), ['width' => '90%'] );
        }

        echo html_writer::link( $userscertificatelink, $img, ['target' => '_blank'] );
        echo html_writer::end_div('text-center');
    } else {
        if (method_exists($PAGE->theme, 'image_url')) {
            $src = $OUTPUT->image_url('incomplete_cert', 'accredible');
        } else {
            $src = $OUTPUT->pix_url('incomplete_cert', 'accredible');
        }

        echo html_writer::start_div('text-center');
        echo html_writer::tag( 'br', null );
        echo html_writer::img($src, get_string('viewimgincomplete', 'accredible'), ['width' => '90%'] );
        echo html_writer::end_div('text-center');
    }

    echo $OUTPUT->footer($course);
}
