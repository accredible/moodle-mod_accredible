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
            var output = "";
            var userselements = $('#users-container .form-group').not('.femptylabel');

            userselements.remove();

            $(response).each(function(index, option) {
                if (option.credential_url) {
                    output += "<div id='fitem_id_certlink" + option.id + "' class='form-group row fitem'>";
                    output += "<div class='col-md-3 col-form-label d-flex pb-0 pr-md-0'>";
                    output += "<span class='d-inline-block'>" + option.name + "   " + option.email + "</span></div>";
                    output += "<div class='col-md-9 form-inline align-items-start felement' data-fieldtype='static'>";
                    output += "<div class='form-control-static'>Certificate " + option.credential_id;
                    output += " - <a href='" + option.credential_url + "' target='_blank'>link</a></div></div></div>";
                } else {
                    output += "<div class='form-group row fitem checkboxgroup1'>";
                    output += "<div class='col-md-3'></div><div class='col-md-9 checkbox'>";
                    output += "<div class='form-check d-flex'>";
                    output += "<input type='hidden' name='users[" + option.id + "]' value='0'>";
                    output += "<input type='checkbox' name='users[" + option.id + "]'"; 
                    output += "class='form-check-input checkboxgroup1' value='1' id='id_users_" + option.id + "'>";
                    output += "<label for='id_users_" + option.id + "'>";
                    output += option.name + "   " + option.email;
                    output += "</label></div></div></div>";
                }
            });

            t.userscontainer.append(output);
        }
    };
    return t;
});