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
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Backup and restore roundtrip tests for mod_quizquest.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_quizquest_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_quizquest_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_quizquest_activity_task::class)]
final class backup_restore_test extends advanced_testcase {
    /**
     * Builds a course containing a question bank and a fully configured quizquest
     * with step messages, generic response pools, a progress image, one real
     * student attempt (with an answered and a pending response) and one teacher
     * preview attempt.
     *
     * @return array [course, quizquest record, cm, question, student, category, bankcontext]
     */
    protected function create_full_setup(): array {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id]);
        $question = $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);

        $quizquest = $generator->create_module('quizquest', [
            'course'               => $course->id,
            'questioncategoryid'   => $category->id . ',' . $bankcontext->id,
            'steps'                => 3,
            'allowstudentreview'   => 1,
            'partialscoreonquit'   => 1,
        ]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);
        $context = \context_module::instance($cm->id);

        // Narrative content.
        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 0,
            'textbefore' => 'Welcome to the quest', 'textafter' => 'Good luck',
        ]);
        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 2,
            'textbefore' => 'The gate creaks open', 'textafter' => '',
        ]);
        foreach ([['correct', 'Well done!'], ['correct', 'Nice one!'], ['incorrect', 'Alas, no.']] as $i => [$type, $text]) {
            $DB->insert_record('quizquest_genericresponses', (object) [
                'quizquest' => $quizquest->id, 'responsetype' => $type,
                'responsetext' => $text, 'sortorder' => $i,
            ]);
        }

        // A progress image in the module file area.
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_quizquest', 'filearea' => 'progressimage',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'stage1.png',
        ], 'not-really-a-png');

        // One real student attempt: an answered turn plus a pending turn.
        $now = time();
        $attemptid = $DB->insert_record('quizquest_attempts', (object) [
            'quizquest' => $quizquest->id, 'userid' => $student->id, 'status' => 'inprogress',
            'stepstally' => 2, 'ispreview' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'correctpoolqueue' => '9998,9999', 'incorrectpoolqueue' => '',
        ]);
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attemptid, 'questionid' => $question->id, 'response' => 'One',
            'iscorrect' => 1, 'stepchange' => 1, 'timecreated' => $now,
            'feedbacktext' => 'Well done!', 'stepmsgbefore' => 'The gate creaks open', 'stepmsgafter' => '',
        ]);
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attemptid, 'questionid' => $question->id,
            'timecreated' => $now + 1,
        ]);

        // A teacher preview attempt, which backups must exclude.
        $DB->insert_record('quizquest_attempts', (object) [
            'quizquest' => $quizquest->id, 'userid' => get_admin()->id, 'status' => 'inprogress',
            'stepstally' => 1, 'ispreview' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        return [$course, $quizquest, $cm, $question, $student, $category, $bankcontext];
    }

    /**
     * Duplicating the activity copies all configuration but never user attempts,
     * and keeps the question bank reference (same site, bank still exists).
     */
    public function test_duplicate_module_copies_configuration(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $quizquest, $cm, , , $category, $bankcontext] = $this->create_full_setup();

        // Moodle 5.2 replaced duplicate_module() with cmactions::duplicate().
        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            $newcm = (new \core_courseformat\local\cmactions($course))->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($cm->id));
        }
        $new = $DB->get_record('quizquest', ['id' => $newcm->instance], '*', MUST_EXIST);

        $this->assertEquals($quizquest->steps, $new->steps);
        $this->assertEquals(1, $new->allowstudentreview);
        $this->assertEquals(1, $new->partialscoreonquit);
        $this->assertEquals($category->id . ',' . $bankcontext->id, $new->questioncategoryid);

        $this->assertEqualsCanonicalizing(
            ['Welcome to the quest', 'The gate creaks open'],
            $DB->get_fieldset_select('quizquest_stepmessages', 'textbefore', 'quizquest = ?', [$new->id])
        );
        $this->assertEquals(3, $DB->count_records('quizquest_genericresponses', ['quizquest' => $new->id]));
        $this->assertEquals(
            2,
            $DB->count_records('quizquest_genericresponses', ['quizquest' => $new->id, 'responsetype' => 'correct'])
        );

        $files = get_file_storage()->get_area_files(
            \context_module::instance($newcm->id)->id,
            'mod_quizquest',
            'progressimage',
            0,
            'filename',
            false
        );
        $this->assertCount(1, $files);
        $this->assertEquals('stage1.png', reset($files)->get_filename());

        // Duplicate carries no user data.
        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['quizquest' => $new->id]));
    }

    /**
     * A backup including user data restores real attempts and responses (but not
     * teacher previews), clears the shuffle queues, and keeps same-site question ids.
     */
    public function test_backup_and_restore_with_user_data(): void {
        global $DB, $USER;
        $this->resetAfterTest();

        [$course, $quizquest, $cm, $question, $student] = $this->create_full_setup();

        // Backup the single activity, forcing user data on.
        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
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

        // Restore into a different course on the same site.
        $newcourse = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $newcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $instances = get_fast_modinfo($newcourse)->get_instances_of('quizquest');
        $this->assertCount(1, $instances);
        $newcm = reset($instances);
        $new = $DB->get_record('quizquest', ['id' => $newcm->instance], '*', MUST_EXIST);

        // Only the real attempt came across, mapped to the same user on this site.
        $attempts = $DB->get_records('quizquest_attempts', ['quizquest' => $new->id]);
        $this->assertCount(1, $attempts);
        $attempt = reset($attempts);
        $this->assertEquals($student->id, $attempt->userid);
        $this->assertEquals('inprogress', $attempt->status);
        $this->assertEquals(2, $attempt->stepstally);
        $this->assertEquals(0, $attempt->ispreview);

        // The shuffle queues reference generic-response row ids and must not survive.
        $this->assertEmpty($attempt->correctpoolqueue);

        // Responses restored; the bank was not part of the backup but this is the
        // same site and the question still exists, so its id is kept.
        $responses = $DB->get_records('quizquest_responses', ['attemptid' => $attempt->id], 'timecreated ASC');
        $this->assertCount(2, $responses);
        $answered = reset($responses);
        $this->assertEquals($question->id, $answered->questionid);
        $this->assertEquals('Well done!', $answered->feedbacktext);
        $this->assertEquals('The gate creaks open', $answered->stepmsgbefore);
        $this->assertEquals(1, $answered->iscorrect);
        $pending = end($responses);
        $this->assertNull($pending->iscorrect);

        // Config content came across too.
        $this->assertEquals(2, $DB->count_records('quizquest_stepmessages', ['quizquest' => $new->id]));
        $this->assertEquals(3, $DB->count_records('quizquest_genericresponses', ['quizquest' => $new->id]));
    }
}
