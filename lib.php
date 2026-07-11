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
        FEATURE_BACKUP_MOODLE2        => false,
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

    $quizquest = $DB->get_record('quizquest', ['id' => $coursemodule->instance], 'id, name');
    if (!$quizquest) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $quizquest->name;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completioncompleted'] = 1;
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

    quizquest_grade_item_delete($quizquest);

    $DB->delete_records('quizquest', ['id' => $quizquest->id]);

    return true;
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
        'accepted_types' => ['image'],
    ]);
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

    return $status;
}
