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

namespace mod_quizquest\external;

use core_external\external_api;
use core_external\tests\externallib_testcase;

/**
 * Tests for the start_attempt external function.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(start_attempt::class)]
final class start_attempt_test extends externallib_testcase {
    /**
     * Creates a course, bank with one question, quizquest and a student.
     *
     * @param array $fields extra activity fields
     * @return array [course, quizquest record, cm, student]
     */
    protected function create_setup(array $fields = []): array {
        global $DB;
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id]);
        $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);

        $quizquest = $generator->create_module('quizquest', array_merge([
            'course' => $course->id,
            'questioncategoryid' => $category->id . ',' . $bankcontext->id,
            'steps' => 2,
        ], $fields));
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);

        // The step-0 opening narrative.
        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 0,
            'textbefore' => 'Once upon a time', 'textafter' => '',
        ]);

        return [$course, $quizquest, $cm, $student];
    }

    /**
     * A student starts an attempt and receives the intro narrative and a question.
     */
    public function test_student_starts_attempt(): void {
        $this->resetAfterTest();
        [, , $cm, $student] = $this->create_setup();

        $this->setUser($student);
        $result = external_api::clean_returnvalue(
            start_attempt::execute_returns(),
            start_attempt::execute($cm->id)
        );

        $this->assertGreaterThan(0, $result['attemptid']);
        $this->assertEquals(0, $result['tally']);
        $this->assertEquals(2, $result['steps']);
        $this->assertEquals('shortanswer', $result['question']['qtype']);
        $this->assertNotEmpty($result['question']['text']);

        // Step-0 narrative is prepended to the (otherwise empty) history.
        $this->assertEquals('Once upon a time', $result['messages'][0]['message']);

        // Calling again resumes the same attempt.
        $again = external_api::clean_returnvalue(
            start_attempt::execute_returns(),
            start_attempt::execute($cm->id)
        );
        $this->assertEquals($result['attemptid'], $again['attemptid']);
    }

    /**
     * A user without the play capability is rejected.
     */
    public function test_requires_play_capability(): void {
        $this->resetAfterTest();
        [$course, , $cm] = $this->create_setup();

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_module::instance($cm->id);
        $studentrole = $this->getDataGenerator()->create_role();
        assign_capability('mod/quizquest:play', CAP_PROHIBIT, $studentrole, $context->id);
        role_assign($studentrole, $viewer->id, $context->id);

        $this->setUser($viewer);
        $this->expectException(\required_capability_exception::class);
        start_attempt::execute($cm->id);
    }

    /**
     * Students cannot start outside the open window; previewing teachers can.
     */
    public function test_open_window_enforced_for_students_only(): void {
        $this->resetAfterTest();
        [$course, , $cm, $student] = $this->create_setup(['timeopen' => time() + DAYSECS]);

        $this->setUser($student);
        try {
            start_attempt::execute($cm->id);
            $this->fail('Expected moodle_exception for a not-yet-open activity');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:notopenyet', $e->errorcode);
        }

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $result = external_api::clean_returnvalue(
            start_attempt::execute_returns(),
            start_attempt::execute($cm->id)
        );
        $this->assertGreaterThan(0, $result['attemptid']);
    }
}
