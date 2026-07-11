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
            'partialscoreonquit', 'wrongpenalty', 'timeopen', 'timeclose',
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

        $quizquest->add_child($stepmessages);
        $stepmessages->add_child($stepmessage);
        $quizquest->add_child($genericresponses);
        $genericresponses->add_child($genericresponse);
        $quizquest->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);

        $quizquest->set_source_table('quizquest', ['id' => backup::VAR_ACTIVITYID]);
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
