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
 * Tests for the narrative message bank.
 *
 * @package    mod_quizquest
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(message_bank::class)]
final class message_bank_test extends advanced_testcase {
    /**
     * Creates a quizquest with a generic-response pool.
     *
     * @param string[] $correct texts for the correct pool
     * @return stdClass the quizquest record
     */
    protected function create_with_pool(array $correct): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $quizquest = $this->getDataGenerator()->create_module('quizquest', ['course' => $course->id]);

        foreach ($correct as $i => $text) {
            $DB->insert_record('quizquest_genericresponses', (object) [
                'quizquest' => $quizquest->id, 'responsetype' => 'correct',
                'responsetext' => $text, 'sortorder' => $i,
            ]);
        }
        return $quizquest;
    }

    /**
     * Step messages are looked up by exact step, null when absent.
     */
    public function test_get_step_message(): void {
        global $DB;
        $this->resetAfterTest();
        $quizquest = $this->create_with_pool([]);

        $DB->insert_record('quizquest_stepmessages', (object) [
            'quizquest' => $quizquest->id, 'step' => 2, 'textbefore' => 'Halfway!', 'textafter' => '',
        ]);

        $message = message_bank::get_step_message((int) $quizquest->id, 2);
        $this->assertEquals('Halfway!', $message->textbefore);
        $this->assertNull(message_bank::get_step_message((int) $quizquest->id, 1));
    }

    /**
     * An empty pool yields an empty string.
     */
    public function test_pick_pool_response_empty_pool(): void {
        $this->resetAfterTest();
        $quizquest = $this->create_with_pool([]);
        $attempt = (object) ['correctpoolqueue' => '', 'incorrectpoolqueue' => ''];

        $this->assertSame('', message_bank::pick_pool_response($attempt, (int) $quizquest->id, 'correct'));
    }

    /**
     * The whole pool is consumed once before any entry repeats, then reshuffles.
     */
    public function test_pick_pool_response_cycles_without_repeats(): void {
        $this->resetAfterTest();
        $quizquest = $this->create_with_pool(['One', 'Two', 'Three']);
        $attempt = (object) ['correctpoolqueue' => '', 'incorrectpoolqueue' => ''];

        $firstcycle = [];
        for ($i = 0; $i < 3; $i++) {
            $firstcycle[] = message_bank::pick_pool_response($attempt, (int) $quizquest->id, 'correct');
        }
        $this->assertEqualsCanonicalizing(['One', 'Two', 'Three'], $firstcycle);

        // The next pick starts a fresh shuffle of the same pool.
        $next = message_bank::pick_pool_response($attempt, (int) $quizquest->id, 'correct');
        $this->assertContains($next, ['One', 'Two', 'Three']);
    }

    /**
     * Queue entries whose pool rows were deleted are dropped silently.
     */
    public function test_pick_pool_response_heals_stale_queue(): void {
        $this->resetAfterTest();
        $quizquest = $this->create_with_pool(['Only entry']);
        // The queue references ids that no longer exist.
        $attempt = (object) ['correctpoolqueue' => '424242,424243', 'incorrectpoolqueue' => ''];

        $this->assertEquals(
            'Only entry',
            message_bank::pick_pool_response($attempt, (int) $quizquest->id, 'correct')
        );
    }
}
