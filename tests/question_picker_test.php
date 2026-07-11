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
 * Tests for the question picker.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(question_picker::class)]
final class question_picker_test extends advanced_testcase {
    /**
     * Creates a course with a question bank and a category.
     *
     * @return array [course, bank context, category, question generator]
     */
    protected function create_bank(): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id]);
        return [$course, $bankcontext, $category, $qgen];
    }

    /**
     * parse_category extracts the category id from the stored value.
     */
    public function test_parse_category(): void {
        $this->assertEquals(7, question_picker::parse_category('7,162'));
        $this->assertEquals(0, question_picker::parse_category(''));
        $this->assertEquals(0, question_picker::parse_category(null));
    }

    /**
     * Eligibility: single-answer multichoice and shortanswer only, and
     * subcategories only when requested.
     */
    public function test_get_eligible_question_ids(): void {
        $this->resetAfterTest();
        [, , $category, $qgen] = $this->create_bank();

        $single = $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        $qgen->create_question('multichoice', 'two_of_four', ['category' => $category->id]);
        $short = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $qgen->create_question('essay', null, ['category' => $category->id]);

        $subcategory = $qgen->create_question_category([
            'contextid' => $category->contextid, 'parent' => $category->id,
        ]);
        $subquestion = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $subcategory->id]);

        $this->assertEqualsCanonicalizing(
            [$single->id, $short->id],
            question_picker::get_eligible_question_ids($category->id)
        );
        $this->assertEqualsCanonicalizing(
            [$single->id, $short->id, $subquestion->id],
            question_picker::get_eligible_question_ids($category->id, true)
        );
        $this->assertSame([], question_picker::get_eligible_question_ids(0));
    }

    /**
     * Only the latest ready version of an edited question is eligible.
     */
    public function test_get_eligible_question_ids_latest_version(): void {
        $this->resetAfterTest();
        [, , $category, $qgen] = $this->create_bank();

        $question = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $edited = $qgen->update_question($question, null, ['name' => 'Edited version']);

        $eligible = question_picker::get_eligible_question_ids($category->id);
        $this->assertEquals([$edited->id], $eligible);
    }

    /**
     * pick_question prefers questions never seen by the user in any attempt.
     */
    public function test_pick_question_prefers_unseen(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $bankcontext, $category, $qgen] = $this->create_bank();

        $q1 = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);
        $q2 = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quizquest = $this->getDataGenerator()->create_module('quizquest', [
            'course' => $course->id,
            'questioncategoryid' => $category->id . ',' . $bankcontext->id,
        ]);

        $manager = new attempt_manager();
        $attempt = $manager->get_or_create_attempt($quizquest, $student->id);

        // The user already saw q1 in this activity.
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attempt->id, 'questionid' => $q1->id, 'response' => 'frog',
            'iscorrect' => 1, 'stepchange' => 1, 'timecreated' => time(),
        ]);

        // With q1 seen, the only unseen question is q2 — always picked.
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(
                $q2->id,
                question_picker::pick_question($quizquest, (int) $student->id, (int) $attempt->id)
            );
        }
    }

    /**
     * The question payload converts content to plain text and lists choices.
     */
    public function test_get_question_payload(): void {
        $this->resetAfterTest();
        [, , $category, $qgen] = $this->create_bank();

        $question = $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        $payload = question_picker::get_question_payload($question->id);

        $this->assertEquals('multichoice', $payload['qtype']);
        $this->assertStringNotContainsString('<', $payload['text']);
        $this->assertCount(4, $payload['choices']);
        foreach ($payload['choices'] as $choice) {
            $this->assertArrayHasKey('id', $choice);
            $this->assertStringNotContainsString('<', $choice['label']);
        }
    }

    /**
     * Multichoice evaluation accepts the correct answer, rejects wrong ones,
     * and throws for a forged answer id.
     */
    public function test_evaluate_multichoice(): void {
        $this->resetAfterTest();
        [, , $category, $qgen] = $this->create_bank();

        $question = $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        $payload = question_picker::get_question_payload($question->id);

        $results = [];
        foreach ($payload['choices'] as $choice) {
            $result = question_picker::evaluate_multichoice($question->id, $choice['id']);
            $results[$choice['label']] = $result['iscorrect'];
        }
        $this->assertEquals(1, count(array_filter($results)), 'Exactly one choice must be correct');

        $this->expectException(\moodle_exception::class);
        question_picker::evaluate_multichoice($question->id, 999999);
    }

    /**
     * Shortanswer evaluation uses the question's own matching rules.
     */
    public function test_evaluate_shortanswer(): void {
        $this->resetAfterTest();
        [, , $category, $qgen] = $this->create_bank();

        // The frogtoad template: 'frog' is 100%, 'toad' is 80%.
        $question = $qgen->create_question('shortanswer', 'frogtoad', ['category' => $category->id]);

        $result = question_picker::evaluate_shortanswer($question->id, 'frog');
        $this->assertTrue($result['iscorrect']);
        $this->assertEquals('frog', $result['responselabel']);

        $this->assertFalse(question_picker::evaluate_shortanswer($question->id, 'toad')['iscorrect']);
        $this->assertFalse(question_picker::evaluate_shortanswer($question->id, 'newt')['iscorrect']);
    }
}
