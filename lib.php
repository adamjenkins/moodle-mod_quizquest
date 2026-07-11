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
 * Library of interface functions for module quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var int Maximum number of progress images that may be uploaded per activity. */
define('QUIZQUEST_MAX_PROGRESS_IMAGES', 20);

/** @var string Calendar event type for the open date. */
define('QUIZQUEST_EVENT_TYPE_OPEN', 'open');

/** @var string Calendar event type for the close date. */
define('QUIZQUEST_EVENT_TYPE_CLOSE', 'close');

/**
 * Returns the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function quizquest_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO             => true,
        FEATURE_SHOW_DESCRIPTION      => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES  => true,
        FEATURE_GRADE_HAS_GRADE       => true,
        FEATURE_BACKUP_MOODLE2        => true,
        FEATURE_MOD_PURPOSE           => MOD_PURPOSE_ASSESSMENT,
        default                       => null,
    };
}

/**
 * Populates cached course-module info, including the custom completion rule.
 *
 * There is no per-activity teacher toggle for the "complete the quest" rule
 * (it always applies once automatic completion tracking is enabled), so it is
 * unconditionally exposed here for \mod_quizquest\completion\custom_completion
 * to evaluate.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function quizquest_get_coursemodule_info($coursemodule) {
    global $DB;

    $quizquest = $DB->get_record('quizquest', ['id' => $coursemodule->instance], 'id, name, timeopen, timeclose');
    if (!$quizquest) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $quizquest->name;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completioncompleted'] = 1;
    }

    // Populate the open/close dates for the \mod_quizquest\dates provider (course page and activity header).
    if ($quizquest->timeopen) {
        $info->customdata['timeopen'] = $quizquest->timeopen;
    }
    if ($quizquest->timeclose) {
        $info->customdata['timeclose'] = $quizquest->timeclose;
    }

    return $info;
}

/**
 * Add a new quizquest instance.
 *
 * @param stdClass $data form data
 * @param mod_quizquest_mod_form|null $mform the form
 * @return int new instance id
 */
function quizquest_add_instance(stdClass $data, ?mod_quizquest_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->id = $DB->insert_record('quizquest', $data);

    quizquest_grade_item_update($data);
    quizquest_save_progress_images($data);
    quizquest_save_stepmessages($data);
    quizquest_save_genericresponses($data);
    quizquest_update_events($data);

    return $data->id;
}

/**
 * Update an existing quizquest instance.
 *
 * @param stdClass $data form data
 * @param mod_quizquest_mod_form|null $mform the form
 * @return bool
 */
function quizquest_update_instance(stdClass $data, ?mod_quizquest_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('quizquest', $data);

    quizquest_grade_item_update($data);
    quizquest_save_progress_images($data);
    quizquest_save_stepmessages($data);
    quizquest_save_genericresponses($data);
    quizquest_update_events($data);

    return true;
}

/**
 * Delete a quizquest instance and all associated data.
 *
 * @param int $id instance id
 * @return bool
 */
function quizquest_delete_instance($id): bool {
    global $DB;

    $quizquest = $DB->get_record('quizquest', ['id' => $id], '*', MUST_EXIST);

    $attemptids = $DB->get_fieldset_select('quizquest_attempts', 'id', 'quizquest = ?', [$quizquest->id]);
    if ($attemptids) {
        $DB->delete_records_list('quizquest_responses', 'attemptid', $attemptids);
    }
    $DB->delete_records('quizquest_attempts', ['quizquest' => $quizquest->id]);
    $DB->delete_records('quizquest_stepmessages', ['quizquest' => $quizquest->id]);
    $DB->delete_records('quizquest_genericresponses', ['quizquest' => $quizquest->id]);
    $DB->delete_records('event', ['modulename' => 'quizquest', 'instance' => $quizquest->id]);

    quizquest_grade_item_delete($quizquest);

    $DB->delete_records('quizquest', ['id' => $quizquest->id]);

    return true;
}

/**
 * Creates, updates, or deletes the open/close calendar events for an activity instance.
 *
 * Mirrors quiz_update_events() (without the override handling quiz needs):
 * separate "opens" and "closes" events, with the close event acting as the
 * action event that drives the timeline block.
 *
 * @param stdClass $quizquest The activity record (form data or DB record)
 */
