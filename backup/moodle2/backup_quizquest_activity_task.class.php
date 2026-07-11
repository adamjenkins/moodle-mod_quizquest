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

/**
 * Backup task for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quizquest/backup/moodle2/backup_quizquest_stepslib.php');

/**
 * Provides the steps to perform a complete backup of a quizquest instance.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizquest_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines the single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_quizquest_activity_structure_step('quizquest_structure', 'quizquest.xml'));
    }

    /**
     * Encodes URLs to quizquest scripts into portable placeholders.
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/({$base}\/mod\/quizquest\/index\.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@QUIZQUESTINDEX*$2@$', $content);

        $search = "/({$base}\/mod\/quizquest\/view\.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@QUIZQUESTVIEWBYID*$2@$', $content);

        return $content;
    }
}
