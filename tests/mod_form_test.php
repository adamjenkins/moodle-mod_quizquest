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
 * Tests for the activity settings form.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_quizquest_mod_form::class)]
final class mod_form_test extends advanced_testcase {
    /**
     * Creates a qbank instance in the course with one eligible question.
     *
     * @param \stdClass $course
     * @return array [bank context, category]
     */
    protected function create_bank_with_question(\stdClass $course): array {
        $generator = $this->getDataGenerator();
        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $qgen = $generator->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $context->id]);
        $qgen->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        return [$context, $category];
    }

    /**
     * A category submitted from a bank other than the one whose categories were
     * rendered server-side must survive the round trip.
     *
     * When the teacher switches banks, bankpicker.js swaps the category options
     * client-side only. Unless definition() re-renders the options for the
     * submitted bank, formslib's select scrubs the "unknown" submitted value and
     * validation fails with "no questions in this category" despite the category
     * being full.
     */
    public function test_category_from_switched_bank_survives_submission(): void {
        global $CFG, $COURSE, $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        [$defaultbankcontext, $defaultcategory] = $this->create_bank_with_question($course);
        [$otherbankcontext, $othercategory] = $this->create_bank_with_question($course);

        // The saved instance points at the first bank, so its categories are the
        // ones definition() renders by default.
        $quizquest = $generator->create_module('quizquest', [
            'course' => $course->id,
            'questioncategoryid' => "{$defaultcategory->id},{$defaultbankcontext->id}",
        ]);

        // The lister get_available_banks() creates the course system bank on demand; make that
        // happen now so definition() doesn't add a module (and invalidate the course
        // cache) halfway through building the form.
        \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);

        $course = get_course($course->id);
        $cm = get_fast_modinfo($course)->get_cm($quizquest->cmid);

        // Simulate the POST from a browser where the teacher switched the bank
        // and picked a category that only exists in the JS-populated select.
        $submittedcategory = "{$othercategory->id},{$otherbankcontext->id}";
        $_POST = [
            '_qf__mod_quizquest_mod_form' => 1,
            'sesskey' => sesskey(),
            'questionbank' => (string) $otherbankcontext->id,
            'questioncategoryid' => $submittedcategory,
            'visible' => '1',
        ];

        // The form constructor initialises $OUTPUT, which would otherwise reset
        // the global $COURSE to the site course; set up $PAGE the way modedit.php
        // does so definition() sees the right course.
        $PAGE->set_url('/course/modedit.php', ['update' => $cm->id]);
        $PAGE->set_course($course);
        $COURSE = $course;
        require_once($CFG->dirroot . '/course/moodleform_mod.php');
        require_once($CFG->dirroot . '/mod/quizquest/mod_form.php');

        $current = $DB->get_record('quizquest', ['id' => $quizquest->id], '*', MUST_EXIST);
        $current->instance = $quizquest->id;
        $current->coursemodule = $cm->id;

        $form = new \mod_quizquest_mod_form($current, $cm->sectionnum, $cm, $course);

        $this->assertSame(
            $submittedcategory,
            $form->get_submitted_data()->questioncategoryid ?? null
        );
    }
}
