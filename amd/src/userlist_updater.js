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
 * The module provides a function to refresh the user list with correct credential data.
 *
 * @module    mod_accredible
 * @package   accredible
 * @copyright Accredible <dev@accredible.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {
    var t = {
        /**
         * Initialise the handling.
         */
        init: function() {
            $('select#id_groupid').on('change', t.groupChanged);
            t.lastGroup = $('select#id_groupid').val();
            t.courseid = $('input:hidden[name=course]').val();
            t.userwarning = $('#users-warning');
            t.userscontainer = $('#users-container');
            if (t.lastGroup === '') {
                t.userwarning.removeClass('hidden');
                t.userscontainer.addClass('hidden');
            }
        },

        /**
         * Source of data for Ajax element.
         */
        groupChanged: function() { 
            if ($('select#id_groupid').val() === t.lastGroup) {
                return;
            }
            t.lastGroup = $('select#id_groupid').val();

            if (t.lastGroup === '') {
                t.updateChoices([]);
                t.userwarning.removeClass('hidden');
                t.userscontainer.addClass('hidden');
            } else {
                Ajax.call([{
                    methodname: 'mod_accredible_reload_users',
                    args: { courseid: t.courseid, groupid: $('select#id_groupid').val()}
                }])[0].done(t.updateChoices);
                t.userwarning.addClass('hidden');
                t.userscontainer.removeClass('hidden');
            }
        },

        /**
         * Update the contents of the User list with the results of the AJAX call.
         *
         * @param {Array} response - array of users.
         */
        updateChoices: function(response) {
            var form = new DOMParser().parseFromString(response, "text/html");

            var userselements = $('#users-container .form-group').not('.femptylabel');

            userselements.remove();

            t.userscontainer.append($(form).find('#users-list > div'));
        }
    };
    return t;
});