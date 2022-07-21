// This file is part of Moodle - http://moodle.org/
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

/*
 * The module provides a function to display the select for mapping grades to custom attributes.
 *
 * @module    mod_accredible
 * @package   accredible
 * @copyright Accredible <dev@accredible.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    var t = {
        /**
         * Initialise the handling.
         */
        init: function() {
            $('input#id_includegradeattribute').on('change', t.includeGradeChanged);

            t.selectsContainer = $("#include-grade-select-container");

            if ($('input#id_includegradeattribute').is(':checked')) {
                t.selectsContainer.removeClass('hidden');
            }
        },

        /**
         * Source of data for Ajax element.
         */
        includeGradeChanged: function() {
            if ($('input#id_includegradeattribute').is(':checked')) {
                t.selectsContainer.removeClass('hidden');
            } else {
                t.selectsContainer.addClass('hidden');
            }
        }
    };
    return t;
});