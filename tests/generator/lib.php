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
 * Data generator for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Quiz Quest module data generator.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizquest_generator extends testing_module_generator {
    /**
     * Creates a quizquest activity instance filling in required fields with defaults.
     *
     * @param array|stdClass|null $record instance fields
     * @param array|null $options course module options
     * @return stdClass the activity record with cmid
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name'                 => 'Test quest',
            'questioncategoryid'   => '',
            'includesubcategories' => 0,
            'steps'                => 5,
            'grade'                => 100,
            'maxattempts'          => -1,
            'showprogress'         => 1,
            'allowstudentreview'   => 0,
            'partialscoreonquit'   => 0,
            'wrongpenalty'         => 0,
            'timeopen'             => 0,
            'timeclose'            => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
