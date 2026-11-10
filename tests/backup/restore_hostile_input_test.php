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

    /**
     * Out-of-range activity settings in a crafted backup are brought back within
     * the limits the settings form enforces.
     */
    public function test_crafted_activity_settings_are_clamped(): void {
        $this->resetAfterTest();

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['summary' => '']);
        $quizquest = $generator->create_module('quizquest', ['course' => $course->id, 'steps' => 3]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);

        $backupid = $this->backup_activity($cm);
        $this->edit_activity_xml($backupid, $cm->id, static function (string $xml): string {
            $values = [
                'steps' => '0',
                'grade' => '-50',
                'maxattempts' => '99',
                'genericresponsedisplay' => '7',
                'showprogress' => '5',
                'wrongpenalty' => '-1',
            ];
            foreach ($values as $field => $value) {
                $element = '<' . $field . '>' . $value . '</' . $field . '>';
                $xml = preg_replace('~<' . $field . '>[^<]*</' . $field . '>~', $element, $xml, 1);
            }
            return $xml;
        });

        $new = $this->restore_as($backupid, $course->id, get_admin()->id);
        $this->assertEquals(1, $new->steps);
        $this->assertEquals(0, $new->grade);
        $this->assertEquals(-1, $new->maxattempts);
        $this->assertEquals(\mod_quizquest\message_bank::DISPLAY_WHEN_NO_FEEDBACK, $new->genericresponsedisplay);
        $this->assertEquals(1, $new->showprogress);
        $this->assertEquals(1, $new->wrongpenalty);
    }

    /**
     * Backs up a quizquest activity (as admin) WITH user data and returns the backup id.
     *
     * @param \stdClass $cm the course module to back up
     * @return string the backup id (its unpacked directory is kept)
     */
    protected function backup_activity_with_userdata(\stdClass $cm): string {
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            get_admin()->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        // In import mode the per-activity userinfo setting does not follow the
        // root users setting, so force it on explicitly as well.
        $activitysetting = $bc->get_plan()->get_setting('quizquest_' . $cm->id . '_userinfo');
        $activitysetting->set_status(\backup_setting::NOT_LOCKED);
        $activitysetting->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        return $backupid;
    }

    /**
     * Restores a backup WITH user data into a course as the given user.
     *
     * @param string $backupid the backup id
     * @param int $courseid the target course
     * @param int $userid the user performing the restore
     * @return \stdClass the restored quizquest record
     */
    protected function restore_with_userdata_as(string $backupid, int $courseid, int $userid): \stdClass {
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
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $after = $DB->get_fieldset_select('quizquest', 'id', 'course = ?', [$courseid]);
        $new = array_values(array_diff($after, $before));
        $this->assertCount(1, $new);
        return $DB->get_record('quizquest', ['id' => reset($new)], '*', MUST_EXIST);
    }

    /**
     * Builds course A with a bank and question the restorer may not use, and course B
     * holding a quizquest with one student attempt whose pending turn is on a
     * question in course B's own bank. A course-level manager in B is the restorer.
     *
     * @return array [courseb, cm in course B, manager in B, question in A, question in B]
     */
    protected function create_response_setup(): array {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $qgen = $generator->get_plugin_generator('core_question');

        $coursea = $generator->create_course(['summary' => '']);
        $qbanka = $generator->create_module('qbank', ['course' => $coursea->id]);
        $categorya = $qgen->create_question_category(['contextid' => \context_module::instance($qbanka->cmid)->id]);
        $questiona = $qgen->create_question('multichoice', 'one_of_four', ['category' => $categorya->id]);

        $courseb = $generator->create_course(['summary' => '']);
        $qbankb = $generator->create_module('qbank', ['course' => $courseb->id]);
        $contextb = \context_module::instance($qbankb->cmid);
        $categoryb = $qgen->create_question_category(['contextid' => $contextb->id]);
        $questionb = $qgen->create_question('multichoice', 'one_of_four', ['category' => $categoryb->id]);

        $student = $generator->create_and_enrol($courseb, 'student');
        $manager = $generator->create_and_enrol($courseb, 'manager');

        $quizquest = $generator->create_module('quizquest', [
            'course' => $courseb->id,
            'steps' => 3,
            'questioncategoryid' => $categoryb->id . ',' . $contextb->id,
        ]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);

        $now = time();
        $attemptid = $DB->insert_record('quizquest_attempts', (object) [
            'quizquest' => $quizquest->id, 'userid' => $student->id, 'status' => 'inprogress',
            'stepstally' => 2, 'ispreview' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attemptid, 'questionid' => $questionb->id, 'timecreated' => $now,
        ]);

        return [$courseb, $cm, $manager, $questiona, $questionb];
    }

    /**
     * Points every backed-up response at the given question id.
     *
     * @param string $backupid the backup id
     * @param int $cmid the original course module id
     * @param int $fromid the genuine question id in the backup
     * @param int $toid the question id to plant
     */
    protected function plant_response_question(string $backupid, int $cmid, int $fromid, int $toid): void {
        $this->edit_activity_xml($backupid, $cmid, static function (string $xml) use ($fromid, $toid): string {
            return str_replace('<questionid>' . $fromid . '</questionid>', '<questionid>' . $toid . '</questionid>', $xml);
        });
    }

    /**
     * Returns the question ids of all restored responses of a quizquest.
     *
     * @param int $quizquestid
     * @return int[]
     */
    protected function restored_response_questionids(int $quizquestid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            'SELECT r.questionid
               FROM {quizquest_responses} r
               JOIN {quizquest_attempts} a ON a.id = r.attemptid
              WHERE a.quizquest = ?',
            [$quizquestid]
        ));
    }

    /**
     * A crafted response pointing at a question in a bank the restorer cannot use
     * is zeroed, so start_attempt can never serve that question back to them (QQ-01).
     */
    public function test_crafted_response_question_from_unauthorised_bank_is_zeroed(): void {
        $this->resetAfterTest();
        [$courseb, $cm, $manager, $questiona, $questionb] = $this->create_response_setup();

        $backupid = $this->backup_activity_with_userdata($cm);
        $this->plant_response_question($backupid, $cm->id, (int) $questionb->id, (int) $questiona->id);

        $new = $this->restore_with_userdata_as($backupid, $courseb->id, $manager->id);
        $this->assertSame([0], $this->restored_response_questionids((int) $new->id));
    }

    /**
     * Control: the same crafted response restored by a user who may use that bank
     * (the admin) keeps its question id, proving the test above reaches the keep path.
     */
    public function test_crafted_response_question_kept_for_user_allowed_to_use_the_bank(): void {
        $this->resetAfterTest();
        [$courseb, $cm, , $questiona, $questionb] = $this->create_response_setup();

        $backupid = $this->backup_activity_with_userdata($cm);
        $this->plant_response_question($backupid, $cm->id, (int) $questionb->id, (int) $questiona->id);

        $new = $this->restore_with_userdata_as($backupid, $courseb->id, get_admin()->id);
        $this->assertSame([(int) $questiona->id], $this->restored_response_questionids((int) $new->id));
    }

    /**
     * A crafted attempt with an unknown status and an out-of-range tally is restored
     * as abandoned with the tally clamped to the activity's steps (QQ-03).
     */
    public function test_crafted_attempt_status_and_tally_are_sanitised(): void {
        global $DB;
        $this->resetAfterTest();
        [$courseb, $cm] = $this->create_response_setup();

        $backupid = $this->backup_activity_with_userdata($cm);
        $this->edit_activity_xml($backupid, $cm->id, static function (string $xml): string {
            $xml = str_replace('<status>inprogress</status>', '<status>bogus</status>', $xml);
            return str_replace('<stepstally>2</stepstally>', '<stepstally>999</stepstally>', $xml);
        });

        $new = $this->restore_with_userdata_as($backupid, $courseb->id, get_admin()->id);
        $attempts = $DB->get_records('quizquest_attempts', ['quizquest' => $new->id]);
        $this->assertCount(1, $attempts);
        $attempt = reset($attempts);
        $this->assertSame('abandoned', $attempt->status);
        $this->assertEquals(3, $attempt->stepstally);
    }
}
