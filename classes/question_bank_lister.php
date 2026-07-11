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

namespace mod_quizquest;

/**
 * Lists the question banks a user may draw questions from: the legacy course-context
 * category, any question bank activities in this course, and any question banks shared
 * to other courses or site-wide that the user holds capability to use.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_lister {
    /** @var string[] Capabilities that grant use of a shared question bank's questions. */
    protected const HAVING_CAP = ['moodle/question:useall', 'moodle/question:usemine'];

    /**
     * Returns the banks available to the current user, keyed by context id.
     *
     * @param int $courseid The course the quizquest activity belongs to
     * @return array<int, string> Context id => display label
     */
    public static function get_available_banks(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/lib/questionlib.php');

        $course = get_course($courseid);
        $coursecontext = \context_course::instance($courseid);

        $banks = [
            $coursecontext->id => get_string('coursequestionbank', 'mod_quizquest', format_string($course->fullname)),
        ];

        $shareable = \core_question\local\bank\question_bank_helper::get_activity_instances_with_shareable_questions(
            havingcap: self::HAVING_CAP
        );
        foreach ($shareable as $bank) {
            $formatted = $bank->get_formatted();
            $banks[$formatted->contextid] = $formatted->coursenamebankname;
        }

        return $banks;
    }
}
