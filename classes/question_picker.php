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
 * Selects and evaluates question bank questions for a quest.
 *
 * Questions are drawn from the configured question bank category. Only the
 * latest ready version of each question is used. Random selection prefers
 * questions the student has never seen in any of their attempts, then
 * questions not yet asked in the current attempt, then anything eligible.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_picker {
    /**
     * Splits the stored "categoryid,contextid" value into the category id.
     *
     * @param string|null $questioncategoryid The raw value stored on the activity record
     * @return int The question category id (0 if unset)
     */
    public static function parse_category(?string $questioncategoryid): int {
        $parts = explode(',', (string) $questioncategoryid);
        return (int) ($parts[0] ?? 0);
    }

    /**
     * Returns the ids of all questions in a category (and optionally its subcategories)
     * this activity can ask.
     *
     * Eligible questions are the latest ready version of each bank entry with
     * qtype multichoice (single-answer only) or shortanswer.
     *
     * @param int  $categoryid           Question category id
     * @param bool $includesubcategories Whether to also include questions from subcategories
     * @return int[] Question ids
     */
    public static function get_eligible_question_ids(int $categoryid, bool $includesubcategories = false): array {
        global $CFG, $DB;

        if (!$categoryid) {
            return [];
        }

        $categoryids = [$categoryid];
        if ($includesubcategories) {
            require_once($CFG->libdir . '/questionlib.php');
            $categoryids = question_categorylist($categoryid);
        }

        [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);

        $sql = "SELECT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
             LEFT JOIN {qtype_multichoice_options} mco
                       ON q.qtype = 'multichoice' AND mco.questionid = q.id
                 WHERE qbe.questioncategoryid $insql
                   AND q.qtype IN ('multichoice', 'shortanswer')
                   AND (q.qtype <> 'multichoice' OR mco.single = 1)
                   AND qv.status = :readystatus
                   AND qv.version = (
                        SELECT MAX(v.version)
                          FROM {question_versions} v
                         WHERE v.questionbankentryid = qbe.id
                           AND v.status = :readystatus2
                   )";

        $params = array_merge($inparams, [
            'readystatus'  => 'ready',
            'readystatus2' => 'ready',
        ]);

        return array_keys($DB->get_records_sql($sql, $params));
    }

    /**
     * Returns the question_bank_entries ids backing get_eligible_question_ids()'s
     * result set — what backup needs to annotate (core's backup categorizes
     * and includes question bank content by 'question_bank_entry' id, not by
     * question id; see backup_question_dbops::calculate_question_categories()).
     *
     * @param int  $categoryid           Question category id
     * @param bool $includesubcategories Whether to also include questions from subcategories
     * @return int[] question_bank_entries ids
     */
    public static function get_eligible_bank_entry_ids(int $categoryid, bool $includesubcategories = false): array {
        global $CFG, $DB;

        if (!$categoryid) {
            return [];
        }

        $categoryids = [$categoryid];
        if ($includesubcategories) {
            require_once($CFG->libdir . '/questionlib.php');
            $categoryids = question_categorylist($categoryid);
        }

        [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);

        $sql = "SELECT qbe.id
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
             LEFT JOIN {qtype_multichoice_options} mco
                       ON q.qtype = 'multichoice' AND mco.questionid = q.id
                 WHERE qbe.questioncategoryid $insql
                   AND q.qtype IN ('multichoice', 'shortanswer')
                   AND (q.qtype <> 'multichoice' OR mco.single = 1)
                   AND qv.status = :readystatus
                   AND qv.version = (
                        SELECT MAX(v.version)
                          FROM {question_versions} v
                         WHERE v.questionbankentryid = qbe.id
                           AND v.status = :readystatus2
                   )";

        $params = array_merge($inparams, [
            'readystatus'  => 'ready',
            'readystatus2' => 'ready',
        ]);

        return array_keys($DB->get_records_sql($sql, $params));
    }

    /**
     * Picks the next question to ask, preferring unseen questions.
     *
     * Preference order:
     * 1. Questions the user has never been asked in any attempt at this activity.
     * 2. Questions not yet asked in the current attempt.
     * 3. Any eligible question.
     *
     * @param stdClass $quizquest The activity record
     * @param int      $userid
     * @param int      $attemptid The current attempt id
     * @return int|null A question id, or null if the category has no eligible questions
     */
    public static function pick_question(\stdClass $quizquest, int $userid, int $attemptid): ?int {
        global $DB;

        $eligible = self::get_eligible_question_ids(
            self::parse_category($quizquest->questioncategoryid),
            (bool) ($quizquest->includesubcategories ?? false)
        );
        if (!$eligible) {
            return null;
        }

        $seenever = $DB->get_fieldset_sql(
            "SELECT DISTINCT r.questionid
               FROM {quizquest_responses} r
               JOIN {quizquest_attempts} a ON a.id = r.attemptid
              WHERE a.quizquest = :quizquest AND a.userid = :userid",
            ['quizquest' => $quizquest->id, 'userid' => $userid]
        );
        $seenthisattempt = $DB->get_fieldset_select(
            'quizquest_responses',
            'questionid',
            'attemptid = ?',
            [$attemptid]
        );

        $pool = array_values(array_diff($eligible, $seenever));
        if (!$pool) {
            $pool = array_values(array_diff($eligible, $seenthisattempt));
        }
        if (!$pool) {
            $pool = array_values($eligible);
        }

        return $pool[random_int(0, count($pool) - 1)];
    }

    /**
     * Builds the client payload for asking a question.
     *
     * @param int $questionid
     * @return array {text: string, qtype: string, choices: array<{id: int, label: string}>}
     */
    public static function get_question_payload(int $questionid): array {
        $question = self::load_question($questionid);

        $payload = [
            'text'    => self::to_plain_text($question->questiontext, (int) $question->questiontextformat),
            'qtype'   => $question->get_type_name(),
            'choices' => [],
        ];

        if ($question->get_type_name() === 'multichoice') {
            foreach ($question->answers as $answer) {
                $payload['choices'][] = [
                    'id'    => (int) $answer->id,
                    'label' => self::to_plain_text($answer->answer, (int) $answer->answerformat),
                ];
            }
        }

        return $payload;
    }

    /**
     * Evaluates a multichoice answer selection.
     *
     * The submitted answer id is validated against the question's own answers,
     * so a forged id that was never offered is rejected server-side.
     *
     * @param int $questionid
     * @param int $answerid The chosen question_answers id
     * @return array {iscorrect: bool, responselabel: string, feedback: string}
     */
    public static function evaluate_multichoice(int $questionid, int $answerid): array {
        $question = self::load_question($questionid);

        $answer = $question->answers[$answerid] ?? null;
        if (!$answer) {
            throw new \moodle_exception('error:invalidchoice', 'mod_quizquest');
        }

        return [
            'iscorrect'     => $answer->fraction >= 0.999,
            'responselabel' => self::to_plain_text($answer->answer, (int) $answer->answerformat),
            'feedback'      => self::to_plain_text($answer->feedback ?? '', (int) ($answer->feedbackformat ?? FORMAT_HTML)),
        ];
    }

    /**
     * Evaluates a typed short answer using the question's own matching rules
     * (case sensitivity and '*' wildcards included).
     *
     * @param int    $questionid
     * @param string $text The student's typed answer
     * @return array {iscorrect: bool, responselabel: string, feedback: string}
     */
    public static function evaluate_shortanswer(int $questionid, string $text): array {
        $question = self::load_question($questionid);

        $answer = $question->get_matching_answer(['answer' => $text]);

        return [
            'iscorrect'     => $answer && $answer->fraction >= 0.999,
            'responselabel' => $text,
            'feedback'      => $answer
                ? self::to_plain_text($answer->feedback ?? '', (int) ($answer->feedbackformat ?? FORMAT_HTML))
                : '',
        ];
    }

    /**
     * Loads a question definition through the core question bank.
     *
     * @param int $questionid
     * @return \question_definition
     */
    protected static function load_question(int $questionid): \question_definition {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        return \question_bank::load_question($questionid);
    }

    /**
     * Converts stored question/answer HTML into plain text for the chat log.
     *
     * @param string $content The stored content
     * @param int    $format  The content's FORMAT_xx constant
     * @return string
     */
    protected static function to_plain_text(string $content, int $format): string {
        if ($format == FORMAT_PLAIN) {
            return trim($content);
        }
        if ($format == FORMAT_MARKDOWN) {
            $content = markdown_to_html($content);
        }
        return trim(html_to_text($content, 0, false));
    }
}
