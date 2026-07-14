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
use mod_quizquest\message_bank;
use mod_quizquest\question_picker;

/**
 * Web service: submit an answer to the current question and receive the next one.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_answer extends external_api {
    /**
     * Defines the parameters for this web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'attemptid'  => new external_value(PARAM_INT, 'Attempt ID'),
            'answerid'   => new external_value(PARAM_INT, 'Chosen answer id (multichoice)', VALUE_DEFAULT, 0),
            'answertext' => new external_value(PARAM_TEXT, 'Typed answer (shortanswer)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Evaluate the student's answer, update progress, and serve the next question.
     *
     * @param int    $cmid
     * @param int    $attemptid
     * @param int    $answerid
     * @param string $answertext
     * @return array
     */
    public static function execute(int $cmid, int $attemptid, int $answerid, string $answertext): array {
        global $DB, $USER;

        [
            'cmid'       => $cmid,
            'attemptid'  => $attemptid,
            'answerid'   => $answerid,
            'answertext' => $answertext,
        ] = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'attemptid', 'answerid', 'answertext')
        );

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'quizquest');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quizquest:play', $context);

        $quizquest = $DB->get_record('quizquest', ['id' => $cm->instance], '*', MUST_EXIST);
        $attempt   = $DB->get_record(
            'quizquest_attempts',
            ['id' => $attemptid, 'userid' => $USER->id, 'quizquest' => $quizquest->id],
            '*',
            MUST_EXIST
        );

        if ($attempt->status !== 'inprogress') {
            throw new \moodle_exception('error:invalidattempt', 'mod_quizquest');
        }

        $manager = new attempt_manager();

        // A closed activity accepts no more answers; finalise the attempt instead.
        if (empty($attempt->ispreview) && attempt_manager::is_closed($quizquest)) {
            $manager->abandon_expired_attempt($attempt, $quizquest);
            throw new \moodle_exception('error:closedon', 'mod_quizquest', '', userdate($quizquest->timeclose));
        }

        $pending = $manager->get_pending_response($attempt->id);
        if (!$pending) {
            throw new \moodle_exception('error:invalidattempt', 'mod_quizquest');
        }

        // Evaluate the answer against the pending question. The submitted answer id
        // is validated against the question's own answers server-side, so a forged
        // id is rejected; typed answers use the question's own matching rules.
        $qtype = $DB->get_field('question', 'qtype', ['id' => $pending->questionid], MUST_EXIST);
        if ($qtype === 'multichoice') {
            if ($answerid <= 0) {
                throw new \moodle_exception('error:invalidchoice', 'mod_quizquest');
            }
            $result = question_picker::evaluate_multichoice((int) $pending->questionid, $answerid);
        } else {
            $answertext = trim($answertext);
            if ($answertext === '') {
                throw new \moodle_exception('error:invalidchoice', 'mod_quizquest');
            }
            $result = question_picker::evaluate_shortanswer((int) $pending->questionid, $answertext);
        }

        $stepchange = $result['iscorrect'] ? 1 : (empty($quizquest->wrongpenalty) ? 0 : -1);

        // Combine the answer's own feedback with a shuffled generic-response pool
        // entry per the activity's display mode. This may consume the attempt's
        // pool queue, which update_tally below persists.
        $feedback = message_bank::assemble_feedback($quizquest, $attempt, $result['feedback'], $result['iscorrect']);

        $manager->update_tally($attempt, $stepchange);

        // Step messages only fire when a correct answer brings the tally up to the configured step.
        $textbefore = '';
        $textafter  = '';
        if ($stepchange > 0) {
            $stepmessage = message_bank::get_step_message((int) $quizquest->id, (int) $attempt->stepstally);
            if ($stepmessage) {
                $textbefore = (string) $stepmessage->textbefore;
                $textafter  = (string) $stepmessage->textafter;
            }
        }

        $manager->record_answer(
            $pending,
            $result['responselabel'],
            $result['iscorrect'],
            $stepchange,
            $feedback,
            $textbefore,
            $textafter
        );

        // Complete when the tally reaches the required number of steps.
        $completed = false;
        if ((int) $attempt->stepstally >= (int) $quizquest->steps) {
            $course = get_course($cm->course);
            $cminfo = get_fast_modinfo($course)->get_cm($cm->id);
            $manager->complete_attempt($attempt, $quizquest, $course, $cminfo);
            $completed = true;
        }

        $response = [
            'feedback'   => $feedback,
            'textbefore' => $textbefore,
            'textafter'  => $textafter,
            'iscorrect'  => $result['iscorrect'],
            'stepchange' => $stepchange,
            'tally'      => (int) $attempt->stepstally,
            'steps'      => (int) $quizquest->steps,
            'completed'  => $completed,
            'canrestart' => $manager->can_start_new_attempt($quizquest, $USER->id, (bool) $attempt->ispreview),
        ];

        if (!$completed) {
            $response['question'] = $manager->serve_question($quizquest, $attempt);
        }

        return $response;
    }

    /**
     * Defines the return value for this web service function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feedback'   => new external_value(PARAM_RAW, 'Feedback on the submitted answer'),
            'textbefore' => new external_value(PARAM_RAW, 'Narrative text to show before the feedback (empty if none)'),
            'textafter'  => new external_value(PARAM_RAW, 'Narrative text to show after the feedback (empty if none)'),
            'iscorrect'  => new external_value(PARAM_BOOL, 'Whether the answer was correct'),
            'stepchange' => new external_value(PARAM_INT, 'Step delta applied this turn'),
            'tally'      => new external_value(PARAM_INT, 'Updated step tally'),
            'steps'      => new external_value(PARAM_INT, 'Steps needed to complete'),
            'completed'  => new external_value(PARAM_BOOL, 'Whether the attempt is now completed'),
            'canrestart' => new external_value(PARAM_BOOL, 'Whether the user can start another attempt'),
            'question'   => new external_single_structure([
                'text'    => new external_value(PARAM_RAW, 'Question text'),
                'qtype'   => new external_value(PARAM_ALPHA, 'multichoice or shortanswer'),
                'choices' => new external_multiple_structure(
                    new external_single_structure([
                        'id'    => new external_value(PARAM_INT, 'Answer id'),
                        'label' => new external_value(PARAM_TEXT, 'Answer label'),
                    ]),
                    'Answer choices (empty for shortanswer)'
                ),
            ], 'The next question to answer (absent when the attempt is completed)', VALUE_OPTIONAL),
        ]);
    }
}
