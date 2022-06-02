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

define(['jquery', 'core/ajax', 'core/templates'], function($, Ajax, Templates) {
    var t = {
        /**
         * Initialise the handling.
         */
        init: function() {
            $('select#id_groupid').on('change', t.groupChanged);

            t.lastSelectedGroup = $('select#id_groupid').val();
            t.courseid = $('input:hidden[name=course]').val();
            t.instanceid = $('input:hidden[name=instance-id]').val();
            t.userwarning = $('.manual-issue-warning');
            t.manualuserscontainer = $('#manual-issue-users-container');
            t.unissuedusers = $('#id_chooseunissuedusers');
            t.unissuedusersmessage = $('#fitem_id_unissueddescription');
            t.selectallbutton = $('#fitem_id_nosubmit_checkbox_controller1, #fitem_id_nosubmit_checkbox_controller2');
            
            if (t.lastSelectedGroup === '') {
                t.userwarning.removeClass('hidden');
                t.manualuserscontainer.addClass('hidden');
            }
            if ($('#unissued-users-container .form-group').length == 0) {
                t.unissuedusers.hide().addClass('collapsed');
            }
        },

        /**
         * Manage group changes from select dropdown.
         */
        groupChanged: function() {
            if ($('select#id_groupid').val() === t.lastSelectedGroup) {
                return;
            }
            t.lastSelectedGroup = $('select#id_groupid').val();
            if (t.lastSelectedGroup === '') {
                t.displayNoUsersWarning();
            } else {
                Ajax.call([{
                    methodname: 'mod_accredible_reload_users',
                    args: { courseid: t.courseid, groupid: $('select#id_groupid').val(), instanceid: t.instanceid}
                }])[0].done(t.updateUsers);

                t.userwarning.addClass('hidden');
                t.manualuserscontainer.removeClass('hidden');
                t.unissuedusersmessage.removeClass('hidden');
                t.selectallbutton.removeClass('hidden');
            }
        },

        /**
         * Hide users and display warning when no group is selected.
         */
        displayNoUsersWarning: function() {
            var userselements = $('#manual-issue-users-container .form-group, #unissued-users-container .form-group');
            userselements.remove();
            t.userwarning.removeClass('hidden');
            t.selectallbutton.addClass('hidden');
            t.manualuserscontainer.addClass('hidden');
            t.unissuedusersmessage.addClass('hidden');
            t.unissuedusers.show();
        },

        /**
         * Update the contents of the User list with the results of the AJAX call.
         *
         * @param {Array} response - array of users.
         */
        updateUsers: function(response) {
            var context;
            var userselements = $('#manual-issue-users-container .form-group, #unissued-users-container .form-group');
            userselements.remove();

            $(response.users).each(function(index, user) {
                if (user.credential_url) {
                    context = {
                        element: {
                            html: 'Certificate ' + user.credential_id + ' - <a href='+ user.credential_url +' target="_blank">link</a>',
                            staticlabel: true
                        },
                        label: user.name + '   ' + user.email
                    };
                } else {
                    context = {
                        element: {
                            id: 'id_users_' + user.id,
                            name: 'users['+ user.id +']',
                            extraclasses: 'checkboxgroup1',
                            selectedvalue: 1
                        },
                        label: user.name + '   ' + user.email
                    };
                }

                t.renderUser(context, '#manual-issue-users-container', user.credential_url);
            });

            if (response.unissued_users.length > 0) {
                t.unissuedusers.show();
                $(response.unissued_users).each(function(index, user) {
                    context = {
                        element: {
                            id: 'id_unissuedusers_' + user.id,
                            name: 'unissuedusers['+ user.id +']',
                            extraclasses: 'checkboxgroup2',
                            selectedvalue: 1
                        },
                        label: user.name + '   ' + user.email
                    };

                    t.renderUser(context, '#unissued-users-container', null);
                });    
            } else {
                t.unissuedusers.hide().addClass('collapsed');
            }
            
        },

        /**
         * Render the template with the user context.
         *
         * @param stdObject context - data for template.
         * @param string containerid - id of the html element where the template will get append.
         * @param string certificate - certificate url to select correct template.
         */
        renderUser: function(context, containerid, certificate) {
          var template = certificate ? 'core_form/element-static' : 'core_form/element-advcheckbox';
          
          Templates.renderForPromise(template, context).then(function (_ref) {
            Templates.appendNodeContents(containerid, _ref.html, _ref.js);
          });
        }
    };
    return t;
});
