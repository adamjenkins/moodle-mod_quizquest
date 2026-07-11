# Quiz Quest (`mod_quizquest`)

A Moodle activity plugin that presents question bank questions as an interactive, escape-room style quest. Students answer questions in a chat-like dialogue, advancing a step for each correct answer until they complete the quest. The interface is adapted from [AI Escape Room (`mod_aiescape`)](https://github.com/adamjenkins/moodle-mod_aiescape), with a question bank category taking the place of the AI.

## Requirements

- Moodle 5.0 or later
- A question bank category containing multiple choice (single answer) and/or short answer questions

## Installation

1. Copy the plugin directory into `<moodleroot>/public/mod/quizquest/`
2. Visit Site administration → Notifications to run the upgrade

## How it works

Each turn, one question is drawn at random from the configured question bank category:

- **Multiple choice** questions (single-answer only) are presented as choice buttons in random order
- **Short answer** questions accept a typed response, evaluated with the question's own matching rules (case sensitivity and `*` wildcards included)

Random selection prefers questions the student has never been asked in any of their attempts, then questions not yet asked in the current attempt, then anything eligible. A correct answer adds one step; the quest completes when the step tally reaches the configured **Steps to complete**. Optionally, wrong answers can subtract a step.

Answer feedback stored on the matched question answer is shown in the dialogue; otherwise a generic correct/incorrect message is used.

### Progress images

Teachers can upload multiple images that display alongside the dialogue. With N images (ordered by file name), the first shows at the start and each subsequent image appears as the student completes an equal share of the required steps — reaching the last image at the end of the quest.

### Attempt management

- Configurable maximum attempts per student
- **Continue** button resumes an in-progress attempt (with full chat history replay)
- **Quit attempt** button lets students abandon a run early
- Optional partial scoring on quit (awards grade proportional to steps completed)
- Student review of completed attempts (configurable per activity)
- **Course reset** — the standard Moodle course-reset flow includes a "Delete all Quiz Quest attempts" checkbox

### Grading and completion

- Standard Moodle gradebook integration; completed attempts receive the full grade
- Abandoned attempts can receive a partial grade (configurable per activity)
- A "Complete the quest" custom completion rule, plus grade-based completion support

### Teacher preview

Users with the `mod/quizquest:viewreports` capability (teachers, managers) can play through the activity themselves. These preview attempts are tracked separately from real student attempts and are excluded from attempt limits, gradebook, and completion.

### Fullscreen mode

A fullscreen toggle button in the top-right corner of the game interface lets students expand the activity to fill the browser window.

## Activity settings

| Setting | Description |
|---------|-------------|
| Question category | The question bank category questions are drawn from |
| Steps to complete | Number of correct answers required to complete (1–100) |
| Maximum attempts | How many attempts a student may make |
| Wrong answers subtract a step | Optional penalty; the tally never drops below zero |
| Show progress | Display the step progress bar to students during play |
| Allow student review | Let students re-read completed attempts |
| Partial score on quit | Award proportional grade when a student quits early |
| Progress images | Images displayed alongside the dialogue, switching with progress |

## Privacy

This plugin stores attempt records (status, progress, timestamps) and the responses students give to questions. All personal data is exportable and deletable via Moodle's Privacy API (`\mod_quizquest\privacy\provider`).

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/gpl-3.0.html
