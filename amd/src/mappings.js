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
    const element = {
        mappingSelects: '[id*="mapping_line"] select',
        addButton: '[id*="_add_new_line"]',
        list: '.attribute_mapping',
    };
    const requiresId = ['coursecustomfieldmapping', 'userprofilefieldmapping'];

    const mappings = {
        init: function() {
            mappings.setSelectValues();
            mappings.listenToAddAction();            
            mappings.listenToDeleteAction();
            mappings.listenToSelectChanges();
        },

        listenToAddAction: function() {
            const addButton = $(element.addButton);
            addButton.on('click', mappings.addNewLine);
            // enable/disable add button after max attributes added
            addButton.each((_,element) => {
                const section = $(element).attr("data-section");
                mappings.toggleAddButton(section);
            });
        },

        listenToDeleteAction: function() {
            $(element.list).on('click', '.remove-line', mappings.removeLine);
        },

        listenToSelectChanges: function() {
            $(element.list).on('change', element.mappingSelects, (event) => {
                mappings.checkForDuplicates();
            });
        },

        getAttributeValuesCount: function() {
            const valuesCount = new Map();
            $(element.mappingSelects).each((_,select) => {
                const value = $(select).val();
                if (!value) {
                    return;
                }
                const name = mappings.getOptionNameFromSelect(select);
                const key = `${name}_${value}`;
                let occurrences = valuesCount.get(key) ?? 0;
                occurrences++;
                valuesCount.set(key, occurrences);
            });
            return valuesCount;
        },

        checkForDuplicates: function() {
            const duplicateCount = mappings.getAttributeValuesCount();
            const rowHasError = {};
            let hasDuplicate = false;

            $(element.mappingSelects).each((_,select) => {
                const id = $(select).attr('id');
                const rowId = new RegExp(/(id_\w+_\d)_\w+/g).exec(id)[1]; // Get "id_{{section}}_{{index}}" part.
                const deleteIconWrapper = $(`#${rowId}_delete_action`);

                $(select).removeClass('is-invalid');

                const value = $(select).val();
                const name = mappings.getOptionNameFromSelect(select);
                const key = `${name}_${value}`;

                if (duplicateCount.get(key) > 1) {
                    $(select).addClass('is-invalid');
                    deleteIconWrapper.addClass('pb-xl-4');  // Applies padding to align delete icon.
                    hasDuplicate = true;
                    rowHasError[rowId] = true;
                }

                if (!rowHasError[rowId]) {
                    deleteIconWrapper.removeClass('pb-xl-4');
                }

                mappings.disableSubmit(hasDuplicate);
            });
        },

        addNewLine: function() {
            const section = $(this).attr('data-section');
            const options = mappings.getOptionsFromTemplate(section);
            const data = {
                index: mappings.countLines(section)+1,
                section: section,
                accredibleoptions: options.accredibleoptions,
                moodleoptions: options.moodleoptions,
                hasid: requiresId.includes(section)
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
            const maxLines = mappings.getMoodleOptionsCount(section);

            if (currentLines == maxLines) {
                addBtn.addClass('hidden');
            } else {
                addBtn.removeClass('hidden');
            }
        },

        getOptionNameFromSelect(select) {
            const value = $(select).val();
            const name = $(select).find(`option[value="${value}"]`)[0].innerHTML;

            return name.replaceAll(/\W+/g, '_'); // Replace all special chars to underscore.
        },

        getOptionsFromTemplate: function(section) {
            const template = $(`#template_${section}`)[0];
            if (!template) {
                return { accredibleoptions: [], moodleoptions: [] };
            }

            const accredibleselect = template.content.querySelector('#accredibleoptions_select');
            const moodleselect = template.content.querySelector('#moodleoptions_select');

            return {
                accredibleoptions: mappings.getOptionsFromSelect(accredibleselect),
                moodleoptions: mappings.getOptionsFromSelect(moodleselect)
            };
        },

        getOptionsFromSelect: function(select) {
            const options = $(select).find('option').toArray();

            return options.reduce((options, optionElement) => {
                options.push({
                    name: optionElement.innerHTML,
                    value: optionElement.value
                });
                return options;
            }, []);
        },

        getMoodleOptionsCount: function(section) {
            const template = $(`#template_${section}`)[0];
            if (!template) {
                return 0;
            }

            const moodleoptions = template.content.querySelector('#moodleoptions_select option');

            return moodleoptions.length - 1; // Excludes blank option.
        },

        disableSubmit: function(isFormInvalid) {
            $('input:submit[id*="id_submitbutton"]').each((_, button) => {
                $(button).attr('disabled', isFormInvalid);
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
