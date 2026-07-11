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
 * Main view page for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quizquest/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'quizquest');
$quizquest = $DB->get_record('quizquest', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quizquest:view', $context);

// Log module viewed event.
$event = \mod_quizquest\event\course_module_viewed::create([
    'objectid' => $quizquest->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('quizquest', $quizquest);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/quizquest/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($quizquest->name, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

echo $OUTPUT->header();
echo html_writer::start_div('quizquest-page');
echo $OUTPUT->heading(format_string($quizquest->name, true, ['context' => $context]), 2);

// Note: the activity description is already rendered once by core's activity
// header inside $OUTPUT->header() above; do not print $quizquest->intro again here.

// Teacher view: show attempt stats.
if (has_capability('mod/quizquest:viewreports', $context)) {
    $totalattempts = $DB->count_records('quizquest_attempts', ['quizquest' => $quizquest->id, 'ispreview' => 0]);
    $completedcount = $DB->count_records(
        'quizquest_attempts',
        ['quizquest' => $quizquest->id, 'status' => 'completed', 'ispreview' => 0]
    );

    echo $OUTPUT->box_start('generalbox');
    echo html_writer::tag('p', get_string('attempts', 'mod_quizquest') . ': ' . $totalattempts);
    echo html_writer::tag('p', get_string('completed', 'mod_quizquest') . ': ' . $completedcount);
    echo $OUTPUT->box_end();
}

// Student view.
if (!has_capability('mod/quizquest:play', $context)) {
    echo $OUTPUT->notification(get_string('error:nopermission', 'mod_quizquest'), 'error');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Check attempt availability.
$atman = new \mod_quizquest\attempt_manager();
$ispreview = has_capability('mod/quizquest:viewreports', $context);

// Enforce the open/close window for students; previewing users may play any time.
if (!$ispreview) {
    if (!empty($quizquest->timeopen) && time() < $quizquest->timeopen) {
        echo $OUTPUT->notification(
            get_string('error:notopenyet', 'mod_quizquest', userdate($quizquest->timeopen)),
            'info'
        );
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
    if (\mod_quizquest\attempt_manager::is_closed($quizquest)) {
        // Finalise any attempt the student still has open.
        if ($activeattempt = $atman->get_active_attempt($quizquest->id, $USER->id)) {
            $atman->abandon_attempt($activeattempt, $quizquest, $course, $cm);
        }
        echo $OUTPUT->notification(
            get_string('error:closedon', 'mod_quizquest', userdate($quizquest->timeclose)),
            'warning'
        );
        if ($quizquest->allowstudentreview && has_capability('mod/quizquest:viewownattempts', $context)) {
            $myurl = new moodle_url('/mod/quizquest/myattempts.php', ['id' => $cm->id]);
            echo html_writer::link($myurl, get_string('viewattempts', 'mod_quizquest'), ['class' => 'btn btn-secondary mt-2']);
        }
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }
}

$canstart = $atman->can_start_new_attempt($quizquest, $USER->id, $ispreview);
$activeattempt = $atman->get_active_attempt($quizquest->id, $USER->id, $ispreview);

if (!$canstart && !$activeattempt) {
    echo $OUTPUT->notification(get_string('error:maxattemptsreached', 'mod_quizquest'), 'warning');

    if ($quizquest->allowstudentreview && has_capability('mod/quizquest:viewownattempts', $context)) {
        $myurl = new moodle_url('/mod/quizquest/myattempts.php', ['id' => $cm->id]);
        echo html_writer::link($myurl, get_string('viewattempts', 'mod_quizquest'), ['class' => 'btn btn-secondary mt-2']);
    }

    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Determine start/continue button label.
$startlabel = $activeattempt
    ? get_string('resumegame', 'mod_quizquest')
    : get_string('startgame', 'mod_quizquest');

// Progress images, ordered by file name.
$imageurls = quizquest_get_progress_image_urls($context);

// Render the game interface.
$templatecontext = [
    'cmid'            => $cm->id,
    'showprogress'    => (bool) $quizquest->showprogress,
    'steps'           => (int) $quizquest->steps,
    'sendlabel'       => get_string('sendanswer', 'mod_quizquest'),
    'waitinglabel'    => get_string('waiting', 'mod_quizquest'),
    'progresslabel'   => get_string('progresslabel', 'mod_quizquest', ['tally' => 0, 'steps' => $quizquest->steps]),
    'placeholder'     => get_string('youranswerplaceholder', 'mod_quizquest'),
    'allowreview'     => $quizquest->allowstudentreview && has_capability('mod/quizquest:viewownattempts', $context),
    'myattemptsurl'   => (new moodle_url('/mod/quizquest/myattempts.php', ['id' => $cm->id]))->out(false),
    'myattemptslabel' => get_string('viewattempts', 'mod_quizquest'),
    'startlabel'      => $startlabel,
    'quitlabel'       => get_string('quitattempt', 'mod_quizquest'),
    'quitconfirm'     => get_string('quitattempt_confirm', 'mod_quizquest'),
    'hasimages'       => !empty($imageurls),
    'firstimage'      => $imageurls[0] ?? '',
];

echo $OUTPUT->render_from_template('mod_quizquest/view', $templatecontext);

$PAGE->requires->js_call_amd('mod_quizquest/game', 'init', [$cm->id, $imageurls]);

echo html_writer::end_div();
echo $OUTPUT->footer();
