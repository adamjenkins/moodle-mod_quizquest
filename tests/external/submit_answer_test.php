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
 * Tests for the submit_answer external function.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(submit_answer::class)]
final class submit_answer_test extends externallib_testcase {
    /**
     * Creates the activity (steps=1 so one correct answer completes it) and
     * starts an attempt as the student.
     *
     * @param array $fields extra activity fields
     * @return array [course, cm, student, started-attempt result]
     */
    protected function create_started_attempt(array $fields = []): array {
        global $DB;
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id]);
        $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);

        $quizquest = $generator->create_module('quizquest', array_merge([
            'course' => $course->id,
            'questioncategoryid' => $category->id . ',' . $bankcontext->id,
            'steps' => 1,
        ], $fields));
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);

        $this->setUser($student);
        $started = external_api::clean_returnvalue(
            start_attempt::execute_returns(),
            start_attempt::execute($cm->id)
        );

        return [$course, $cm, $student, $started];
    }

    /**
     * A correct typed answer advances the tally and completes a 1-step quest.
     */
    public function test_correct_answer_completes(): void {
        $this->resetAfterTest();
        [, $cm, , $started] = $this->create_started_attempt();

        $result = external_api::clean_returnvalue(
            submit_answer::execute_returns(),
            submit_answer::execute($cm->id, $started['attemptid'], 0, 'frog')
        );

        $this->assertTrue($result['iscorrect']);
        $this->assertEquals(1, $result['stepchange']);
        $this->assertEquals(1, $result['tally']);
        $this->assertTrue($result['completed']);
        $this->assertArrayNotHasKey('question', $result);
    }

    /**
     * A wrong answer makes no progress and serves the next question.
     */
    public function test_wrong_answer_serves_next_question(): void {
        $this->resetAfterTest();
        [, $cm, , $started] = $this->create_started_attempt();

        $result = external_api::clean_returnvalue(
            submit_answer::execute_returns(),
            submit_answer::execute($cm->id, $started['attemptid'], 0, 'newt')
        );

        $this->assertFalse($result['iscorrect']);
        $this->assertEquals(0, $result['tally']);
        $this->assertFalse($result['completed']);
        $this->assertNotEmpty($result['question']['text']);
    }

    /**
     * Another user's attempt id is rejected outright (no IDOR).
     */
    public function test_rejects_foreign_attempt(): void {
        $this->resetAfterTest();
        [$course, $cm, , $started] = $this->create_started_attempt();

        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($other);

        $this->expectException(\dml_missing_record_exception::class);
        submit_answer::execute($cm->id, $started['attemptid'], 0, 'frog');
    }

    /**
     * An empty typed answer for a shortanswer question is invalid.
     */
    public function test_rejects_empty_answer(): void {
        $this->resetAfterTest();
        [, $cm, , $started] = $this->create_started_attempt();

        try {
            submit_answer::execute($cm->id, $started['attemptid'], 0, '   ');
            $this->fail('Expected moodle_exception for an empty answer');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:invalidchoice', $e->errorcode);
        }
    }
}