function quizquest_update_events(stdClass $quizquest): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');

    $oldevents = $DB->get_records('event', ['modulename' => 'quizquest', 'instance' => $quizquest->id], 'id ASC');

    if (!empty($quizquest->coursemodule)) {
        $cmid = $quizquest->coursemodule;
    } else {
        $cmid = get_coursemodule_from_instance('quizquest', $quizquest->id, $quizquest->course)->id;
    }

    $event = new stdClass();
    $event->description  = format_module_intro('quizquest', $quizquest, $cmid, false);
    $event->format       = FORMAT_HTML;
    $event->courseid     = $quizquest->course;
    $event->groupid      = 0;
    $event->userid       = 0;
    $event->modulename   = 'quizquest';
    $event->instance     = $quizquest->id;
    $event->timeduration = 0;
    $event->visible      = instance_is_visible('quizquest', $quizquest);
    $event->priority     = null;

    if (!empty($quizquest->timeopen)) {
        if ($oldevent = array_shift($oldevents)) {
            $event->id = $oldevent->id;
        } else {
            unset($event->id);
        }
        $event->name      = get_string('calendareventopens', 'mod_quizquest', $quizquest->name);
        // The open event only drives the timeline when there is no close event.
        $event->type      = empty($quizquest->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->timestart = $quizquest->timeopen;
        $event->timesort  = $quizquest->timeopen;
        $event->eventtype = QUIZQUEST_EVENT_TYPE_OPEN;
        calendar_event::create($event, false);
    }

    if (!empty($quizquest->timeclose)) {
        if ($oldevent = array_shift($oldevents)) {
            $event->id = $oldevent->id;
        } else {
            unset($event->id);
        }
        $event->name      = get_string('calendareventcloses', 'mod_quizquest', $quizquest->name);
        $event->type      = CALENDAR_EVENT_TYPE_ACTION;
        $event->timestart = $quizquest->timeclose;
        $event->timesort  = $quizquest->timeclose;
        $event->eventtype = QUIZQUEST_EVENT_TYPE_CLOSE;
        calendar_event::create($event, false);
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Refreshes the calendar events for one instance, one course, or the whole site.
 *
 * Standard callback used by course restore and the "Refresh calendar events" tool.
 *
 * @param int $courseid Course id, or 0 for all courses
 * @param stdClass|int|null $instance Activity instance record or id
 * @param stdClass|null $cm Unused course module (part of the callback signature)
 * @return bool
 */
function quizquest_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('quizquest', ['id' => $instance], '*', MUST_EXIST);
        }
        quizquest_update_events($instance);
        return true;
    }

    $conditions = $courseid ? ['course' => $courseid] : [];
    foreach ($DB->get_records('quizquest', $conditions) as $quizquest) {
        quizquest_update_events($quizquest);
    }
    return true;
}

/**
 * Provides the action for a quizquest calendar event (timeline / calendar blocks).
 *
 * @param calendar_event $event The calendar event
 * @param \core_calendar\action_factory $factory The action factory
 * @param int $userid User id, 0 for current user
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_quizquest_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    int $userid = 0
) {
    global $DB, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['quizquest'][$event->instance];
    $context = context_module::instance($cm->id);

    if (!has_capability('mod/quizquest:play', $context, $userid)) {
        return null;
    }
    if (!is_enrolled(context_course::instance($cm->course), $userid)) {
        // Filter out the events for teachers and admins who are not participants.
        return null;
    }

    $completion = new \completion_info($cm->get_course());
    $completiondata = $completion->get_data($cm, false, $userid);
    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $quizquest = $DB->get_record('quizquest', ['id' => $event->instance], '*', MUST_EXIST);

    // Nothing to do once the activity has closed.
    if (\mod_quizquest\attempt_manager::is_closed($quizquest)) {
        return null;
    }

    // Nothing to do once the user has completed the quest, or has no attempts left.
    $manager = new \mod_quizquest\attempt_manager();
    $hascompleted = $DB->record_exists('quizquest_attempts', [
        'quizquest' => $quizquest->id, 'userid' => $userid, 'status' => 'completed', 'ispreview' => 0,
    ]);
    if ($hascompleted) {
        return null;
    }
    if (!$manager->can_start_new_attempt($quizquest, $userid) && !$manager->get_active_attempt($quizquest->id, $userid)) {
        return null;
    }

    $actionable = empty($quizquest->timeopen) || $quizquest->timeopen <= time();

    return $factory->create_instance(
        get_string('startgame', 'mod_quizquest'),
        new \moodle_url('/mod/quizquest/view.php', ['id' => $cm->id]),
        1,
        $actionable
    );
}

/**
 * Returns the valid drag range for a quizquest calendar event in the calendar UI.
 *
 * @param calendar_event $event The calendar event being moved
 * @param stdClass $quizquest The activity record
 * @return array [min timestamp + error string | null, max timestamp + error string | null]
 */
