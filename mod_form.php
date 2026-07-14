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
 * Activity settings form for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/quizquest/lib.php');

/**
 * Settings form for the Quiz Quest activity.
 *
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quizquest_mod_form extends moodleform_mod {
    /**
     * Define the form elements.
     */
    public function definition() {
        global $COURSE, $PAGE;

        $mform = $this->_form;

        $PAGE->requires->js_call_amd('mod_quizquest/bankpicker', 'init');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('quizquestname', 'mod_quizquest'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'timing', get_string('timing', 'mod_quizquest'));

        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('questopen', 'mod_quizquest'),
            ['optional' => true]
        );
        $mform->addHelpButton('timeopen', 'questopenclose', 'mod_quizquest');

        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('questclose', 'mod_quizquest'),
            ['optional' => true]
        );

        $mform->addElement('header', 'gamesettings', get_string('gamesettings', 'mod_quizquest'));

        $questionbankoptions = $this->get_question_bank_options();

        $defaultbankcontextid = 0;
        // On submission, render the categories of the bank the teacher actually chose:
        // formslib only exports select values that are among the server-registered
        // options, so the options swapped in client-side by bankpicker.js must be
        // re-registered here or the submitted category is silently discarded.
        $submittedbankcontextid = optional_param('questionbank', 0, PARAM_INT);
        if ($submittedbankcontextid && array_key_exists($submittedbankcontextid, $questionbankoptions)) {
            $defaultbankcontextid = $submittedbankcontextid;
        }
        if (!$defaultbankcontextid && $this->current->instance && !empty($this->current->questioncategoryid)) {
            $parts = explode(',', (string) $this->current->questioncategoryid);
            $defaultbankcontextid = (int) ($parts[1] ?? 0);
        }
        if (!$defaultbankcontextid) {
            $candidates = $questionbankoptions;
            unset($candidates[0]);
            $firstkey = array_key_first($candidates);
            $defaultbankcontextid = $firstkey !== null ? (int) $firstkey : 0;
        }

        $mform->addElement(
            'select',
            'questionbank',
            get_string('questionbank', 'mod_quizquest'),
            $questionbankoptions,
            ['data-courseid' => $COURSE->id]
        );
        $mform->addHelpButton('questionbank', 'questionbank', 'mod_quizquest');
        $mform->addRule('questionbank', null, 'required', null, 'client');
        $mform->setDefault('questionbank', $defaultbankcontextid);

        // The default bank's categories are rendered server-side so the form works without
        // JS; amd/src/bankpicker.js repopulates this select when the bank choice changes.
        $categoryoptions = $defaultbankcontextid ? $this->get_category_options_for_context($defaultbankcontextid) : [];
        $mform->addElement(
            'select',
            'questioncategoryid',
            get_string('questioncategory', 'mod_quizquest'),
            $categoryoptions
        );
        $mform->addHelpButton('questioncategoryid', 'questioncategory', 'mod_quizquest');
        $mform->addRule('questioncategoryid', null, 'required', null, 'client');

        $mform->addElement('selectyesno', 'includesubcategories', get_string('includesubcategories', 'mod_quizquest'));
        $mform->setDefault('includesubcategories', 0);
        $mform->addHelpButton('includesubcategories', 'includesubcategories', 'mod_quizquest');

        $mform->addElement('text', 'steps', get_string('steps', 'mod_quizquest'), ['size' => 4]);
        $mform->setType('steps', PARAM_INT);
        $mform->setDefault('steps', 10);
        $mform->addHelpButton('steps', 'steps', 'mod_quizquest');

        $attemptoptions = [-1 => get_string('unlimited', 'mod_quizquest')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_quizquest'), $attemptoptions);
        $mform->setDefault('maxattempts', -1);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_quizquest');

        $mform->addElement('selectyesno', 'wrongpenalty', get_string('wrongpenalty', 'mod_quizquest'));
        $mform->setDefault('wrongpenalty', 0);
        $mform->addHelpButton('wrongpenalty', 'wrongpenalty', 'mod_quizquest');

        $mform->addElement('selectyesno', 'showprogress', get_string('showprogress', 'mod_quizquest'));
        $mform->setDefault('showprogress', 1);
        $mform->addHelpButton('showprogress', 'showprogress', 'mod_quizquest');

        $mform->addElement('selectyesno', 'allowstudentreview', get_string('allowstudentreview', 'mod_quizquest'));
        $mform->setDefault('allowstudentreview', 0);
        $mform->addHelpButton('allowstudentreview', 'allowstudentreview', 'mod_quizquest');

        $mform->addElement('selectyesno', 'partialscoreonquit', get_string('partialscoreonquit', 'mod_quizquest'));
        $mform->setDefault('partialscoreonquit', 0);
        $mform->addHelpButton('partialscoreonquit', 'partialscoreonquit', 'mod_quizquest');

        $mform->addElement('filemanager', 'progressimages', get_string('progressimages', 'mod_quizquest'), null, [
            'subdirs' => 0,
            'maxfiles' => QUIZQUEST_MAX_PROGRESS_IMAGES,
            'maxbytes' => $COURSE->maxbytes,
            'accepted_types' => ['image'],
        ]);
        $mform->addHelpButton('progressimages', 'progressimages', 'mod_quizquest');

        $this->definition_stepmessages();
        $this->definition_genericresponses();

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds the repeatable "step message" fields: narrative text inserted before/after
     * feedback when an attempt's step tally reaches a configured value.
     */
    protected function definition_stepmessages(): void {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'stepmessagesheader', get_string('stepmessages', 'mod_quizquest'));
        $mform->addElement('static', 'stepmessagesinfo', '', get_string('stepmessages_help', 'mod_quizquest'));

        $repeatarray = [
            $mform->createElement('text', 'stepmsg_step', get_string('stepnumber', 'mod_quizquest'), ['size' => 4]),
            $mform->createElement(
                'textarea',
                'stepmsg_before',
                get_string('textbeforefeedback', 'mod_quizquest'),
                ['rows' => 2, 'cols' => 50]
            ),
            $mform->createElement(
                'textarea',
                'stepmsg_after',
                get_string('textafterfeedback', 'mod_quizquest'),
                ['rows' => 2, 'cols' => 50]
            ),
        ];
        $repeateloptions = [
            'stepmsg_step'   => ['type' => PARAM_TEXT],
            'stepmsg_before' => ['type' => PARAM_TEXT],
            'stepmsg_after'  => ['type' => PARAM_TEXT],
        ];

        $existingcount = $this->current->instance
            ? $DB->count_records('quizquest_stepmessages', ['quizquest' => $this->current->instance])
            : 0;

        $this->repeat_elements(
            $repeatarray,
            max($existingcount + 2, 3),
            $repeateloptions,
            'stepmsg_repeats',
            'stepmsg_add_fields',
            1,
            get_string('addstepmessage', 'mod_quizquest'),
            true
        );
    }

    /**
     * Adds the repeatable "generic response" fields: shuffled pools of fallback
     * correct/incorrect feedback, used when the matched question answer has none of its own.
     */
    protected function definition_genericresponses(): void {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'genericresponsesheader', get_string('genericresponses', 'mod_quizquest'));
        $mform->addElement('static', 'genericresponsesinfo', '', get_string('genericresponses_help', 'mod_quizquest'));

        $existingcorrect = $this->current->instance
            ? $DB->count_records(
                'quizquest_genericresponses',
                ['quizquest' => $this->current->instance, 'responsetype' => 'correct']
            )
            : 0;
        $correctrepeatarray = [
            $mform->createElement(
                'textarea',
                'correctresponse_text',
                get_string('correctresponses', 'mod_quizquest'),
                ['rows' => 2, 'cols' => 60]
            ),
        ];
        $this->repeat_elements(
            $correctrepeatarray,
            max($existingcorrect + 2, 3),
            ['correctresponse_text' => ['type' => PARAM_TEXT]],
            'correctresponse_repeats',
            'correctresponse_add_fields',
            1,
            get_string('addcorrectresponse', 'mod_quizquest'),
            true
        );

        $existingincorrect = $this->current->instance
            ? $DB->count_records(
                'quizquest_genericresponses',
                ['quizquest' => $this->current->instance, 'responsetype' => 'incorrect']
            )
            : 0;
        $incorrectrepeatarray = [
            $mform->createElement(
                'textarea',
                'incorrectresponse_text',
                get_string('incorrectresponses', 'mod_quizquest'),
                ['rows' => 2, 'cols' => 60]
            ),
        ];
        $this->repeat_elements(
            $incorrectrepeatarray,
            max($existingincorrect + 2, 3),
            ['incorrectresponse_text' => ['type' => PARAM_TEXT]],
            'incorrectresponse_repeats',
            'incorrectresponse_add_fields',
            1,
            get_string('addincorrectresponse', 'mod_quizquest'),
            true
        );
    }

    /**
     * Prepare existing data (progress images draft area, step messages, generic
     * response pools) before the form is rendered.
     *
     * @param array $defaultvalues form default values
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        global $COURSE, $DB;

        $draftitemid = file_get_submitted_draft_itemid('progressimages');
        if ($this->current->instance) {
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_quizquest', 'progressimage', 0, [
                'subdirs' => 0,
                'maxfiles' => QUIZQUEST_MAX_PROGRESS_IMAGES,
                'maxbytes' => $COURSE->maxbytes,
            ]);
        }
        $defaultvalues['progressimages'] = $draftitemid;

        if (!$this->current->instance) {
            return;
        }

        $stepmessages = $DB->get_records('quizquest_stepmessages', ['quizquest' => $this->current->instance], 'step ASC');
        $i = 0;
        foreach ($stepmessages as $stepmessage) {
            $defaultvalues['stepmsg_step'][$i]   = $stepmessage->step;
            $defaultvalues['stepmsg_before'][$i] = $stepmessage->textbefore;
            $defaultvalues['stepmsg_after'][$i]  = $stepmessage->textafter;
            $i++;
        }

        $correct = $DB->get_records(
            'quizquest_genericresponses',
            ['quizquest' => $this->current->instance, 'responsetype' => 'correct'],
            'sortorder ASC, id ASC'
        );
        $i = 0;
        foreach ($correct as $response) {
            $defaultvalues['correctresponse_text'][$i] = $response->responsetext;
            $i++;
        }

        $incorrect = $DB->get_records(
            'quizquest_genericresponses',
            ['quizquest' => $this->current->instance, 'responsetype' => 'incorrect'],
            'sortorder ASC, id ASC'
        );
        $i = 0;
        foreach ($incorrect as $response) {
            $defaultvalues['incorrectresponse_text'][$i] = $response->responsetext;
            $i++;
        }
    }

    /**
     * Validate the form data.
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array of errors keyed by element name
     */
    public function validation($data, $files) {
        global $COURSE;

        $errors = parent::validation($data, $files);

        $steps = (int) ($data['steps'] ?? 0);
        if ($steps < 1 || $steps > 100) {
            $errors['steps'] = get_string('error:stepsinvalid', 'mod_quizquest');
        }

        // Scales are stored as negative ids in the integer grade column, which the
        // point-based grading logic cannot interpret; only points (or none) work.
        if (isset($data['grade']) && (int) $data['grade'] < 0) {
            $errors['grade'] = get_string('error:gradescalenotsupported', 'mod_quizquest');
        }

        // Reject a category from a bank this user isn't authorised to use — the client-side
        // choices aren't authoritative, so re-check server-side against the same source the
        // form options were built from.
        $rawcategory = (string) ($data['questioncategoryid'] ?? '');
        $categoryid = \mod_quizquest\question_picker::parse_category($rawcategory);
        $bankcontextid = (int) (explode(',', $rawcategory)[1] ?? 0);
        $allowedbanks = \mod_quizquest\question_bank_lister::get_available_banks($COURSE->id);

        if (!$categoryid || !array_key_exists($bankcontextid, $allowedbanks)) {
            $errors['questioncategoryid'] = get_string('error:noquestionsincategory', 'mod_quizquest');
        } else {
            // The chosen category must contain at least one question this activity can ask.
            $includesubcategories = !empty($data['includesubcategories']);
            if (!\mod_quizquest\question_picker::get_eligible_question_ids($categoryid, $includesubcategories)) {
                $errors['questioncategoryid'] = get_string('error:noquestionsincategory', 'mod_quizquest');
            }
        }

        // Check open and close times are consistent.
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'mod_quizquest');
        }

        // Step messages: step numbers must be valid, within range, unique, and have some text.
        $seensteps = [];
        foreach ($data['stepmsg_step'] ?? [] as $i => $rawstep) {
            $rawstep = trim((string) $rawstep);
            $before  = trim($data['stepmsg_before'][$i] ?? '');
            $after   = trim($data['stepmsg_after'][$i] ?? '');

            if ($rawstep === '' && $before === '' && $after === '') {
                continue;
            }

            if ($rawstep === '' || !ctype_digit($rawstep) || (int) $rawstep > $steps) {
                $errors["stepmsg_step[$i]"] = get_string('error:stepmessagestepinvalid', 'mod_quizquest');
                continue;
            }

            if ($before === '' && $after === '') {
                $errors["stepmsg_before[$i]"] = get_string('error:stepmessageempty', 'mod_quizquest');
                continue;
            }

            $step = (int) $rawstep;
            if (isset($seensteps[$step])) {
                $errors["stepmsg_step[$i]"] = get_string('error:stepmessageduplicate', 'mod_quizquest');
                continue;
            }
            $seensteps[$step] = true;
        }

        return $errors;
    }

    /**
     * Build the question bank options: the legacy course-context category, any question
     * bank activities in this course, and any banks shared to other courses or site-wide
     * that the current user is authorised to use.
     *
     * @return array<int, string> select options, keyed by context id, with a leading prompt
     */
    protected function get_question_bank_options(): array {
        global $COURSE;

        return [0 => get_string('choosedots')] + \mod_quizquest\question_bank_lister::get_available_banks($COURSE->id);
    }

    /**
     * Build the category options for a single question bank context.
     *
     * Values are stored as "categoryid,contextid" (the same convention used by
     * qbank_managecategories::question_category_options()), flattened from its
     * per-context grouping since only one context is ever passed in here.
     *
     * @param int $contextid
     * @return array<string, string> select options
     */
    protected function get_category_options_for_context(int $contextid): array {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        // Question categories only live in module (qbank) contexts since Moodle 5.0.
        // Instances saved before 0.4.1 may still reference a course context here;
        // returning no options makes the teacher re-pick instead of crashing the form.
        if (!$context || $context->contextlevel != CONTEXT_MODULE) {
            return [];
        }

        $grouped = \qbank_managecategories\helper::question_category_options([$context], false, 0, false, -1, true);

        $flat = [];
        foreach ($grouped as $group) {
            foreach ($group as $value => $label) {
                $flat[$value] = $label;
            }
        }
        return $flat;
    }
}
