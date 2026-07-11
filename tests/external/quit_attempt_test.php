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
 * Tests for the quit_attempt external function.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quit_attempt::class)]
final class quit_attempt_test extends externallib_testcase {
    /**
     * Creates the activity and starts an attempt as the student.
     *
     * @param array $fields extra activity fields
     * @return array [course, cm, student, started-attempt result]
     */
    protected function create_started_attempt(array $fields = []): array {
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
            'steps' => 4,
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
     * Quitting abandons the attempt; with partial scoring a grade is awarded.
     */
    public function test_quit_awards_partial_grade(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cm, , $started] = $this->create_started_attempt(['partialscoreonquit' => 1]);

        // Progress the attempt by answering correctly once (tally 1 of 4).
        submit_answer::execute($cm->id, $started['attemptid'], 0, 'frog');

        $result = external_api::clean_returnvalue(
            quit_attempt::execute_returns(),
            quit_attempt::execute($cm->id, $started['attemptid'])
        );

        $this->assertTrue($result['abandoned']);
        $this->assertEqualsWithDelta(100 * 1 / 4, $result['grade'], 0.001);
        $this->assertEquals(
            'abandoned',
            $DB->get_field('quizquest_attempts', 'status', ['id' => $started['attemptid']])
        );
    }

    /**
     * A finished attempt cannot be quit again.
     */
    public function test_cannot_quit_twice(): void {
        $this->resetAfterTest();
        [, $cm, , $started] = $this->create_started_attempt();

        quit_attempt::execute($cm->id, $started['attemptid']);

        try {
            quit_attempt::execute($cm->id, $started['attemptid']);
            $this->fail('Expected moodle_exception for a non-inprogress attempt');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:invalidattempt', $e->errorcode);
        }
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
        quit_attempt::execute($cm->id, $started['attemptid']);
    }
}