function mod_quizquest_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $quizquest) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == QUIZQUEST_EVENT_TYPE_OPEN) {
        if (!empty($quizquest->timeclose)) {
            $maxdate = [
                $quizquest->timeclose,
                get_string('openafterclose', 'mod_quizquest'),
            ];
        }
    } else if ($event->eventtype == QUIZQUEST_EVENT_TYPE_CLOSE) {
        if (!empty($quizquest->timeopen)) {
            $mindate = [
                $quizquest->timeopen,
                get_string('closebeforeopen', 'mod_quizquest'),
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * Updates the activity's open/close date when its calendar event is dragged to a new time.
 *
 * @param calendar_event $event The calendar event that was updated
 * @param stdClass $quizquest The activity record
 */
function mod_quizquest_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $quizquest): void {
    global $DB;

    if (!in_array($event->eventtype, [QUIZQUEST_EVENT_TYPE_OPEN, QUIZQUEST_EVENT_TYPE_CLOSE])) {
        return;
    }

    if ($event->modulename != 'quizquest' || $quizquest->id != $event->instance) {
        return;
    }

    $coursemodule = get_fast_modinfo($event->courseid)->instances['quizquest'][$quizquest->id];
    $context = context_module::instance($coursemodule->id);

    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    $modified = false;
    if ($event->eventtype == QUIZQUEST_EVENT_TYPE_OPEN) {
        if ($quizquest->timeopen != $event->timestart) {
            $quizquest->timeopen = $event->timestart;
            $modified = true;
        }
    } else {
        if ($quizquest->timeclose != $event->timestart) {
            $quizquest->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $quizquest->timemodified = time();
        $DB->update_record('quizquest', $quizquest);
        quizquest_update_events($quizquest);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Save the progress images from the form's draft area into the module file area.
 *
 * @param stdClass $data form data containing coursemodule and progressimages draft item id
 */
function quizquest_save_progress_images(stdClass $data): void {
    if (empty($data->progressimages)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);
    file_save_draft_area_files($data->progressimages, $context->id, 'mod_quizquest', 'progressimage', 0, [
        'subdirs' => 0,
        'maxfiles' => QUIZQUEST_MAX_PROGRESS_IMAGES,
        'maxbytes' => get_course($data->course)->maxbytes,
        'accepted_types' => ['image'],
    ]);
}

/**
 * Replaces an activity's step-triggered narrative messages from submitted form data.
 *
 * Blank rows (no step number, or no text in either box) are ignored. Called with the
 * form's raw repeated fields: stepmsg_step[], stepmsg_before[], stepmsg_after[].
 *
 * @param stdClass $data form data containing the id and the stepmsg_* repeated arrays
 */
function quizquest_save_stepmessages(stdClass $data): void {
    global $DB;

    $DB->delete_records('quizquest_stepmessages', ['quizquest' => $data->id]);

    if (empty($data->stepmsg_step)) {
        return;
    }

    foreach ($data->stepmsg_step as $i => $rawstep) {
        $rawstep = trim((string) $rawstep);
        $before  = trim($data->stepmsg_before[$i] ?? '');
        $after   = trim($data->stepmsg_after[$i] ?? '');

        if ($rawstep === '' || !ctype_digit($rawstep) || ($before === '' && $after === '')) {
            continue;
        }

        $record             = new stdClass();
        $record->quizquest  = $data->id;
        $record->step       = (int) $rawstep;
        $record->textbefore = $before;
        $record->textafter  = $after;
        $DB->insert_record('quizquest_stepmessages', $record);
    }
}

/**
 * Replaces an activity's shuffled generic correct/incorrect response pools from submitted form data.
 *
 * Blank rows are ignored. Called with the form's raw repeated fields:
 * correctresponse_text[], incorrectresponse_text[].
 *
 * @param stdClass $data form data containing the id and the *response_text repeated arrays
 */
function quizquest_save_genericresponses(stdClass $data): void {
    global $DB;

    $DB->delete_records('quizquest_genericresponses', ['quizquest' => $data->id]);

    $pools = [
        'correct'   => $data->correctresponse_text ?? [],
        'incorrect' => $data->incorrectresponse_text ?? [],
    ];

    foreach ($pools as $type => $texts) {
        $sortorder = 0;
        foreach ($texts as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $record                = new stdClass();
            $record->quizquest     = $data->id;
            $record->responsetype  = $type;
            $record->responsetext  = $text;
            $record->sortorder     = $sortorder++;
            $DB->insert_record('quizquest_genericresponses', $record);
        }
    }
}

/**
 * Returns the URLs of the activity's progress images, ordered by file name.
 *
 * With N images, image k (0-based) is shown once the student has completed
 * k/N of the required steps.
 *
 * @param context_module $context
 * @return string[] Image URLs
 */
function quizquest_get_progress_image_urls(context_module $context): array {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_quizquest', 'progressimage', 0, 'filename', false);

    $urls = [];
    foreach ($files as $file) {
        $urls[] = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_quizquest',
            'progressimage',
            0,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }
    return $urls;
}

/**
 * Serve files from the quizquest file areas.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context $context context object
 * @param string $filearea file area name
 * @param array $args remaining path arguments
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting file serving
 * @return bool false if the file was not found
 */
function quizquest_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    if ($filearea !== 'progressimage') {
        return false;
    }

    require_login($course, true, $cm);
    require_capability('mod/quizquest:view', $context);

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_quizquest', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}

/**
 * Creates or updates the grade item in the gradebook.
 *
 * @param stdClass $quizquest The activity record
 * @param mixed $grades Optional grades to write
 * @return int Grade update status
 */
function quizquest_grade_item_update($quizquest, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($quizquest, 'cmidnumber')) {
        $item = ['itemname' => clean_param($quizquest->name, PARAM_NOTAGS), 'idnumber' => $quizquest->cmidnumber];
    } else {
        $item = ['itemname' => clean_param($quizquest->name, PARAM_NOTAGS)];
    }

    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $quizquest->grade ?? 100;
    $item['grademin']  = 0;

    if (isset($quizquest->grade) && $quizquest->grade == 0) {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/quizquest', $quizquest->course, 'mod', 'quizquest', $quizquest->id, 0, $grades, $item);
}

/**
 * Deletes the grade item from the gradebook.
 *
 * @param stdClass $quizquest The activity record
 * @return int
 */
function quizquest_grade_item_delete($quizquest) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/quizquest',
        $quizquest->course,
        'mod',
        'quizquest',
        $quizquest->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Updates the gradebook with current grades.
 *
 * @param stdClass $quizquest The activity record
 * @param int $userid Optional specific user; 0 means all users
 * @param bool $nullifnone If true, write null grade when no submission exists
 */
function quizquest_update_grades($quizquest, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($quizquest->grade == 0) {
        quizquest_grade_item_update($quizquest);
        return;
    }

    $grades = quizquest_get_user_grades($quizquest, $userid);

    if ($grades) {
        quizquest_grade_item_update($quizquest, $grades);
    } else if ($userid && $nullifnone) {
        $grade           = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        quizquest_grade_item_update($quizquest, $grade);
    } else {
        quizquest_grade_item_update($quizquest);
    }
}

/**
 * Returns grades for one or all users.
 *
 * Completed attempts always earn the full grade. Abandoned attempts earn a
 * partial grade when $quizquest->partialscoreonquit is enabled. A full grade
 * from any completed attempt takes precedence over any partial grade.
 *
 * @param stdClass $quizquest The activity record
 * @param int $userid 0 means all users
 * @return array Keyed by userid
 */
function quizquest_get_user_grades($quizquest, $userid = 0) {
    global $DB;

    // Full grade from any completed attempt.
    $params = array_merge(['quizquest' => $quizquest->id, 'status' => 'completed'], $userid ? ['userid' => $userid] : []);
    $where  = 'quizquest = :quizquest AND status = :status AND ispreview = 0' . ($userid ? ' AND userid = :userid' : '');
    $completed = $DB->get_records_select('quizquest_attempts', $where, $params, 'timecompleted ASC');
    $grades = [];

    foreach ($completed as $attempt) {
        if (!isset($grades[$attempt->userid])) {
            $grades[$attempt->userid]             = new stdClass();
            $grades[$attempt->userid]->userid     = $attempt->userid;
            $grades[$attempt->userid]->rawgrade   = (float) $quizquest->grade;
            $grades[$attempt->userid]->dategraded = $attempt->timecompleted;
        }
    }

    // Partial grade from abandoned attempts when the setting is on.
    if (!empty($quizquest->partialscoreonquit) && (int) $quizquest->steps > 0) {
        $params = array_merge(['quizquest' => $quizquest->id, 'status' => 'abandoned'], $userid ? ['userid' => $userid] : []);
        $where  = 'quizquest = :quizquest AND status = :status AND ispreview = 0' . ($userid ? ' AND userid = :userid' : '');
        $abandoned = $DB->get_records_select('quizquest_attempts', $where, $params, 'timecompleted ASC');

        foreach ($abandoned as $attempt) {
            // Only apply partial if user has no completed attempt (full grade wins).
            if (isset($grades[$attempt->userid]) && $grades[$attempt->userid]->rawgrade >= (float) $quizquest->grade) {
                continue;
            }
            $partial = (float) $quizquest->grade
                * min(1.0, (int) $attempt->stepstally / (int) $quizquest->steps);
            // Keep the highest partial grade across multiple abandoned attempts.
            if (!isset($grades[$attempt->userid]) || $partial > $grades[$attempt->userid]->rawgrade) {
                $grades[$attempt->userid]             = new stdClass();
                $grades[$attempt->userid]->userid     = $attempt->userid;
                $grades[$attempt->userid]->rawgrade   = $partial;
                $grades[$attempt->userid]->dategraded = $attempt->timecompleted;
            }
        }
    }

    return $grades;
}

/**
 * Adds the Quiz Quest reset option to the course-reset form.
 *
 * @param MoodleQuickForm $mform The course reset form
 */
function quizquest_reset_course_form_definition($mform) {
    $mform->addElement('header', 'quizquestheader', get_string('modulenameplural', 'mod_quizquest'));
    $mform->addElement('advcheckbox', 'reset_quizquest_attempts', get_string('resetattempts', 'mod_quizquest'));
}

/**
 * Returns default values for the course-reset form fields.
 *
 * @param stdClass $course The course object (unused)
 * @return array
 */
function quizquest_reset_course_form_defaults($course) {
    return ['reset_quizquest_attempts' => 1];
}

/**
 * Resets user data for course reset.
 *
 * @param stdClass $data Course reset form data
 * @return array Status items
 */
function quizquest_reset_userdata($data) {
    global $CFG, $DB;

    $status = [];

    if (!empty($data->reset_quizquest_attempts)) {
        require_once($CFG->libdir . '/gradelib.php');

        $quizquests = $DB->get_records('quizquest', ['course' => $data->courseid], '', 'id, course');
        $quizquestids = array_keys($quizquests);

        if ($quizquestids) {
            [$insql, $params] = $DB->get_in_or_equal($quizquestids);
            $attemptids = $DB->get_fieldset_select('quizquest_attempts', 'id', "quizquest $insql", $params);
            if ($attemptids) {
                $DB->delete_records_list('quizquest_responses', 'attemptid', $attemptids);
            }
            $DB->delete_records_select('quizquest_attempts', "quizquest $insql", $params);

            // Reset gradebook entries for all activities in the course.
            foreach ($quizquests as $quizquest) {
                grade_update(
                    'mod/quizquest',
                    $quizquest->course,
                    'mod',
                    'quizquest',
                    $quizquest->id,
                    0,
                    null,
                    ['reset' => 1]
                );
            }
        }

        $status[] = [
            'component' => get_string('modulenameplural', 'mod_quizquest'),
            'item'      => get_string('attempts', 'mod_quizquest'),
            'error'     => false,
        ];
    }

    // Shift the open/close dates when the reset requests a date shift.
    // shift_course_mod_dates() only rewrites the date columns, so the open/close
    // calendar events must be rebuilt for the shifted dates afterwards.
    if (!empty($data->timeshift)) {
        $shifted = shift_course_mod_dates('quizquest', ['timeopen', 'timeclose'], $data->timeshift, $data->courseid);
        quizquest_refresh_events($data->courseid);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_quizquest'),
            'item'      => get_string('openclosedatesupdated', 'mod_quizquest'),
            'error'     => !$shifted,
        ];
    }

    return $status;
}
