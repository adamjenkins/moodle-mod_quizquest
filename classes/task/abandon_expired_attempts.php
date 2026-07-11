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

namespace mod_quizquest\task;

use mod_quizquest\attempt_manager;

/**
 * Scheduled task that abandons in-progress attempts whose activity has closed.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class abandon_expired_attempts extends \core\task\scheduled_task {
    /**
     * Returns the task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskabandonexpired', 'mod_quizquest');
    }

    /**
     * Abandons every in-progress attempt belonging to an activity whose close date has passed.
     */
    public function execute(): void {
        global $DB;

        $attempts = $DB->get_recordset_sql(
            "SELECT a.*
               FROM {quizquest_attempts} a
               JOIN {quizquest} q ON q.id = a.quizquest
              WHERE a.status = 'inprogress' AND q.timeclose > 0 AND q.timeclose < :now",
            ['now' => time()]
        );

        $manager = new attempt_manager();
        $activities = [];
        $count = 0;
        foreach ($attempts as $attempt) {
            if (!isset($activities[$attempt->quizquest])) {
                $activities[$attempt->quizquest] = $DB->get_record('quizquest', ['id' => $attempt->quizquest]);
            }
            if ($manager->abandon_expired_attempt($attempt, $activities[$attempt->quizquest])) {
                $count++;
            }
        }
        $attempts->close();

        mtrace("mod_quizquest: abandoned $count expired attempt(s).");
    }
}
