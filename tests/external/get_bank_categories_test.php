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
 * Tests for the get_bank_categories external function.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_bank_categories::class)]
final class get_bank_categories_test extends externallib_testcase {
    /**
     * Creates a course with a teacher, a student and a question bank.
     *
     * @return array [course, teacher, student, bank context, category]
     */
    protected function create_setup(): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id]);

        return [$course, $teacher, $student, $bankcontext, $category];
    }

    /**
     * A teacher receives the category options of an authorised bank.
     */
    public function test_teacher_gets_categories(): void {
        $this->resetAfterTest();
        [$course, $teacher, , $bankcontext, $category] = $this->create_setup();

        $this->setUser($teacher);
        $result = external_api::clean_returnvalue(
            get_bank_categories::execute_returns(),
            get_bank_categories::execute($course->id, $bankcontext->id)
        );

        $values = array_column($result['categories'], 'value');
        $this->assertContains($category->id . ',' . $bankcontext->id, $values);
    }

    /**
     * Students (no manageactivities) are rejected.
     */
    public function test_student_rejected(): void {
        $this->resetAfterTest();
        [$course, , $student, $bankcontext] = $this->create_setup();

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        get_bank_categories::execute($course->id, $bankcontext->id);
    }

    /**
     * A context id that is not one of the user's authorised banks is rejected,
     * even for a teacher.
     */
    public function test_unauthorised_bank_rejected(): void {
        $this->resetAfterTest();
        [$course, $teacher] = $this->create_setup();

        // A bank in a course the teacher is not enrolled in.
        $othercourse = $this->getDataGenerator()->create_course();
        $otherbank = $this->getDataGenerator()->create_module('qbank', ['course' => $othercourse->id]);
        $othercontext = \context_module::instance($otherbank->cmid);

        $this->setUser($teacher);
        try {
            get_bank_categories::execute($course->id, $othercontext->id);
            $this->fail('Expected moodle_exception for an unauthorised bank');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error:bankaccessdenied', $e->errorcode);
        }
    }
}
