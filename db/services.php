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
 * Web service definitions for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_quizquest_start_attempt' => [
        'classname'     => 'mod_quizquest\external\start_attempt',
        'methodname'    => 'execute',
        'description'   => 'Start or resume a Quiz Quest attempt.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_quizquest_submit_answer' => [
        'classname'     => 'mod_quizquest\external\submit_answer',
        'methodname'    => 'execute',
        'description'   => 'Submit an answer to the current question and receive the next question.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_quizquest_quit_attempt' => [
        'classname'     => 'mod_quizquest\external\quit_attempt',
        'methodname'    => 'execute',
        'description'   => 'Abandon an in-progress attempt, optionally awarding a partial grade.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'mod_quizquest_get_bank_categories' => [
        'classname'     => 'mod_quizquest\external\get_bank_categories',
        'methodname'    => 'execute',
        'description'   => 'List the question categories in a chosen question bank, for the settings form.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
