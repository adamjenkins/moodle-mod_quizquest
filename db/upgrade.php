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
 * Upgrade steps for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_quizquest upgrade steps between the current and new version.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_quizquest_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071102) {
        $table = new xmldb_table('quizquest');

        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'wrongpenalty');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026071102, 'quizquest');
    }

    if ($oldversion < 2026071104) {
        // Custom messages: step-triggered narrative text and shuffled generic response pools.
        $table = new xmldb_table('quizquest_stepmessages');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizquest', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('step', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('textbefore', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('textafter', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizquest', XMLDB_KEY_FOREIGN, ['quizquest'], 'quizquest', ['id']);
            $table->add_index('quizquest_step', XMLDB_INDEX_UNIQUE, ['quizquest', 'step']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('quizquest_genericresponses');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('quizquest', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('responsetype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('responsetext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('quizquest', XMLDB_KEY_FOREIGN, ['quizquest'], 'quizquest', ['id']);
            $table->add_index('quizquest_responsetype', XMLDB_INDEX_NOTUNIQUE, ['quizquest', 'responsetype']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('quizquest_attempts');

        $field = new xmldb_field('correctpoolqueue', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ispreview');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('incorrectpoolqueue', XMLDB_TYPE_TEXT, null, null, null, null, null, 'correctpoolqueue');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('quizquest_responses');

        $field = new xmldb_field('feedbacktext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('stepmsgbefore', XMLDB_TYPE_TEXT, null, null, null, null, null, 'feedbacktext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('stepmsgafter', XMLDB_TYPE_TEXT, null, null, null, null, null, 'stepmsgbefore');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026071104, 'quizquest');
    }

    if ($oldversion < 2026071105) {
        $table = new xmldb_table('quizquest');

        $field = new xmldb_field(
            'includesubcategories',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'questioncategoryid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026071105, 'quizquest');
    }

    if ($oldversion < 2026071400) {
        $table = new xmldb_table('quizquest');

        $field = new xmldb_field(
            'genericresponsedisplay',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'wrongpenalty'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026071400, 'quizquest');
    }

    return true;
}
