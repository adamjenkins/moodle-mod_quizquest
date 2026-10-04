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

namespace mod_quizquest\backup;

use advanced_testcase;
use backup;
use backup_controller;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Restore tests that feed a hand-edited (crafted) backup into mod_quizquest.
 *
 * Each test makes a genuine single-activity backup, rewrites the activity's
 * quizquest.xml in the unpacked backup directory (exactly what a teacher can do
 * to a downloaded .mbz before uploading it again), and restores it.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_quizquest_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_quizquest_activity_task::class)]
final class restore_hostile_input_test extends advanced_testcase {
    /**
     * Backs up a quizquest activity (as admin) and returns the backup id.
     *
     * @param \stdClass $cm the course module to back up
     * @return string the backup id (its unpacked directory is kept)
     */
    protected function backup_activity(\stdClass $cm): string {
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            get_admin()->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        return $backupid;
    }

    /**
     * Applies a rewrite to the backed-up quizquest.xml.
     *
     * @param string $backupid the backup id
     * @param int $cmid the original course module id
     * @param callable $rewrite function(string $xml): string
     */
    protected function edit_activity_xml(string $backupid, int $cmid, callable $rewrite): void {
        global $CFG;
        $path = $CFG->backuptempdir . '/' . $backupid . '/activities/quizquest_' . $cmid . '/quizquest.xml';
        $this->assertFileExists($path);
        $original = file_get_contents($path);
        $edited = $rewrite($original);
        $this->assertNotSame($original, $edited, 'The crafted rewrite must actually change the backup.');
        file_put_contents($path, $edited);
    }

    /**
     * Restores a backup into a course as the given user and returns the new quizquest record.
     *
     * @param string $backupid the backup id
     * @param int $courseid the target course
     * @param int $userid the user performing the restore
     * @return \stdClass the restored quizquest record
     */
    protected function restore_as(string $backupid, int $courseid, int $userid): \stdClass {
        global $DB;

        $before = $DB->get_fieldset_select('quizquest', 'id', 'course = ?', [$courseid]);
        $this->setUser($userid);
        $rc = new restore_controller(
            $backupid,
            $courseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $userid,
            backup::TARGET_CURRENT_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $after = $DB->get_fieldset_select('quizquest', 'id', 'course = ?', [$courseid]);
        $new = array_values(array_diff($after, $before));
        $this->assertCount(1, $new);
        return $DB->get_record('quizquest', ['id' => reset($new)], '*', MUST_EXIST);
    }

    /**
     * Builds two courses: course A with a question bank the teacher has no access to,
     * and course B (the teacher's own) holding a quizquest with no bank picked yet.
     *
     * @return array [courseb, cm in course B, teacher, category ref in A, category ref in B]
     */
    protected function create_two_course_setup(): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $qgen = $generator->get_plugin_generator('core_question');

        $coursea = $generator->create_course();
        $qbanka = $generator->create_module('qbank', ['course' => $coursea->id]);
        $contexta = \context_module::instance($qbanka->cmid);
        $categorya = $qgen->create_question_category(['contextid' => $contexta->id]);
        $qgen->create_question('multichoice', 'one_of_four', ['category' => $categorya->id]);

        $courseb = $generator->create_course();
        $qbankb = $generator->create_module('qbank', ['course' => $courseb->id]);
        $contextb = \context_module::instance($qbankb->cmid);
        $categoryb = $qgen->create_question_category(['contextid' => $contextb->id]);

        $teacher = $generator->create_and_enrol($courseb, 'editingteacher');

        $quizquest = $generator->create_module('quizquest', ['course' => $courseb->id, 'steps' => 3]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);

        return [
            $courseb,
            $cm,
            $teacher,
            $categorya->id . ',' . $contexta->id,
            $categoryb->id . ',' . $contextb->id,
        ];
    }

    /**
     * Points the backed-up activity's questioncategoryid at the given reference.
     *
     * @param string $backupid the backup id
     * @param int $cmid the original course module id
     * @param string $reference the "categoryid,contextid" reference to plant
     */
    protected function plant_category(string $backupid, int $cmid, string $reference): void {
        $this->edit_activity_xml($backupid, $cmid, static function (string $xml) use ($reference): string {
            return preg_replace(
                '~<questioncategoryid>[^<]*</questioncategoryid>~',
                '<questioncategoryid>' . $reference . '</questioncategoryid>',
                $xml
            );
        });
    }

    /**
     * A teacher restoring a crafted backup that points at another course's bank,
     * which they cannot use, must not end up with that bank: the reference is cleared.
     */
    public function test_crafted_reference_to_unauthorised_bank_is_cleared(): void {
        $this->resetAfterTest();
        [$courseb, $cm, $teacher, $refa] = $this->create_two_course_setup();

        $backupid = $this->backup_activity($cm);
        $this->plant_category($backupid, $cm->id, $refa);

        $new = $this->restore_as($backupid, $courseb->id, $teacher->id);
        $this->assertSame('', (string) $new->questioncategoryid);
    }

    /**
     * Control: the same crafted reference restored by a user who may use that bank
     * (the admin) is kept, proving the test above reaches the same-site keep path.
     */
    public function test_crafted_reference_kept_for_user_allowed_to_use_the_bank(): void {
        $this->resetAfterTest();
        [$courseb, $cm, , $refa] = $this->create_two_course_setup();

        $backupid = $this->backup_activity($cm);
        $this->plant_category($backupid, $cm->id, $refa);

        $new = $this->restore_as($backupid, $courseb->id, get_admin()->id);
        $this->assertSame($refa, (string) $new->questioncategoryid);
    }

    /**
     * A teacher restoring a reference to their own course's bank keeps it.
     */
    public function test_reference_to_own_course_bank_is_kept_for_teacher(): void {
        $this->resetAfterTest();
        [$courseb, $cm, $teacher, , $refb] = $this->create_two_course_setup();

        $backupid = $this->backup_activity($cm);
        $this->plant_category($backupid, $cm->id, $refb);

        $new = $this->restore_as($backupid, $courseb->id, $teacher->id);
        $this->assertSame($refb, (string) $new->questioncategoryid);
    }

    /**
     * Duplicate, negative, non-numeric and out-of-range step messages in a crafted
     * backup must not abort the restore; only the valid, first-seen row survives.
     */
    public function test_crafted_step_messages_do_not_abort_restore(): void {
        global $DB;
        $this->resetAfterTest();

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quizquest = $generator->create_module('quizquest', ['course' => $course->id, 'steps' => 3]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);
        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 1, 'textbefore' => 'First', 'textafter' => '',
        ]);

        $backupid = $this->backup_activity($cm);
        $this->edit_activity_xml($backupid, $cm->id, static function (string $xml): string {
            $extra = '';
            $rows = [['1', 'Duplicate'], ['-2', 'Negative'], ['abc', 'Not a number'], ['99', 'Beyond the last step']];
            foreach ($rows as $i => [$step, $text]) {
                $extra .= '<stepmessage id="' . (900 + $i) . '"><step>' . $step . '</step>'
                    . '<textbefore>' . $text . '</textbefore><textafter></textafter></stepmessage>';
            }
            return str_replace('</stepmessages>', $extra . '</stepmessages>', $xml);
        });

        $new = $this->restore_as($backupid, $course->id, get_admin()->id);

        $rows = $DB->get_records('quizquest_stepmessages', ['quizquest' => $new->id]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertEquals(1, $row->step);
        $this->assertSame('First', $row->textbefore);
    }
}
