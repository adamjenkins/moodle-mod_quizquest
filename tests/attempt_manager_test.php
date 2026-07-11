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

use advanced_testcase;

/**
 * Tests for the attempt manager.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempt_manager::class)]
final class attempt_manager_test extends advanced_testcase {
    /**
     * Creates a course, student and quizquest instance.
     *
     * @param array $instancefields extra fields for the activity
     * @param array $coursefields extra fields for the course
     * @return array [course, quizquest record, cm_info, student]
     */
    protected function create_setup(array $instancefields = [], array $coursefields = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course($coursefields);
        $student = $generator->create_and_enrol($course, 'student');
        $quizquest = $generator->create_module('quizquest', array_merge([
            'course' => $course->id,
            'steps'  => 3,
            'grade'  => 100,
        ], $instancefields));
        $cm = get_fast_modinfo($course)->get_cm($quizquest->cmid);
        return [$course, $quizquest, $cm, $student];
    }

    /**
     * The open/close window calculations.
     */
    public function test_is_open_and_is_closed(): void {
        $now = 1000000;

        $quizquest = (object) ['timeopen' => 0, 'timeclose' => 0];
        $this->assertTrue(attempt_manager::is_open($quizquest, $now));
        $this->assertFalse(attempt_manager::is_closed($quizquest, $now));

        $quizquest = (object) ['timeopen' => $now + 100, 'timeclose' => 0];
        $this->assertFalse(attempt_manager::is_open($quizquest, $now));
        $this->assertFalse(attempt_manager::is_closed($quizquest, $now));

        $quizquest = (object) ['timeopen' => 0, 'timeclose' => $now - 100];
        $this->assertFalse(attempt_manager::is_open($quizquest, $now));
        $this->assertTrue(attempt_manager::is_closed($quizquest, $now));

        $quizquest = (object) ['timeopen' => $now - 100, 'timeclose' => $now + 100];
        $this->assertTrue(attempt_manager::is_open($quizquest, $now));
    }

    /**
     * Attempt limits: unlimited, exhausted, and the preview bypass.
     */
    public function test_can_start_new_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [, $quizquest, , $student] = $this->create_setup(['maxattempts' => 1]);
        $manager = new attempt_manager();

        $this->assertTrue($manager->can_start_new_attempt($quizquest, $student->id));

        $DB->insert_record('quizquest_attempts', (object) [
            'quizquest' => $quizquest->id, 'userid' => $student->id, 'status' => 'completed',
            'stepstally' => 3, 'ispreview' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertFalse($manager->can_start_new_attempt($quizquest, $student->id));

        // Preview attempts bypass the limit entirely.
        $this->assertTrue($manager->can_start_new_attempt($quizquest, $student->id, true));

        // Unlimited attempts.
        $quizquest->maxattempts = -1;
        $this->assertTrue($manager->can_start_new_attempt($quizquest, $student->id));
    }

    /**
     * get_or_create_attempt returns the active attempt, creates new ones, fires
     * the started event, and throws once attempts are exhausted.
     */
    public function test_get_or_create_attempt(): void {
        $this->resetAfterTest();
        [, $quizquest, , $student] = $this->create_setup(['maxattempts' => 1]);
        $manager = new attempt_manager();

        $sink = $this->redirectEvents();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\attempt_started);
        $sink->close();

        $this->assertEquals('inprogress', $attempt->status);
        $this->assertCount(1, $events);

        // A second call resumes rather than creates.
        $again = $manager->get_or_create_attempt($quizquest, $student->id);
        $this->assertEquals($attempt->id, $again->id);

        // Finish it; the limit now blocks a new one.
        $manager->update_tally($attempt, 3);
        $attempt->status = 'completed';
        global $DB;
        $DB->update_record('quizquest_attempts', $attempt);

        $this->expectException(\moodle_exception::class);
        $manager->get_or_create_attempt($quizquest, $student->id);
    }

    /**
     * The tally never drops below zero.
     */
    public function test_update_tally_floor(): void {
        $this->resetAfterTest();
        [, $quizquest, , $student] = $this->create_setup();
        $manager = new attempt_manager();

        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);
        $manager->update_tally($attempt, -1);
        $this->assertEquals(0, $attempt->stepstally);

        $manager->update_tally($attempt, 2);
        $manager->update_tally($attempt, -1);
        $this->assertEquals(1, $attempt->stepstally);
    }

    /**
     * Completing an attempt awards the full grade and marks activity completion.
     */
    public function test_complete_attempt(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        [$course, $quizquest, $cm, $student] = $this->create_setup(
            ['completion' => COMPLETION_TRACKING_AUTOMATIC],
            ['enablecompletion' => 1]
        );
        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);
        $manager->update_tally($attempt, 3);

        $manager->complete_attempt($attempt, $quizquest, $course, $cm);

        $this->assertEquals('completed', $DB->get_field('quizquest_attempts', 'status', ['id' => $attempt->id]));

        $grades = grade_get_grades($course->id, 'mod', 'quizquest', $quizquest->id, $student->id);
        $this->assertEquals(100.0, (float) $grades->items[0]->grades[$student->id]->grade);

        $completion = new \completion_info($course);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            $completion->get_data($cm, false, $student->id)->completionstate
        );
    }

    /**
     * Abandoning awards a proportional grade only when partial scoring is on.
     */
    public function test_abandon_attempt_partial_grade(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        [$course, $quizquest, $cm, $student] = $this->create_setup(['partialscoreonquit' => 1]);
        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);
        $manager->update_tally($attempt, 2);

        $grade = $manager->abandon_attempt($attempt, $quizquest, $course, $cm);
        $this->assertEqualsWithDelta(100 * 2 / 3, $grade, 0.001);

        $grades = grade_get_grades($course->id, 'mod', 'quizquest', $quizquest->id, $student->id);
        $this->assertEqualsWithDelta(100 * 2 / 3, (float) $grades->items[0]->grades[$student->id]->grade, 0.001);
    }

    /**
     * Abandoning without partial scoring awards nothing.
     */
    public function test_abandon_attempt_no_partial_grade(): void {
        $this->resetAfterTest();
        [$course, $quizquest, $cm, $student] = $this->create_setup(['partialscoreonquit' => 0]);
        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);
        $manager->update_tally($attempt, 2);

        $this->assertEquals(0.0, $manager->abandon_attempt($attempt, $quizquest, $course, $cm));
    }

    /**
     * abandon_expired_attempt acts only on in-progress attempts of closed activities.
     */
    public function test_abandon_expired_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [, $quizquest, , $student] = $this->create_setup();
        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);

        // Not closed: nothing happens.
        $this->assertFalse($manager->abandon_expired_attempt($attempt, $quizquest));

        $quizquest->timeclose = time() - 10;
        $DB->update_record('quizquest', $quizquest);
        $this->assertTrue($manager->abandon_expired_attempt($attempt, $quizquest));
        $this->assertEquals('abandoned', $DB->get_field('quizquest_attempts', 'status', ['id' => $attempt->id]));
    }

    /**
     * build_history interleaves questions, answers, feedback and step messages.
     */
    public function test_build_history(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $quizquest, , $student] = $this->create_setup();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => \context_module::instance($qbank->cmid)->id]);
        $question = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);

        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);

        $now = time();
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attempt->id, 'questionid' => $question->id, 'response' => 'frog',
            'iscorrect' => 1, 'stepchange' => 1, 'timecreated' => $now,
            'feedbacktext' => 'Well done!', 'stepmsgbefore' => 'A door opens', 'stepmsgafter' => 'Onwards!',
        ]);
        // A pending (unanswered) turn must not appear in the history.
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attempt->id, 'questionid' => $question->id, 'timecreated' => $now + 1,
        ]);

        $messages = $manager->build_history($attempt->id);

        $this->assertCount(5, $messages);
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertEquals('frog', $messages[1]['message']);
        $this->assertEquals('A door opens', $messages[2]['message']);
        $this->assertEquals('Well done!', $messages[3]['message']);
        $this->assertEquals('Onwards!', $messages[4]['message']);
    }
}
