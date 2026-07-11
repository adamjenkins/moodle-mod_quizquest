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
 * English language strings for mod_quizquest.
 *
 * @package    mod_quizquest
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcorrectresponse'] = 'Add another correct response';
$string['addincorrectresponse'] = 'Add another incorrect response';
$string['addstepmessage'] = 'Add another step message';
$string['allowstudentreview'] = 'Allow student review';
$string['allowstudentreview_help'] = 'Let students re-read the questions and answers of their completed attempts.';
$string['attempt'] = 'Attempt {$a}';
$string['attemptabandoned'] = 'You quit this attempt.';
$string['attemptcompleted'] = 'Congratulations!';
$string['attemptcompletedmessage'] = 'You completed the quest.';
$string['attempts'] = 'Attempts';
$string['attemptstarted'] = 'Started';
$string['backtolist'] = 'Back to attempt list';
$string['calendareventcloses'] = '{$a} closes';
$string['calendareventopens'] = '{$a} opens';
$string['chatlog'] = 'Quest dialogue';
$string['closebeforeopen'] = 'You have specified a close date before the open date.';
$string['completed'] = 'Completed';
$string['completiondetail:completed'] = 'Complete the quest';
$string['correctresponses'] = 'Correct-answer responses';
$string['enterfullscreen'] = 'Enter fullscreen';
$string['error:closedon'] = 'This activity closed on {$a}.';
$string['error:invalidattempt'] = 'This attempt is not in progress.';
$string['error:invalidchoice'] = 'That answer is not valid for the current question.';
$string['error:maxattemptsreached'] = 'You have used all of your attempts at this activity.';
$string['error:nopermission'] = 'You do not have permission to play this activity.';
$string['error:noquestions'] = 'No suitable questions were found in the configured question bank category.';
$string['error:noquestionsincategory'] = 'The selected category contains no multiple choice (single answer) or short answer questions.';
$string['error:notopenyet'] = 'This activity opens on {$a}.';
$string['error:stepmessageduplicate'] = 'Only one message can be configured per step.';
$string['error:stepmessageempty'] = 'Enter text before and/or after feedback, or remove this step number.';
$string['error:stepmessagestepinvalid'] = 'Step must be a whole number between 0 and the configured "Steps to complete".';
$string['error:stepsinvalid'] = 'Steps must be a number between 1 and 100.';
$string['eventattemptabandoned'] = 'Quiz Quest attempt abandoned';
$string['eventattemptcompleted'] = 'Quiz Quest attempt completed';
$string['eventattemptstarted'] = 'Quiz Quest attempt started';
$string['exitfullscreen'] = 'Exit fullscreen';
$string['feedbackcorrect'] = 'Correct! You move a step closer to your goal.';
$string['feedbackincorrect'] = 'Not quite.';
$string['gamesettings'] = 'Game settings';
$string['genericresponses'] = 'Generic responses';
$string['genericresponses_help'] = 'Optional pools of fallback feedback shown when the matched question answer has no feedback text of its own. Each turn, a random unused response is shown from the relevant pool; once every response in a pool has been shown, it reshuffles. Leave both pools empty to keep using the default "Correct!"/"Not quite" messages.';
$string['incorrectresponses'] = 'Incorrect-answer responses';
$string['maxattempts'] = 'Maximum attempts';
$string['maxattempts_help'] = 'How many attempts a student may make at this quest. Select "Unlimited" to allow any number of attempts.';
$string['modulename'] = 'Quiz Quest';
$string['modulename_help'] = 'The Quiz Quest activity presents questions from a question bank category as an interactive, escape-room style dialogue. Multiple choice questions become choice buttons; short answer questions let the student type a response. Questions are chosen at random, preferring questions the student has not seen before, and optional images change as the student progresses.';
$string['modulenameplural'] = 'Quiz Quests';
$string['myattempts'] = 'My attempts';
$string['myattemptsheading'] = 'My attempts';
$string['newattempt'] = 'Start a new attempt';
$string['noattempts'] = 'No attempts recorded yet.';
$string['openafterclose'] = 'You have specified an open date after the close date.';
$string['partialscoreonquit'] = 'Partial score on quit';
$string['partialscoreonquit_help'] = 'When a student quits an attempt early, award a grade proportional to the steps completed.';
$string['pluginadministration'] = 'Quiz Quest administration';
$string['pluginname'] = 'Quiz Quest';
$string['privacy:metadata:quizquest_attempts'] = 'Attempt records for each user of a Quiz Quest activity.';
$string['privacy:metadata:quizquest_attempts:ispreview'] = 'Whether the attempt was a teacher/manager preview.';
$string['privacy:metadata:quizquest_attempts:status'] = 'The status of the attempt (in progress, completed or abandoned).';
$string['privacy:metadata:quizquest_attempts:stepstally'] = 'The number of steps of progress made in the attempt.';
$string['privacy:metadata:quizquest_attempts:timecompleted'] = 'The time the attempt was completed.';
$string['privacy:metadata:quizquest_attempts:timecreated'] = 'The time the attempt was started.';
$string['privacy:metadata:quizquest_attempts:timemodified'] = 'The time the attempt was last updated.';
$string['privacy:metadata:quizquest_attempts:userid'] = 'The ID of the user making the attempt.';
$string['privacy:metadata:quizquest_responses'] = 'Individual question turns within an attempt, including the response the user gave.';
$string['privacy:metadata:quizquest_responses:iscorrect'] = 'Whether the response was judged correct.';
$string['privacy:metadata:quizquest_responses:questionid'] = 'The ID of the question bank question that was asked.';
$string['privacy:metadata:quizquest_responses:response'] = 'The response the user gave to the question.';
$string['privacy:metadata:quizquest_responses:stepchange'] = 'The progress step change applied by the response.';
$string['privacy:metadata:quizquest_responses:timecreated'] = 'The time the response was recorded.';
$string['progressimages'] = 'Progress images';
$string['progressimages_help'] = 'Optional images displayed alongside the dialogue. Upload several images and the displayed image changes as the student progresses: with N images, the first shows at the start and each subsequent image appears as the student completes an equal share of the required steps. Images are ordered by file name.';
$string['progresslabel'] = 'Progress: {$a->tally} / {$a->steps}';
$string['questclose'] = 'Close the quest';
$string['questioncategory'] = 'Question category';
$string['questioncategory_help'] = 'Questions are drawn at random from this question bank category, preferring questions the student has not seen before. Multiple choice questions are presented as choice buttons; short answer questions accept a typed response.';
$string['questionunavailable'] = '[Question no longer available]';
$string['questopen'] = 'Open the quest';
$string['questopenclose'] = 'Open and close dates';
$string['questopenclose_help'] = 'Students can only start and answer attempts between the open and close dates. Any attempt still in progress at the close date is automatically abandoned (with a partial grade if "Partial score on quit" is enabled). Teachers and managers can preview the activity at any time.';
$string['quitattempt'] = 'Quit attempt';
$string['quitattempt_confirm'] = 'Are you sure you want to quit this attempt? You cannot resume it later.';
$string['quizquest:addinstance'] = 'Add a new Quiz Quest';
$string['quizquest:play'] = 'Play a Quiz Quest';
$string['quizquest:view'] = 'View Quiz Quest';
$string['quizquest:viewownattempts'] = 'Review own past Quiz Quest attempts';
$string['quizquest:viewreports'] = 'View Quiz Quest attempt reports';
$string['quizquestname'] = 'Name';
$string['resetattempts'] = 'Delete all Quiz Quest attempts';
$string['resumegame'] = 'Continue';
$string['sendanswer'] = 'Send';
$string['showprogress'] = 'Show progress';
$string['showprogress_help'] = 'Display the step progress bar to students during play.';
$string['startgame'] = 'Start Quest';
$string['status_abandoned'] = 'Abandoned';
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['statuslabel'] = 'Status';
$string['stepmessages'] = 'Step narrative text';
$string['stepmessages_help'] = 'Optional text inserted into the dialogue when a correct answer brings the student\'s step tally up to a given step. Text entered "before feedback" appears as its own message immediately before the correct/incorrect feedback for that turn; text "after feedback" appears immediately after it. Step 0 is special: since there is no feedback yet, both boxes are shown one after another as opening narrative before the first question.';
$string['stepnumber'] = 'Step';
$string['steps'] = 'Steps to complete';
$string['steps_help'] = 'The number of correct answers required to complete the quest.';
$string['taskabandonexpired'] = 'Abandon Quiz Quest attempts past their close date';
$string['textafterfeedback'] = 'Text after feedback';
$string['textbeforefeedback'] = 'Text before feedback';
$string['timing'] = 'Timing';
$string['unlimited'] = 'Unlimited';
$string['viewattempt'] = 'View';
$string['viewattempts'] = 'My attempts';
$string['waiting'] = 'Loading…';
$string['wrongpenalty'] = 'Wrong answers subtract a step';
$string['wrongpenalty_help'] = 'When enabled, an incorrect answer subtracts one step from the progress tally (it never drops below zero). When disabled, incorrect answers simply make no progress.';
$string['youranswerplaceholder'] = 'Type your answer…';
