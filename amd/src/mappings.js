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
    const acrredibleSelect = '[id*="_accredibleattribute"]';
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

            mappings.add.each((_,element) => {
                const section = $(element).attr("data-section");
                mappings.toggleAddButton(section);
            });

            mappings.list = $('.attribute_mapping');
            mappings.list.on('click', '.remove-line', mappings.removeLine);
            
            mappings.listenToSelectChanges();
        },

        listenToSelectChanges() {
            mappings.list.on('change', acrredibleSelect, (event) => {
                mappings.checkForDuplicates();
            });
        },

        getAttributeValuesCount: function() {
            const valuesCount = new Map();
            $(acrredibleSelect).each((_,select) => {
                const value = $(select).val();
                let occurrences = valuesCount.get(value) ?? 0;

                occurrences++;
                valuesCount.set(value, occurrences);
            });
            return valuesCount;
        },

        checkForDuplicates: function() {
            const duplicateCount = mappings.getAttributeValuesCount();

            $(acrredibleSelect).each((_,select) => {
                $(select).removeClass('is-invalid');

                const value = $(select).val();
                if (duplicateCount.get(value) > 1) {
                    $(select).addClass('is-invalid');
                }
            });
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
            // Wait for line to be rendered then show/hide the button.
            setTimeout(() => {
                mappings.toggleAddButton(section);
            }, 100);
        },

        removeLine: function() {
            const index = $(this).attr("data-id");
            const section = $(this).attr("data-section");
            const mappingLineId = `#${section}_mapping_line_${index}`;
            $(mappingLineId).remove();
            mappings.toggleAddButton(section);
            mappings.checkForDuplicates();
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

        toggleAddButton: function(section) {
           const addBtn = $(`#${section}_add_new_line`);
           const currentLines = mappings.countLines(section);
           const maxLines = options[optionsMap[section]].length - 1; // Excludes blank option.

           addBtn.prop("disabled", currentLines == maxLines);
        },

        renderMappingLine: function(context, containerid) {
            Templates.renderForPromise('mod_accredible/mapping_line', context).then(function (_ref) {
              Templates.appendNodeContents(containerid, _ref.html, _ref.js);
            });
        },
    };
    return mappings; 
});
