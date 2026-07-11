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
use mod_quizquest\attempt_manager;
use mod_quizquest\question_picker;

/**
 * Web service: start or resume an attempt.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_attempt extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Start or resume an attempt and return the chat history plus the current question.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'quizquest');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quizquest:play', $context);
        $ispreview = has_capability('mod/quizquest:viewreports', $context);

        $quizquest = $DB->get_record('quizquest', ['id' => $cm->instance], '*', MUST_EXIST);
        $manager   = new attempt_manager();

        // Enforce the open/close window for students; previewing users may play any time.
        if (!$ispreview) {
            if (!empty($quizquest->timeopen) && time() < $quizquest->timeopen) {
                throw new \moodle_exception('error:notopenyet', 'mod_quizquest', '', userdate($quizquest->timeopen));
            }
            if (attempt_manager::is_closed($quizquest)) {
                if ($active = $manager->get_active_attempt($quizquest->id, $USER->id)) {
                    $manager->abandon_expired_attempt($active, $quizquest);
                }
                throw new \moodle_exception('error:closedon', 'mod_quizquest', '', userdate($quizquest->timeclose));
            }
        }

        $attempt   = $manager->get_or_create_attempt($quizquest, $USER->id, $ispreview);

        $pending = $manager->get_pending_response($attempt->id);
        $question = $pending
            ? question_picker::get_question_payload((int) $pending->questionid)
            : $manager->serve_question($quizquest, $attempt);

        return [
            'attemptid'    => (int) $attempt->id,
            'tally'        => (int) $attempt->stepstally,
            'steps'        => (int) $quizquest->steps,
            'showprogress' => (bool) $quizquest->showprogress,
            'messages'     => $manager->build_history($attempt->id),
            'question'     => $question,
            'canrestart'   => $manager->can_start_new_attempt($quizquest, $USER->id, $ispreview),
        ];
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attemptid'    => new external_value(PARAM_INT, 'Attempt ID'),
            'tally'        => new external_value(PARAM_INT, 'Current step tally'),
            'steps'        => new external_value(PARAM_INT, 'Steps needed to complete'),
            'showprogress' => new external_value(PARAM_BOOL, 'Whether to show the progress bar'),
            'messages'     => new external_multiple_structure(
                new external_single_structure([
                    'role'    => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'message' => new external_value(PARAM_RAW, 'Message text'),
                ]),
                'Chat history of already-answered turns'
            ),
            'question'     => new external_single_structure([
                'text'    => new external_value(PARAM_RAW, 'Question text'),
                'qtype'   => new external_value(PARAM_ALPHA, 'multichoice or shortanswer'),
                'choices' => new external_multiple_structure(
                    new external_single_structure([
                        'id'    => new external_value(PARAM_INT, 'Answer id'),
                        'label' => new external_value(PARAM_TEXT, 'Answer label'),
                    ]),
                    'Answer choices (empty for shortanswer)'
                ),
            ], 'The current question to answer'),
            'canrestart'   => new external_value(PARAM_BOOL, 'Whether the user can start another attempt'),
        ]);
    }
}
