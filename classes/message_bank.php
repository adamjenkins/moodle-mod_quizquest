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

/**
 * Looks up teacher-configured narrative text: step-triggered messages and
 * shuffled generic correct/incorrect response pools.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_bank {
    /**
     * Returns the step message configured for a given step tally, if any.
     *
     * @param int $quizquestid Activity instance id
     * @param int $step        The step tally value to look up
     * @return stdClass|null Record with textbefore/textafter, or null if none configured
     */
    public static function get_step_message(int $quizquestid, int $step): ?\stdClass {
        global $DB;
        return $DB->get_record('quizquest_stepmessages', ['quizquest' => $quizquestid, 'step' => $step]) ?: null;
    }

    /**
     * Picks the next response from a shuffled generic response pool, without
     * repeating an entry until the whole pool has been shown once.
     *
     * The remaining shuffle order is tracked on the attempt record's
     * correctpoolqueue/incorrectpoolqueue field (mutated in place); the
     * caller is responsible for persisting the attempt record.
     *
     * @param stdClass $attempt     The attempt record (its pool queue field is updated in place)
     * @param int      $quizquestid Activity instance id
     * @param string   $type        'correct' or 'incorrect'
     * @return string The response text, or '' if the pool is empty
     */
    public static function pick_pool_response(\stdClass $attempt, int $quizquestid, string $type): string {
        global $DB;

        $pool = $DB->get_records(
            'quizquest_genericresponses',
            ['quizquest' => $quizquestid, 'responsetype' => $type],
            'sortorder ASC, id ASC'
        );
        if (!$pool) {
            return '';
        }

        $field = $type === 'correct' ? 'correctpoolqueue' : 'incorrectpoolqueue';

        $queue = array_filter(array_map('intval', explode(',', (string) ($attempt->{$field} ?? ''))));
        // Drop ids that no longer exist in the pool (entries may have been edited since the queue was seeded).
        $queue = array_values(array_intersect($queue, array_keys($pool)));

        if (!$queue) {
            $queue = array_keys($pool);
            shuffle($queue);
        }

        $nextid = array_shift($queue);
        $attempt->{$field} = implode(',', $queue);

        return (string) $pool[$nextid]->responsetext;
    }
}
