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
     * There is no account to fall back to: every request belongs to a brand,
     * and a request that cannot name one is a bug, not something to paper over
     * by talking to some default account. So this throws rather than returning
     * a usable-looking result.
     *
     * @param string|null $brand Brand name as stored on the activity.
     * @return array{api_key: string, is_eu: bool}
     * @throws \moodle_exception when the brand is missing, unknown or keyless
     */
    public static function for_brand($brand) {
        if (empty($brand)) {
            throw new \moodle_exception('brandmissing', 'accredible');
        }

        foreach (self::all() as $configured) {
            if (strcasecmp($configured['name'], (string) $brand) !== 0) {
                continue;
            }
            if ($configured['api_key'] === '') {
                throw new \moodle_exception('brandwithoutkey', 'accredible', '', $configured['name']);
            }
            return [
                'api_key' => $configured['api_key'],
                'is_eu'   => $configured['is_eu'],
            ];
        }

        throw new \moodle_exception('brandunknown', 'accredible', '', (string) $brand);
    }

    /**
     * Whether a brand name resolves to a usable account.
     *
     * For callers that need to branch instead of failing, such as form
     * validation deciding whether to show an error.
     *
     * @param string|null $brand
     * @return bool
     */
    public static function is_usable($brand) {
        if (empty($brand)) {
            return false;
        }

        foreach (self::all() as $configured) {
            if (strcasecmp($configured['name'], (string) $brand) === 0) {
                return $configured['api_key'] !== '';
            }
        }

        return false;
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
     * no idnumber or names a brand that is not configured. Null means no brand
     * could be derived, which the activity form turns into a validation error:
     * there is no account to fall back to.
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
     * A saved activity carries its own brand. One that has not been saved yet
     * does not, and neither does a row left over from before the brand was
     * mandatory, so the course category answers for both. This mirrors what
     * the activity form preselects, which is what keeps the form and the web
     * service reading the same account.
     *
     * @param int|null $instanceid accredible activity instance, 0 when creating
     * @param \stdClass|int|null $courseorid
     * @return string|null null when nothing names a brand
     */
    public static function brand_for_activity($instanceid, $courseorid) {
        global $DB;

        if (!empty($instanceid)) {
            $record = $DB->get_record('accredible', ['id' => (int) $instanceid], 'id, brand');
            if ($record && !empty($record->brand)) {
                return $record->brand;
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
