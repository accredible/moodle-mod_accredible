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

/**
 * Resolves which Accredible account (API key + region) an activity talks to.
 *
 * Brands are configured as fixed slots in the plugin settings. An activity
 * stores the brand name it belongs to; this class turns that name into the
 * credentials the HTTP client needs. Anything unmapped falls back to the
 * legacy global settings so existing installs keep working untouched.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class brand_keys {
    /**
     * Number of brand slots rendered by settings.php.
     */
    const SLOTS = 3;

    /**
     * Resolve the account for a brand name.
     *
     * Falls back to the global settings when the brand is empty, unknown, or
     * configured without a key.
     *
     * @param string|null $brand Brand name as stored on the activity.
     * @return array{api_key: string, is_eu: bool}
     */
    public static function for_brand($brand) {
        $fallback = self::global_account();

        if (empty($brand)) {
            return $fallback;
        }

        foreach (self::all() as $configured) {
            if (strcasecmp($configured['name'], (string) $brand) !== 0) {
                continue;
            }
            // A named slot with no key is half-configured: degrade rather than
            // send an empty Authorization header.
            if ($configured['api_key'] === '') {
                return $fallback;
            }
            return [
                'api_key' => $configured['api_key'],
                'is_eu'   => $configured['is_eu'],
            ];
        }

        return $fallback;
    }

    /**
     * Every configured brand, in slot order. Slots without a name are skipped.
     *
     * @return array<int, array{name: string, api_key: string, is_eu: bool}>
     */
    public static function all() {
        $brands = [];

        for ($slot = 1; $slot <= self::SLOTS; $slot++) {
            $name = trim(self::config("accredible_brand{$slot}_name"));
            if ($name === '') {
                continue;
            }
            $brands[] = [
                'name'    => $name,
                'api_key' => trim(self::config("accredible_brand{$slot}_api_key")),
                'is_eu'   => (bool) self::config("accredible_brand{$slot}_is_eu"),
            ];
        }

        return $brands;
    }

    /**
     * The brand a course belongs to, derived from its category tree.
     *
     * Brands are delimited by the root categories: the root category's
     * idnumber is the brand name. Subcategories are internal organisation and
     * carry no idnumber of their own, so a course several levels deep resolves
     * through its path up to depth 1.
     *
     * Returns the configured brand name (in its configured casing) so the
     * result can be used directly as a form value, or null when the root has
     * no idnumber or names a brand that is not configured. Null means "use the
     * global account", which is the safe default in both cases.
     *
     * @param \stdClass|int|null $courseorid a course record or its id
     * @return string|null
     */
    public static function brand_from_course($courseorid) {
        $idnumber = self::root_idnumber_for_course($courseorid);

        if ($idnumber === '') {
            return null;
        }

        foreach (self::all() as $configured) {
            if (strcasecmp($configured['name'], $idnumber) === 0) {
                return $configured['name'];
            }
        }

        return null;
    }

    /**
     * The brand an activity issues against.
     *
     * A saved activity carries its own brand, including the empty one meaning
     * the global account. One that has not been saved yet has no brand of its
     * own, so its course category answers instead. Callers that only know the
     * course and instance id -- the web service, for one -- use this so the
     * client never has to send the brand.
     *
     * @param int|null $instanceid accredible activity instance, 0 when creating
     * @param \stdClass|int|null $courseorid
     * @return string|null null meaning the global account
     */
    public static function brand_for_activity($instanceid, $courseorid) {
        global $DB;

        if (!empty($instanceid)) {
            $record = $DB->get_record('accredible', ['id' => (int) $instanceid], 'id, brand');
            if ($record) {
                return empty($record->brand) ? null : $record->brand;
            }
        }

        return self::brand_from_course($courseorid);
    }

    /**
     * The idnumber of the root category a course hangs from, verbatim.
     *
     * Kept separate from brand_from_course() so callers can tell "no idnumber
     * set" apart from "idnumber set but matching no configured brand", which
     * is the difference between an unconfigured category and a typo.
     *
     * @param \stdClass|int|null $courseorid a course record or its id
     * @return string empty when it cannot be resolved
     */
    public static function root_idnumber_for_course($courseorid) {
        global $DB;

        if (empty($courseorid)) {
            return '';
        }

        if (is_object($courseorid)) {
            $categoryid = (int) ($courseorid->category ?? 0);
        } else {
            $categoryid = (int) $DB->get_field('course', 'category', ['id' => (int) $courseorid]);
        }

        // The site course lives outside the category tree.
        if ($categoryid <= 0) {
            return '';
        }

        $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id, idnumber, path');
        if (!$category) {
            return '';
        }

        $rootid = self::root_id_from_path($category->path, (int) $category->id);
        if ($rootid === (int) $category->id) {
            $root = $category;
        } else {
            $root = $DB->get_record('course_categories', ['id' => $rootid], 'id, idnumber');
        }

        return $root ? trim((string) $root->idnumber) : '';
    }

    /**
     * First id in a category path, which is the root of that branch.
     *
     * @param string $path a category path such as /2/5/6
     * @param int $fallback returned when the path is unusable
     * @return int
     */
    private static function root_id_from_path($path, $fallback) {
        $segments = array_values(array_filter(explode('/', (string) $path), 'strlen'));

        return empty($segments) ? $fallback : (int) $segments[0];
    }

    /**
     * Brand names for a form select, keyed by the value stored on the activity.
     *
     * @return array<string, string>
     */
    public static function menu() {
        $menu = [];

        foreach (self::all() as $brand) {
            $menu[$brand['name']] = $brand['name'];
        }

        return $menu;
    }

    /**
     * Whether any brand is configured. When false the plugin behaves exactly
     * as it did before multi-brand support.
     *
     * @return bool
     */
    public static function is_configured() {
        return !empty(self::all());
    }

    /**
     * The legacy single-account settings.
     *
     * @return array{api_key: string, is_eu: bool}
     */
    public static function global_account() {
        return [
            'api_key' => trim(self::config('accredible_api_key')),
            'is_eu'   => (bool) self::config('is_eu'),
        ];
    }

    /**
     * Read a global config value. The plugin stores its settings unprefixed,
     * so they land in $CFG rather than in the plugin config table.
     *
     * @param string $name
     * @return string
     */
    private static function config($name) {
        global $CFG;

        return isset($CFG->{$name}) ? (string) $CFG->{$name} : '';
    }
}
