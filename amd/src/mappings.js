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
    var mappings = {
        init: function() {
            mappings.add = $('#id_add_new_line');
            mappings.add.on('click', mappings.addNewLine);
            
            mappings.list = $('#id_my_mappings');
            mappings.list.on('click', '.remove-line', mappings.removeLine);
        },

        addNewLine: function() {
            const data = {
                index: mappings.countLines()+1,
            };
            mappings.renderMappingLine(data,'#id_my_mappings');
        },

        removeLine: function() {
            const index = $(this).attr("data-id");
            const mappingLineId = '#mapping_line_'+index;
            $(mappingLineId).remove();
        },

        countLines: function() {
            return $('[id*="mapping_line_"]').length;
        },

        renderMappingLine: function(context, containerid) {
            Templates.renderForPromise('mod_accredible/mapping_line', context).then(function (_ref) {
              Templates.appendNodeContents(containerid, _ref.html, _ref.js);
            });
        }
    };
    return mappings; 
});