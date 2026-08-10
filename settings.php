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
 * Provides some custom settings for the accredible module
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Global account. Kept as the fallback for activities with no brand set, and
// for installs that never configure brands at all.
// Later: language tags.
$settings->add(
    new admin_setting_configtext(
        'accredible_api_key',
        get_string('apikeylabel', 'accredible'),
        get_string('apikeyhelp', 'accredible'),
        ''
    )
);

$settings->add(
    new admin_setting_configcheckbox(
        'is_eu',
        get_string('eulabel', 'accredible'),
        get_string('euhelp', 'accredible'),
        ''
    )
);

// Brands. Each slot is one Accredible account; activities pick a brand by name
// and the plugin authenticates against that account instead of the global one.
$settings->add(
    new admin_setting_heading(
        'accredible_brands_heading',
        get_string('brandsheading', 'accredible'),
        get_string('brandsheadinghelp', 'accredible')
    )
);

for ($brandslot = 1; $brandslot <= \mod_accredible\local\brand_keys::SLOTS; $brandslot++) {
    $settings->add(
        new admin_setting_configtext(
            "accredible_brand{$brandslot}_name",
            get_string('brandnamelabel', 'accredible', $brandslot),
            get_string('brandnamehelp', 'accredible'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            "accredible_brand{$brandslot}_api_key",
            get_string('brandapikeylabel', 'accredible', $brandslot),
            get_string('brandapikeyhelp', 'accredible'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            "accredible_brand{$brandslot}_is_eu",
            get_string('brandeulabel', 'accredible', $brandslot),
            get_string('brandeuhelp', 'accredible'),
            0
        )
    );
}
