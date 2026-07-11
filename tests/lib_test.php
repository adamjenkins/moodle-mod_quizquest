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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quizquest/lib.php');

/**
 * Tests for lib.php callbacks: grades, course reset, calendar events.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('quizquest_get_user_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('quizquest_reset_userdata')]
#[\PHPUnit\Framework\Attributes\CoversFunction('quizquest_update_events')]
#[\PHPUnit\Framework\Attributes\CoversFunction('quizquest_delete_instance')]
final class lib_test extends advanced_testcase {
    /**
     * Creates a course, student and quizquest.
     *
     * @param array $fields extra instance fields
     * @return array [course, quizquest record, student]
     */
    protected function create_setup(array $fields = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $quizquest = $generator->create_module('quizquest', array_merge([
            'course' => $course->id, 'steps' => 4, 'grade' => 100,
        ], $fields));
        return [$course, $quizquest, $student];
    }

    /**
     * Inserts an attempt row.
     *
     * @param stdClass $quizquest activity record
     * @param int $userid user
     * @param string $status attempt status
     * @param int $tally steps tally
     * @param int $timecompleted completion time
     * @return int attempt id
     */
    protected function add_attempt(\stdClass $quizquest, int $userid, string $status, int $tally, int $timecompleted = 0): int {
        global $DB;
        $now = time();
        return $DB->insert_record('quizquest_attempts', (object) [
            'quizquest' => $quizquest->id, 'userid' => $userid, 'status' => $status,
            'stepstally' => $tally, 'ispreview' => 0, 'timecreated' => $now,
            'timemodified' => $now, 'timecompleted' => $timecompleted ?: $now,
        ]);
    }

    /**
     * A completed attempt earns the full grade, beating any partial grade.
     */
    public function test_get_user_grades_full_beats_partial(): void {
        $this->resetAfterTest();
        [, $quizquest, $student] = $this->create_setup(['partialscoreonquit' => 1]);

        $this->add_attempt($quizquest, (int) $student->id, 'abandoned', 2);
        $grades = quizquest_get_user_grades($quizquest, (int) $student->id);
        $this->assertEqualsWithDelta(50.0, $grades[$student->id]->rawgrade, 0.001);

        $this->add_attempt($quizquest, (int) $student->id, 'completed', 4);
        $grades = quizquest_get_user_grades($quizquest, (int) $student->id);
        $this->assertEquals(100.0, $grades[$student->id]->rawgrade);
    }

    /**
     * Partial grades require the setting; the highest partial wins.
     */
    public function test_get_user_grades_partial_rules(): void {
        $this->resetAfterTest();

        // Setting off: abandoned attempts earn nothing.
        [, $off, $student] = $this->create_setup(['partialscoreonquit' => 0]);
        $this->add_attempt($off, (int) $student->id, 'abandoned', 2);
        $this->assertSame([], quizquest_get_user_grades($off, (int) $student->id));

        // Setting on: highest of multiple abandoned attempts.
        [, $on, $student2] = $this->create_setup(['partialscoreonquit' => 1]);
        $this->add_attempt($on, (int) $student2->id, 'abandoned', 1);
        $this->add_attempt($on, (int) $student2->id, 'abandoned', 3);
        $grades = quizquest_get_user_grades($on, (int) $student2->id);
        $this->assertEqualsWithDelta(75.0, $grades[$student2->id]->rawgrade, 0.001);
    }

    /**
     * Course reset deletes attempts/responses and can shift dates with events.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $timeopen = time() + DAYSECS;
        $timeclose = time() + 2 * DAYSECS;
        [$course, $quizquest, $student] = $this->create_setup([
            'timeopen' => $timeopen, 'timeclose' => $timeclose,
        ]);

        $attemptid = $this->add_attempt($quizquest, (int) $student->id, 'completed', 4);
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attemptid, 'questionid' => 1, 'response' => 'x', 'timecreated' => time(),
        ]);

        $data = (object) [
            'courseid' => $course->id,
            'reset_quizquest_attempts' => 1,
            'timeshift' => DAYSECS,
        ];
        $status = quizquest_reset_userdata($data);

        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['quizquest' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('quizquest_responses', ['attemptid' => $attemptid]));
        $this->assertNotEmpty(array_filter(
            $status,
            fn($s) => $s['item'] === get_string('openclosedatesupdated', 'mod_quizquest')
        ));

        // Dates shifted by one day, and the calendar events follow.
        $updated = $DB->get_record('quizquest', ['id' => $quizquest->id]);
        $this->assertEquals($timeopen + DAYSECS, $updated->timeopen);
        $this->assertEquals($timeclose + DAYSECS, $updated->timeclose);
        $this->assertEquals(
            $timeopen + DAYSECS,
            $DB->get_field('event', 'timestart', [
                'modulename' => 'quizquest', 'instance' => $quizquest->id, 'eventtype' => QUIZQUEST_EVENT_TYPE_OPEN,
            ])
        );
    }

    /**
     * Calendar events are created for both dates and removed when dates clear.
     */
    public function test_update_events(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $quizquest] = $this->create_setup([
            'timeopen' => time() + HOURSECS, 'timeclose' => time() + DAYSECS,
        ]);

        $conditions = ['modulename' => 'quizquest', 'instance' => $quizquest->id];
        $this->assertEquals(2, $DB->count_records('event', $conditions));

        $quizquest->timeopen = 0;
        $quizquest->timeclose = 0;
        $DB->update_record('quizquest', $quizquest);
        quizquest_update_events($quizquest);
        $this->assertEquals(0, $DB->count_records('event', $conditions));
    }

    /**
     * Deleting the instance removes every dependent row and its events.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $quizquest, $student] = $this->create_setup(['timeclose' => time() + DAYSECS]);
        $attemptid = $this->add_attempt($quizquest, (int) $student->id, 'inprogress', 1);
        $DB->insert_record('quizquest_responses', (object) [
            'attemptid' => $attemptid, 'questionid' => 1, 'timecreated' => time(),
        ]);
        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 1, 'textbefore' => 'x', 'textafter' => '',
        ]);
        $DB->insert_record('quizquest_genericresponses', (object) [
            'quizquest' => $quizquest->id, 'responsetype' => 'correct', 'responsetext' => 'x', 'sortorder' => 0,
        ]);

        $this->assertTrue(quizquest_delete_instance($quizquest->id));

        $this->assertEquals(0, $DB->count_records('quizquest', ['id' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('quizquest_attempts', ['quizquest' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('quizquest_responses', ['attemptid' => $attemptid]));
        $this->assertEquals(0, $DB->count_records('quizquest_stepmessages', ['quizquest' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('quizquest_genericresponses', ['quizquest' => $quizquest->id]));
        $this->assertEquals(0, $DB->count_records('event', ['modulename' => 'quizquest', 'instance' => $quizquest->id]));
    }

    /**
     * Course module info exposes completion rules and dates in customdata.
     */
    public function test_get_coursemodule_info(): void {
        $this->resetAfterTest();
        $timeopen = time() + HOURSECS;
        [, $quizquest] = $this->create_setup(['timeopen' => $timeopen]);

        $cm = get_coursemodule_from_instance('quizquest', $quizquest->id);
        $info = quizquest_get_coursemodule_info($cm);

        $this->assertEquals($timeopen, $info->customdata['timeopen']);
        $this->assertArrayNotHasKey('timeclose', $info->customdata);
    }
}
