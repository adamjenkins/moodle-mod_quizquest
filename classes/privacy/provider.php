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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection the collection to add to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizquest_attempts', [
            'userid' => 'privacy:metadata:quizquest_attempts:userid',
            'status' => 'privacy:metadata:quizquest_attempts:status',
            'stepstally' => 'privacy:metadata:quizquest_attempts:stepstally',
            'timecreated' => 'privacy:metadata:quizquest_attempts:timecreated',
            'timemodified' => 'privacy:metadata:quizquest_attempts:timemodified',
            'timecompleted' => 'privacy:metadata:quizquest_attempts:timecompleted',
            'ispreview' => 'privacy:metadata:quizquest_attempts:ispreview',
            'correctpoolqueue' => 'privacy:metadata:quizquest_attempts:correctpoolqueue',
            'incorrectpoolqueue' => 'privacy:metadata:quizquest_attempts:incorrectpoolqueue',
        ], 'privacy:metadata:quizquest_attempts');

        $collection->add_database_table('quizquest_responses', [
            'questionid' => 'privacy:metadata:quizquest_responses:questionid',
            'response' => 'privacy:metadata:quizquest_responses:response',
            'iscorrect' => 'privacy:metadata:quizquest_responses:iscorrect',
            'stepchange' => 'privacy:metadata:quizquest_responses:stepchange',
            'timecreated' => 'privacy:metadata:quizquest_responses:timecreated',
            'feedbacktext' => 'privacy:metadata:quizquest_responses:feedbacktext',
            'stepmsgbefore' => 'privacy:metadata:quizquest_responses:stepmsgbefore',
            'stepmsgafter' => 'privacy:metadata:quizquest_responses:stepmsgafter',
        ], 'privacy:metadata:quizquest_responses');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * @param int $userid the user id
     * @return contextlist the list of contexts
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modulelevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quizquest} q ON q.id = cm.instance
                  JOIN {quizquest_attempts} qa ON qa.quizquest = q.id
                 WHERE qa.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'modulelevel' => CONTEXT_MODULE,
            'modname' => 'quizquest',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the list of users who have personal data within the specified context.
     *
     * @param userlist $userlist the userlist to add to
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT qa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {quizquest} q ON q.id = cm.instance
                  JOIN {quizquest_attempts} qa ON qa.quizquest = q.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'quizquest',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Export all personal data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to export for
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quizquest', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Export the generic activity data (name, intro, ...).
            $contextdata = helper::get_context_data($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
            helper::export_context_files($context, $user);

            $attempts = $DB->get_records(
                'quizquest_attempts',
                ['quizquest' => $cm->instance, 'userid' => $user->id],
                'timecreated ASC'
            );

            $number = 0;
            foreach ($attempts as $attempt) {
                $number++;
                $subcontext = [get_string('attempt', 'mod_quizquest', $number)];

                $responses = $DB->get_records(
                    'quizquest_responses',
                    ['attemptid' => $attempt->id],
                    'timecreated ASC'
                );

                $data = (object) [
                    'status' => $attempt->status,
                    'stepstally' => $attempt->stepstally,
                    'timecreated' => transform::datetime($attempt->timecreated),
                    'timemodified' => transform::datetime($attempt->timemodified),
                    'timecompleted' => $attempt->timecompleted ? transform::datetime($attempt->timecompleted) : null,
                    'responses' => array_values(array_map(static function ($response) {
                        return (object) [
                            'questionid' => $response->questionid,
                            'response' => $response->response,
                            'iscorrect' => $response->iscorrect === null ? null : transform::yesno($response->iscorrect),
                            'stepchange' => $response->stepchange,
                            'timecreated' => transform::datetime($response->timecreated),
                            'feedbacktext' => $response->feedbacktext,
                            'stepmsgbefore' => $response->stepmsgbefore,
                            'stepmsgafter' => $response->stepmsgafter,
                        ];
                    }, $responses)),
                ];

                writer::with_context($context)->export_data($subcontext, $data);
            }
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context the context to delete for
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quizquest', $context->instanceid);
        if (!$cm) {
            return;
        }

        $attemptids = $DB->get_fieldset_select('quizquest_attempts', 'id', 'quizquest = ?', [$cm->instance]);
        if ($attemptids) {
            $DB->delete_records_list('quizquest_responses', 'attemptid', $attemptids);
        }
        $DB->delete_records('quizquest_attempts', ['quizquest' => $cm->instance]);
    }

    /**
     * Delete all personal data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to delete for
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('quizquest', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_attempts($cm->instance, [$userid]);
        }
    }

    /**
     * Delete personal data for multiple users in the specified context.
     *
     * @param approved_userlist $userlist the approved users to delete for
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('quizquest', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_attempts($cm->instance, $userlist->get_userids());
    }

    /**
     * Delete all attempts (and their responses) for the given users in a quizquest instance.
     *
     * @param int $quizquestid quizquest instance id
     * @param int[] $userids user ids to delete
     */
    protected static function delete_attempts(int $quizquestid, array $userids): void {
        global $DB;

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['quizquest' => $quizquestid] + $inparams;

        $attemptids = $DB->get_fieldset_select(
            'quizquest_attempts',
            'id',
            "quizquest = :quizquest AND userid $insql",
            $params
        );
        if ($attemptids) {
            $DB->delete_records_list('quizquest_responses', 'attemptid', $attemptids);
            $DB->delete_records_list('quizquest_attempts', 'id', $attemptids);
        }
    }
}
