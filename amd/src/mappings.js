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
    var options = {};
    const optionsMap = {
        coursefieldmapping: 'coursefieldoptions',
        coursecustomfieldmapping: 'coursecustomfieldoptions',
        userprofilefieldmapping: 'userprofilefieldoptions',
    };

    var mappings = {
        init: function(data) {
            options = data;
            mappings.setSelectValues();

            mappings.add = $('[id*="_add_new_line"]');
            mappings.add.on('click', mappings.addNewLine);
            
            mappings.list = $('.attribute_mapping');
            mappings.list.on('click', '.remove-line', mappings.removeLine);
        },

        addNewLine: function() {
            const section = $(this).attr('data-section');
            const data = {
                index: mappings.countLines(section)+1,
                section: section,
                accredibleoptions: options.accredibleoptions,
                moodleoptions: options[optionsMap[section]]
            };
            mappings.renderMappingLine(data,`#${section}_content`);
        },

        removeLine: function() {
            const index = $(this).attr("data-id");
            const section = $(this).attr("data-section");
            const mappingLineId = `#${section}_mapping_line_${index}`;
            $(mappingLineId).remove();
        },

        countLines: function(section) {
            return $(`[id*="${section}_mapping_line"]`).length;
        },

        setSelectValues: function() {
            $('.attribute_mapping select.form-control').each((_, element) => {
                const selectEl = $(element);
                const value = selectEl.attr('data-initial-value');
                selectEl.val(value);
            });
        },

        renderMappingLine: function(context, containerid) {
            Templates.renderForPromise('mod_accredible/mapping_line', context).then(function (_ref) {
              Templates.appendNodeContents(containerid, _ref.html, _ref.js);
            });
        },
    };
    return mappings; 
});
