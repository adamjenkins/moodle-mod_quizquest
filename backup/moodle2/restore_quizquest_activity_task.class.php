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
 * Restore task for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizquest/backup/moodle2/restore_quizquest_stepslib.php');

/**
 * Provides the steps to perform a complete restore of a quizquest instance.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizquest_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_quizquest_activity_structure_step('quizquest_structure', 'quizquest.xml'));
    }

    /**
     * Defines the contents in the activity that must be processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('quizquest', ['intro'], 'quizquest'),
        ];
    }

    /**
     * Defines the decoding rules for links belonging to the activity.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('QUIZQUESTVIEWBYID', '/mod/quizquest/view.php?id=$1', 'course_module'),
            new restore_decode_rule('QUIZQUESTINDEX', '/mod/quizquest/index.php?id=$1', 'course'),
        ];
    }

    /**
     * No restore log rules (the plugin predates legacy add_to_log).
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Remaps the question bank references once the whole plan has restored.
     *
     * This runs in after_restore() rather than in the structure step because
     * question banks are themselves activities in the restore plan, and their
     * question_category/question mappings only reliably exist after every
     * activity has been processed.
     */
    public function after_restore() {
        global $DB;

        $quizquestid = $this->get_activityid();
        $quizquest = $DB->get_record('quizquest', ['id' => $quizquestid]);
        if (!$quizquest) {
            return;
        }

        $DB->set_field(
            'quizquest',
            'questioncategoryid',
            $this->remap_category_reference((string) $quizquest->questioncategoryid),
            ['id' => $quizquestid]
        );

        $this->remap_response_questionids($quizquestid);
    }

    /**
     * Remaps the stored "categoryid,contextid" question category reference.
     *
     * Mapped (the bank was part of the backup) -> new ids. Unmapped on the same
     * site with the category still present -> kept as-is (e.g. duplicating an
     * activity that draws from a bank elsewhere in the course). Otherwise ->
     * cleared, so the settings form forces the teacher to pick a new category.
     *
     * @param string $reference the restored raw reference
     * @return string the remapped reference, or '' when it cannot be resolved
     */
    protected function remap_category_reference(string $reference): string {
        global $DB;

        $oldcategoryid = (int) (explode(',', $reference)[0] ?? 0);
        if (!$oldcategoryid) {
            return '';
        }

        $mapping = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'question_category', $oldcategoryid);
        $categoryid = $mapping && !empty($mapping->newitemid) ? (int) $mapping->newitemid : 0;

        if (!$categoryid && $this->is_samesite()) {
            $categoryid = $oldcategoryid;
        }

        if ($categoryid) {
            $category = $DB->get_record('question_categories', ['id' => $categoryid]);
            if ($category) {
                return $category->id . ',' . $category->contextid;
            }
        }

        return '';
    }

    /**
     * Remaps quizquest_responses.questionid for all restored attempts.
     *
     * Mapped -> new question id. Unmapped on the same site with the question
     * still present -> kept. Otherwise -> 0, so the stale id can never collide
     * with an unrelated question on this site (history shows the question as
     * no longer available).
     *
     * @param int $quizquestid the restored activity id
     */
    protected function remap_response_questionids(int $quizquestid): void {
        global $DB;

        $oldids = $DB->get_fieldset_sql(
            'SELECT DISTINCT r.questionid
               FROM {quizquest_responses} r
               JOIN {quizquest_attempts} a ON a.id = r.attemptid
              WHERE a.quizquest = ? AND r.questionid <> 0',
            [$quizquestid]
        );

        foreach ($oldids as $oldid) {
            $mapping = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'question', $oldid);
            $newid = $mapping && !empty($mapping->newitemid) ? (int) $mapping->newitemid : 0;

            if (!$newid && $this->is_samesite() && $DB->record_exists('question', ['id' => $oldid])) {
                $newid = (int) $oldid;
            }

            if ($newid == $oldid) {
                continue;
            }

            $DB->execute(
                'UPDATE {quizquest_responses}
                    SET questionid = ?
                  WHERE questionid = ?
                    AND attemptid IN (SELECT id FROM {quizquest_attempts} WHERE quizquest = ?)',
                [$newid, $oldid, $quizquestid]
            );
        }
    }
}
