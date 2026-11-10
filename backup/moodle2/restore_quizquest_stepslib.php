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
 * Restore structure step for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the quizquest structure produced by backup_quizquest_activity_structure_step.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizquest_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $paths = [];
        $paths[] = new restore_path_element('quizquest', '/activity/quizquest');
        $paths[] = new restore_path_element('quizquest_stepmessage', '/activity/quizquest/stepmessages/stepmessage');
        $paths[] = new restore_path_element(
            'quizquest_genericresponse',
            '/activity/quizquest/genericresponses/genericresponse'
        );
        if ($userinfo) {
            $paths[] = new restore_path_element('quizquest_attempt', '/activity/quizquest/attempts/attempt');
            $paths[] = new restore_path_element(
                'quizquest_response',
                '/activity/quizquest/attempts/attempt/responses/response'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity record.
     *
     * @param array $data the activity element data
     */
    protected function process_quizquest($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timeopen = $this->apply_date_offset($data->timeopen ?? 0);
        $data->timeclose = $this->apply_date_offset($data->timeclose ?? 0);
        // A .mbz is attacker-controlled input: re-apply the same PARAM_TEXT
        // cleaning the settings form applies to this field (mod_form.php's
        // 'name' element) so a crafted backup can't smuggle in markup that a
        // future, less-careful output path might fail to escape.
        $data->name = clean_param((string) ($data->name ?? ''), PARAM_TEXT);
        // Re-apply the settings form's limits too (mod_form.php validation() and
        // its select/yes-no elements): steps 1..100, grade >= 0 (no scales),
        // maxattempts -1 or 1..10, a known generic-response display mode, and
        // 0/1 flags.
        $data->steps = min(100, max(1, (int) ($data->steps ?? 10)));
        $data->grade = max(0, (int) ($data->grade ?? 100));
        $maxattempts = (int) ($data->maxattempts ?? -1);
        $data->maxattempts = ($maxattempts === -1 || ($maxattempts >= 1 && $maxattempts <= 10)) ? $maxattempts : -1;
        $display = (int) ($data->genericresponsedisplay ?? \mod_quizquest\message_bank::DISPLAY_WHEN_NO_FEEDBACK);
        $displaymodes = [
            \mod_quizquest\message_bank::DISPLAY_WHEN_NO_FEEDBACK,
            \mod_quizquest\message_bank::DISPLAY_NEVER,
            \mod_quizquest\message_bank::DISPLAY_BEFORE,
            \mod_quizquest\message_bank::DISPLAY_AFTER,
        ];
        $data->genericresponsedisplay = in_array($display, $displaymodes, true)
            ? $display : \mod_quizquest\message_bank::DISPLAY_WHEN_NO_FEEDBACK;
        foreach (['includesubcategories', 'showprogress', 'allowstudentreview', 'partialscoreonquit', 'wrongpenalty'] as $flag) {
            $data->$flag = empty($data->$flag) ? 0 : 1;
        }
        // The questioncategoryid reference is restored raw here; the task's
        // after_restore() remaps it once every activity (incl. qbanks) is in.

        $newitemid = $DB->insert_record('quizquest', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores a step message row.
     *
     * @param array $data the stepmessage element data
     */
    protected function process_quizquest_stepmessage($data) {
        global $DB;

        $data = (object) $data;
        $data->quizquest = $this->get_new_parentid('quizquest');
        // A .mbz is attacker-controlled input: re-apply the same PARAM_TEXT
        // cleaning the settings form applies to these fields (mod_form.php's
        // stepmsg_before/stepmsg_after repeated elements).
        $data->textbefore = clean_param((string) ($data->textbefore ?? ''), PARAM_TEXT);
        $data->textafter = clean_param((string) ($data->textafter ?? ''), PARAM_TEXT);

        // Re-apply the form's step rules (mod_form.php validation(): digits only,
        // no greater than the activity's steps, unique per activity). A crafted
        // row that breaks them is skipped: a duplicate would otherwise hit the
        // UNIQUE (quizquest, step) index and abort the whole restore.
        $rawstep = trim((string) ($data->step ?? ''));
        if ($rawstep === '' || !ctype_digit($rawstep)) {
            return;
        }
        $data->step = (int) $rawstep;
        $steps = (int) $DB->get_field('quizquest', 'steps', ['id' => $data->quizquest]);
        if ($data->step > $steps) {
            return;
        }
        if ($DB->record_exists('quizquest_stepmessages', ['quizquest' => $data->quizquest, 'step' => $data->step])) {
            return;
        }

        $DB->insert_record('quizquest_stepmessages', $data);
    }

    /**
     * Restores a generic response pool row.
     *
     * @param array $data the genericresponse element data
     */
    protected function process_quizquest_genericresponse($data) {
        global $DB;

        $data = (object) $data;
        $data->quizquest = $this->get_new_parentid('quizquest');
        // A .mbz is attacker-controlled input: re-apply the same PARAM_TEXT
        // cleaning the settings form applies to this field (mod_form.php's
        // correctresponse_text/incorrectresponse_text repeated elements).
        $data->responsetext = clean_param((string) ($data->responsetext ?? ''), PARAM_TEXT);
        $DB->insert_record('quizquest_genericresponses', $data);
    }

    /**
     * Restores an attempt row (user data only).
     *
     * @param array $data the attempt element data
     */
    protected function process_quizquest_attempt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->quizquest = $this->get_new_parentid('quizquest');
        $data->userid = $this->get_mappingid('user', $data->userid);
        // Shuffle queues reference source-site generic-response row ids; start fresh.
        $data->correctpoolqueue = '';
        $data->incorrectpoolqueue = '';
        // A .mbz is attacker-controlled input: only the statuses the plugin
        // itself writes are accepted (an unknown one is restored as abandoned),
        // the tally stays within 0..steps, and ispreview is a 0/1 flag.
        if (!in_array((string) ($data->status ?? ''), ['inprogress', 'completed', 'abandoned'], true)) {
            $data->status = 'abandoned';
        }
        $steps = (int) $DB->get_field('quizquest', 'steps', ['id' => $data->quizquest]);
        $data->stepstally = min($steps, max(0, (int) ($data->stepstally ?? 0)));
        $data->ispreview = empty($data->ispreview) ? 0 : 1;

        $newitemid = $DB->insert_record('quizquest_attempts', $data);
        $this->set_mapping('quizquest_attempt', $oldid, $newitemid);
    }

    /**
     * Restores a response row (user data only).
     *
     * @param array $data the response element data
     */
    protected function process_quizquest_response($data) {
        global $DB;

        $data = (object) $data;
        $data->attemptid = $this->get_new_parentid('quizquest_attempt');
        // The questionid is restored raw here; the task's after_restore() remaps
        // it (or zeroes it) once any bundled question banks have been restored.
        $DB->insert_record('quizquest_responses', $data);
    }

    /**
     * Restores the file areas after the records.
     */
    protected function after_execute() {
        $this->add_related_files('mod_quizquest', 'intro', null);
        $this->add_related_files('mod_quizquest', 'progressimage', null);
    }
}
