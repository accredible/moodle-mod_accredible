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
