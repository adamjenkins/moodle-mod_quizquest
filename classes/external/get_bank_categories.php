<?php
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

namespace mod_quizquest\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quizquest\question_bank_lister;

/**
 * Web service: list the question categories in a chosen question bank, for the
 * activity settings form's bank -> category cascading select.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_bank_categories extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'      => new external_value(PARAM_INT, 'Course the activity belongs to'),
            'bankcontextid' => new external_value(PARAM_INT, 'Context id of the chosen question bank'),
        ]);
    }

    /**
     * Returns the category options for a question bank the user is authorised to use.
     *
     * @param int $courseid
     * @param int $bankcontextid
     * @return array
     */
    public static function execute(int $courseid, int $bankcontextid): array {
        [
            'courseid'      => $courseid,
            'bankcontextid' => $bankcontextid,
        ] = self::validate_parameters(self::execute_parameters(), compact('courseid', 'bankcontextid'));

        $coursecontext = \context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        // Never trust the client-supplied context id: re-derive the user's authorised
        // bank list server-side and only proceed if it's a genuine member.
        $allowedbanks = question_bank_lister::get_available_banks($courseid);
        if (!array_key_exists($bankcontextid, $allowedbanks)) {
            throw new \moodle_exception('error:bankaccessdenied', 'mod_quizquest');
        }

        $bankcontext = \context::instance_by_id($bankcontextid);
        $grouped = \qbank_managecategories\helper::question_category_options([$bankcontext], false, 0, false, -1, true);

        $categories = [];
        foreach ($grouped as $group) {
            foreach ($group as $value => $label) {
                $categories[] = ['value' => (string) $value, 'label' => (string) $label];
            }
        }

        return ['categories' => $categories];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Option value: "categoryid,contextid"'),
                    'label' => new external_value(PARAM_RAW, 'Formatted, HTML-escaped category name (indented for subcategories)'),
                ])
            ),
        ]);
    }
}
