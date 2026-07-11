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

namespace mod_quizquest\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Privacy provider tests for mod_quizquest.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_quizquest\privacy\provider::class)]
final class provider_test extends provider_testcase {
    /**
     * Creates a quizquest with attempts for two students.
     *
     * @return array [cm context, quizquest record, student1, student2]
     */
    protected function create_setup(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $quizquest = $generator->create_module('quizquest', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);
        $context = \context_module::instance($cm->id);

        $now = time();
        foreach ([$student1, $student2] as $student) {
            $attemptid = $DB->insert_record('quizquest_attempts', (object) [
                'quizquest' => $quizquest->id, 'userid' => $student->id, 'status' => 'completed',
                'stepstally' => 5, 'ispreview' => 0, 'timecreated' => $now,
                'timemodified' => $now, 'timecompleted' => $now,
            ]);
            $DB->insert_record('quizquest_responses', (object) [
                'attemptid' => $attemptid, 'questionid' => 1, 'response' => 'Paris',
                'iscorrect' => 1, 'stepchange' => 1, 'timecreated' => $now,
                'feedbacktext' => 'Correct!', 'stepmsgbefore' => '', 'stepmsgafter' => '',
            ]);
        }

        return [$context, $quizquest, $student1, $student2];
    }

    /**
     * The metadata must describe every user-data column of both tables.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_quizquest'));
        $items = $collection->get_collection();
        $this->assertCount(2, $items);

        $tables = [];
        foreach ($items as $item) {
            $tables[$item->get_name()] = array_keys($item->get_privacy_fields());
        }

        $this->assertEqualsCanonicalizing(
            ['userid', 'status', 'stepstally', 'timecreated', 'timemodified', 'timecompleted',
                'ispreview', 'correctpoolqueue', 'incorrectpoolqueue'],
            $tables['quizquest_attempts']
        );
        $this->assertEqualsCanonicalizing(
            ['questionid', 'response', 'iscorrect', 'stepchange', 'timecreated',
                'feedbacktext', 'stepmsgbefore', 'stepmsgafter'],
            $tables['quizquest_responses']
        );
    }

    /**
     * Contexts are returned only for users with attempts.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$context, , $student1] = $this->create_setup();

        $contextlist = provider::get_contexts_for_userid($student1->id);
        $this->assertEquals([$context->id], $contextlist->get_contextids());

        $other = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($other->id)->get_contextids());
    }

    /**
     * All users with attempts in a module context are listed.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [$context, , $student1, $student2] = $this->create_setup();

        $userlist = new userlist($context, 'mod_quizquest');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$student1->id, $student2->id], $userlist->get_userids());
    }

    /**
     * Export produces per-attempt data including responses.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        [$context, , $student1] = $this->create_setup();

        $this->export_context_data_for_user($student1->id, $context, 'mod_quizquest');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $attemptdata = $writer->get_data([get_string('attempt', 'mod_quizquest', 1)]);
        $this->assertEquals('completed', $attemptdata->status);
        $this->assertEquals(5, $attemptdata->stepstally);
        $this->assertCount(1, $attemptdata->responses);
        $this->assertEquals('Paris', $attemptdata->responses[0]->response);
        $this->assertEquals('Correct!', $attemptdata->responses[0]->feedbacktext);
    }

    /**
     * Context-wide deletion removes every user's attempts and responses.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        [$context, $quizquest] = $this->create_setup();

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['quizquest' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('quizquest_responses'));
    }

    /**
     * Single-user deletion leaves other users' data alone.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$context, $quizquest, $student1, $student2] = $this->create_setup();

        $contextlist = new approved_contextlist($student1, 'mod_quizquest', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['userid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('quizquest_attempts', ['userid' => $student2->id]));
        $this->assertEquals(1, $DB->count_records('quizquest_responses'));
    }

    /**
     * Userlist deletion removes exactly the approved users' data.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$context, $quizquest, $student1, $student2] = $this->create_setup();

        $userlist = new approved_userlist($context, 'mod_quizquest', [$student1->id]);
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['userid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('quizquest_attempts', ['userid' => $student2->id]));
    }
}
