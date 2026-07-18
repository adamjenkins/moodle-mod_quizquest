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

/**
 * Backup structure step for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete quizquest structure for backup, with optional user data.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizquest_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $quizquest = new backup_nested_element('quizquest', ['id'], [
            'name', 'intro', 'introformat', 'questioncategoryid', 'includesubcategories',
            'steps', 'grade', 'maxattempts', 'showprogress', 'allowstudentreview',
            'partialscoreonquit', 'wrongpenalty', 'genericresponsedisplay', 'timeopen', 'timeclose',
            'timecreated', 'timemodified',
        ]);

        $stepmessages = new backup_nested_element('stepmessages');
        $stepmessage = new backup_nested_element('stepmessage', ['id'], [
            'step', 'textbefore', 'textafter',
        ]);

        $genericresponses = new backup_nested_element('genericresponses');
        $genericresponse = new backup_nested_element('genericresponse', ['id'], [
            'responsetype', 'responsetext', 'sortorder',
        ]);

        // The correctpoolqueue/incorrectpoolqueue fields are intentionally not backed
        // up: they hold quizquest_genericresponses row ids from the source site, and
        // message_bank reseeds an empty queue automatically.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'status', 'stepstally', 'timecreated', 'timemodified',
            'timecompleted', 'ispreview',
        ]);

        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'questionid', 'response', 'iscorrect', 'stepchange', 'timecreated',
            'feedbacktext', 'stepmsgbefore', 'stepmsgafter',
        ]);

        // The activity's question bank category is referenced only by a raw
        // id on the quizquest row, not through the standard
        // question_references/question_set_references tables mod_quiz uses,
        // so core's backup never discovers it on its own. Without this,
        // annotate_ids('question', ...) on $response only ever ran when
        // userinfo was included (student answer history) - the OER Exchange
        // platform always backs up with userinfo=false, so every question in
        // the configured category silently went missing from the archive on
        // a single-activity share (found live, 2026-07-19: questions.xml was
        // empty for a quizquest-only backup even though the same category
        // backed up fine as part of a whole-course share). Core's backup
        // categorizes/includes question bank content by annotated
        // 'question_bank_entry' ids (backup_question_dbops::
        // calculate_question_categories()), not by 'question' ids - that
        // itemname is only used for restore-side remapping elsewhere in this
        // plugin (see $response below). Annotate the category's current
        // eligible bank entries unconditionally so they're included
        // regardless of the userinfo setting.
        $questionsused = new backup_nested_element('questionsused');
        $questionused = new backup_nested_element('questionused', null, ['questionbankentryid']);
        $questionsused->add_child($questionused);

        $quizquest->add_child($stepmessages);
        $stepmessages->add_child($stepmessage);
        $quizquest->add_child($genericresponses);
        $genericresponses->add_child($genericresponse);
        $quizquest->add_child($questionsused);
        $quizquest->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);

        $quizquest->set_source_table('quizquest', ['id' => backup::VAR_ACTIVITYID]);

        global $DB;
        $record = $DB->get_record(
            'quizquest',
            ['id' => $this->task->get_activityid()],
            'questioncategoryid, includesubcategories'
        );
        $eligiblebankentries = $record
            ? \mod_quizquest\question_picker::get_eligible_bank_entry_ids(
                \mod_quizquest\question_picker::parse_category($record->questioncategoryid),
                (bool) $record->includesubcategories
            )
            : [];
        $questionused->set_source_array(array_map(
            static fn (int $qbeid) => (object) ['questionbankentryid' => $qbeid],
            $eligiblebankentries
        ));
        $questionused->annotate_ids('question_bank_entry', 'questionbankentryid');
        $stepmessage->set_source_table('quizquest_stepmessages', ['quizquest' => backup::VAR_PARENTID], 'step ASC');
        $genericresponse->set_source_table(
            'quizquest_genericresponses',
            ['quizquest' => backup::VAR_PARENTID],
            'sortorder ASC, id ASC'
        );

        if ($userinfo) {
            // Teacher/manager preview attempts are working data, not user records.
            $attempt->set_source_sql(
                'SELECT * FROM {quizquest_attempts} WHERE quizquest = :quizquest AND ispreview = 0',
                ['quizquest' => backup::VAR_PARENTID]
            );
            $response->set_source_table(
                'quizquest_responses',
                ['attemptid' => backup::VAR_PARENTID],
                'timecreated ASC, id ASC'
            );
        }

        $attempt->annotate_ids('user', 'userid');
        $response->annotate_ids('question', 'questionid');

        $quizquest->annotate_files('mod_quizquest', 'intro', null);
        $quizquest->annotate_files('mod_quizquest', 'progressimage', null);

        return $this->prepare_activity_structure($quizquest);
    }
}
