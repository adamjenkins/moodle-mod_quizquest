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

/**
 * AMD module: cascading question bank -> category select on the activity settings form.
 *
 * The server always renders the currently-selected (or default) bank's categories, so
 * this module only needs to act when the teacher changes the bank.
 *
 * @module     mod_quizquest/bankpicker
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /**
     * Whether the category select currently has an option with the given value.
     *
     * @param {HTMLSelectElement} select
     * @param {string} value
     * @returns {boolean}
     */
    var hasOption = function(select, value) {
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === value) {
                return true;
            }
        }
        return false;
    };

    /**
     * Fetches and repopulates the category select for the chosen bank.
     *
     * @param {number} courseId
     * @param {number} bankContextId
     * @param {HTMLSelectElement} categorySelect
     * @returns {Promise}
     */
    var loadCategories = function(courseId, bankContextId, categorySelect) {
        var previous = categorySelect.value;
        categorySelect.innerHTML = '';

        if (!bankContextId) {
            return Promise.resolve();
        }

        return Ajax.call([{
            methodname: 'mod_quizquest_get_bank_categories',
            args: {courseid: courseId, bankcontextid: bankContextId},
        }])[0].then(function(result) {
            // The labels arrive HTML-escaped, with literal &nbsp; entities indenting
            // subcategories. Decode them in a detached textarea (which never executes
            // markup) and assign the resulting plain text, so no server HTML ever
            // reaches the live DOM.
            var decoder = document.createElement('textarea');
            result.categories.forEach(function(category) {
                var option = document.createElement('option');
                option.value = category.value;
                decoder.innerHTML = category.label;
                option.textContent = decoder.value;
                categorySelect.appendChild(option);
            });
            if (previous && hasOption(categorySelect, previous)) {
                categorySelect.value = previous;
            }
            return null;
        }).catch(Notification.exception);
    };

    return {
        /** Initialises the bank -> category cascading select. */
        init: function() {
            var bankSelect = document.getElementById('id_questionbank');
            var categorySelect = document.getElementById('id_questioncategoryid');
            if (!bankSelect || !categorySelect) {
                return;
            }
            var courseId = parseInt(bankSelect.dataset.courseid, 10) || 0;

            bankSelect.addEventListener('change', function() {
                loadCategories(courseId, parseInt(bankSelect.value, 10) || 0, categorySelect);
            });
        },
    };
});
