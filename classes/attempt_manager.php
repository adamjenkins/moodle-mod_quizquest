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
 * Central business logic for managing attempts.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /**
     * Whether the activity is currently within its open/close window.
     *
     * @param stdClass $quizquest The activity record
     * @param int|null $now Timestamp to check against (defaults to now)
     * @return bool
     */
    public static function is_open(\stdClass $quizquest, ?int $now = null): bool {
        $now = $now ?? time();
        if (!empty($quizquest->timeopen) && $now < (int) $quizquest->timeopen) {
            return false;
        }
        return !self::is_closed($quizquest, $now);
    }

    /**
     * Whether the activity's close date has passed.
     *
     * @param stdClass $quizquest The activity record
     * @param int|null $now Timestamp to check against (defaults to now)
     * @return bool
     */
    public static function is_closed(\stdClass $quizquest, ?int $now = null): bool {
        return !empty($quizquest->timeclose) && ($now ?? time()) > (int) $quizquest->timeclose;
    }

    /**
     * Abandons an in-progress attempt whose activity close date has passed.
     *
     * @param stdClass $attempt   The attempt record
     * @param stdClass $quizquest The activity record
     * @return bool Whether the attempt was abandoned
     */
    public function abandon_expired_attempt(\stdClass $attempt, \stdClass $quizquest): bool {
        if ($attempt->status !== 'inprogress' || !self::is_closed($quizquest)) {
            return false;
        }
        $course = get_course($quizquest->course);
        $cm = get_fast_modinfo($course)->get_instances_of('quizquest')[$quizquest->id];
        $this->abandon_attempt($attempt, $quizquest, $course, $cm);
        return true;
    }

    /**
     * Returns the current in-progress attempt for a user, or null if none exists.
     *
     * @param int $quizquest Activity instance id
     * @param int $userid
     * @param bool $ispreview Whether to look up a preview attempt rather than a real one
     * @return stdClass|null
     */
    public function get_active_attempt(int $quizquest, int $userid, bool $ispreview = false): ?\stdClass {
        global $DB;
        return $DB->get_record(
            'quizquest_attempts',
            ['quizquest' => $quizquest, 'userid' => $userid, 'status' => 'inprogress', 'ispreview' => $ispreview ? 1 : 0]
        ) ?: null;
    }

    /**
     * Checks whether the user is allowed to start a new attempt.
     *
     * @param stdClass $quizquest The activity record
     * @param int      $userid
     * @param bool     $ispreview Preview attempts are never subject to the attempt limit
     * @return bool
     */
    public function can_start_new_attempt(\stdClass $quizquest, int $userid, bool $ispreview = false): bool {
        global $DB;

        if ($ispreview) {
            return true;
        }

        if ((int) $quizquest->maxattempts === -1) {
            return true;
        }

        $count = $DB->count_records(
            'quizquest_attempts',
            ['quizquest' => $quizquest->id, 'userid' => $userid, 'ispreview' => 0]
        );
        return $count < (int) $quizquest->maxattempts;
    }

    /**
     * Returns an active attempt for the user, or creates a new one.
     *
     * Throws moodle_exception if the user has exhausted their attempts.
     *
     * @param stdClass $quizquest The activity record
     * @param int      $userid
     * @param bool     $ispreview Whether this is a teacher/manager preview attempt
     * @return stdClass The attempt record
     */
    public function get_or_create_attempt(\stdClass $quizquest, int $userid, bool $ispreview = false): \stdClass {
        global $DB;

        $attempt = $this->get_active_attempt($quizquest->id, $userid, $ispreview);
        if ($attempt) {
            return $attempt;
        }

        if (!$this->can_start_new_attempt($quizquest, $userid, $ispreview)) {
            throw new \moodle_exception('error:maxattemptsreached', 'mod_quizquest');
        }

        $now = time();
        $record               = new \stdClass();
        $record->quizquest    = $quizquest->id;
        $record->userid       = $userid;
        $record->status       = 'inprogress';
        $record->stepstally   = 0;
        $record->ispreview    = $ispreview ? 1 : 0;
        $record->timecreated  = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record('quizquest_attempts', $record);

        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);
        $context = \context_module::instance($cm->id);
        $event = \mod_quizquest\event\attempt_started::create([
            'objectid' => $record->id,
            'context'  => $context,
            'userid'   => $userid,
        ]);
        $event->trigger();

        return $record;
    }

    /**
     * Returns all attempts by a user for a given activity, newest first.
     *
     * @param int $quizquest
     * @param int $userid
     * @param bool $includepreview Whether to include teacher/manager preview attempts
     * @return stdClass[]
     */
    public function get_user_attempts(int $quizquest, int $userid, bool $includepreview = false): array {
        global $DB;
        $conditions = ['quizquest' => $quizquest, 'userid' => $userid];
        if (!$includepreview) {
            $conditions['ispreview'] = 0;
        }
        return array_values(
            $DB->get_records('quizquest_attempts', $conditions, 'timecreated DESC')
        );
    }

    /**
     * Returns all question turns for an attempt, oldest first.
     *
     * @param int $attemptid
     * @return stdClass[]
     */
    public function get_attempt_responses(int $attemptid): array {
        global $DB;
        return array_values(
            $DB->get_records('quizquest_responses', ['attemptid' => $attemptid], 'timecreated ASC, id ASC')
        );
    }

    /**
     * Returns the currently served but unanswered question turn, if any.
     *
     * @param int $attemptid
     * @return stdClass|null
     */
    public function get_pending_response(int $attemptid): ?\stdClass {
        global $DB;
        $records = $DB->get_records_select(
            'quizquest_responses',
            'attemptid = ? AND iscorrect IS NULL',
            [$attemptid],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Picks the next question, records it as a pending turn, and returns its payload.
     *
     * @param stdClass $quizquest The activity record
     * @param stdClass $attempt   The attempt record
     * @return array Question payload (see question_picker::get_question_payload)
     */
    public function serve_question(\stdClass $quizquest, \stdClass $attempt): array {
        global $DB;

        $questionid = question_picker::pick_question($quizquest, (int) $attempt->userid, (int) $attempt->id);
        if ($questionid === null) {
            throw new \moodle_exception('error:noquestions', 'mod_quizquest');
        }

        $record              = new \stdClass();
        $record->attemptid   = $attempt->id;
        $record->questionid  = $questionid;
        $record->timecreated = time();
        $DB->insert_record('quizquest_responses', $record);

        return question_picker::get_question_payload($questionid);
    }

    /**
     * Records the student's answer against a pending question turn.
     *
     * @param stdClass $pending       The pending quizquest_responses record
     * @param string   $response      The student's answer (choice label or typed text)
     * @param bool     $iscorrect
     * @param int      $stepchange
     * @param string   $feedbacktext  The exact feedback text shown to the student
     * @param string   $stepmsgbefore Narrative text shown before the feedback, if any
     * @param string   $stepmsgafter  Narrative text shown after the feedback, if any
     */
    public function record_answer(
        \stdClass $pending,
        string $response,
        bool $iscorrect,
        int $stepchange,
        string $feedbacktext = '',
        string $stepmsgbefore = '',
        string $stepmsgafter = ''
    ): void {
        global $DB;

        $pending->response      = $response;
        $pending->iscorrect     = $iscorrect ? 1 : 0;
        $pending->stepchange    = $stepchange;
        $pending->feedbacktext  = $feedbacktext;
        $pending->stepmsgbefore = $stepmsgbefore;
        $pending->stepmsgafter  = $stepmsgafter;
        $DB->update_record('quizquest_responses', $pending);
    }

    /**
     * Rebuilds the chat history for the answered turns of an attempt.
     *
     * Each answered turn becomes three chat messages: the question (assistant),
     * the student's answer (user), and a correct/incorrect verdict (assistant).
     *
     * @param int $attemptid
     * @return array<array{role: string, message: string}>
     */
    public function build_history(int $attemptid): array {
        global $DB;

        $responses = $this->get_attempt_responses($attemptid);
        $answered = array_filter($responses, fn($r) => $r->iscorrect !== null);
        if (!$answered) {
            return [];
        }

        $questionids = array_unique(array_map(fn($r) => $r->questionid, $answered));
        $questions = $DB->get_records_list(
            'question',
            'id',
            $questionids,
            '',
            'id, questiontext, questiontextformat'
        );

        $messages = [];
        foreach ($answered as $response) {
            $question = $questions[$response->questionid] ?? null;
            $questiontext = $question
                ? trim(html_to_text($question->questiontext, 0, false))
                : get_string('questionunavailable', 'mod_quizquest');

            $messages[] = ['role' => 'assistant', 'message' => $questiontext];
            $messages[] = ['role' => 'user', 'message' => (string) $response->response];

            if (!empty($response->stepmsgbefore)) {
                $messages[] = ['role' => 'assistant', 'message' => $response->stepmsgbefore];
            }

            // Older rows recorded before this feature was added have no stored feedback text.
            $feedbacktext = $response->feedbacktext ?? '';
            if ($feedbacktext === '') {
                $feedbacktext = get_string($response->iscorrect ? 'feedbackcorrect' : 'feedbackincorrect', 'mod_quizquest');
            }
            $messages[] = ['role' => 'assistant', 'message' => $feedbacktext];

            if (!empty($response->stepmsgafter)) {
                $messages[] = ['role' => 'assistant', 'message' => $response->stepmsgafter];
            }
        }

        return $messages;
    }

    /**
     * Applies a step delta to the attempt tally (floor 0).
     *
     * @param stdClass $attempt  The attempt record (updated in place)
     * @param int      $stepchange
     * @return void
     */
    public function update_tally(\stdClass $attempt, int $stepchange): void {
        global $DB;

        $newtally = max(0, (int) $attempt->stepstally + $stepchange);
        $attempt->stepstally   = $newtally;
        $attempt->timemodified = time();
        $DB->update_record('quizquest_attempts', $attempt);
    }

    /**
     * Marks an attempt as abandoned and optionally awards a partial grade.
     *
     * @param stdClass $attempt   The attempt record (must be in 'inprogress' status)
     * @param stdClass $quizquest The activity record
     * @param stdClass $course    The course record
     * @param cm_info  $cm        The course-module record
     * @return float The grade awarded (0.0 if partial scoring is disabled)
     */
    public function abandon_attempt(
        \stdClass $attempt,
        \stdClass $quizquest,
        \stdClass $course,
        \cm_info $cm
    ): float {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quizquest/lib.php');

        $now = time();
        $attempt->status        = 'abandoned';
        $attempt->timemodified  = $now;
        $attempt->timecompleted = $now;
        $DB->update_record('quizquest_attempts', $attempt);

        $grade = 0.0;
        if (
            empty($attempt->ispreview) &&
            !empty($quizquest->partialscoreonquit) &&
            (int) $quizquest->steps > 0
        ) {
            $grade = (float) $quizquest->grade
                * min(1.0, (int) $attempt->stepstally / (int) $quizquest->steps);
            quizquest_update_grades($quizquest, $attempt->userid);
        }

        $context = \context_module::instance($cm->id);
        $event = \mod_quizquest\event\attempt_abandoned::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'userid'   => $attempt->userid,
        ]);
        $event->trigger();

        return $grade;
    }

    /**
     * Marks an attempt as completed, awards the grade, and triggers Moodle completion.
     *
     * @param stdClass $attempt   The attempt record
     * @param stdClass $quizquest The activity record
     * @param stdClass $course    The course record
     * @param cm_info  $cm        The course-module record
     * @return void
     */
    public function complete_attempt(
        \stdClass $attempt,
        \stdClass $quizquest,
        \stdClass $course,
        \cm_info $cm
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quizquest/lib.php');

        $now = time();
        $attempt->status        = 'completed';
        $attempt->timemodified  = $now;
        $attempt->timecompleted = $now;
        $DB->update_record('quizquest_attempts', $attempt);

        if (empty($attempt->ispreview)) {
            // Update the gradebook.
            quizquest_update_grades($quizquest, $attempt->userid);

            // Update Moodle activity completion.
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $attempt->userid);
            }
        }

        $context = \context_module::instance($cm->id);
        $event = \mod_quizquest\event\attempt_completed::create([
            'objectid' => $attempt->id,
            'context'  => $context,
            'userid'   => $attempt->userid,
        ]);
        $event->trigger();
    }
}
