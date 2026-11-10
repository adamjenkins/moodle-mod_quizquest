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

### Choosing the question bank

The settings form has two linked menus. **Question bank** lists every question bank activity in the course, plus any banks shared to other courses or the whole site that you have permission to use. Choosing a bank refreshes the **Question category** menu with that bank's categories (no page reload needed; without JavaScript the categories of the selected bank are shown when the form loads). **Include questions from subcategories** also draws questions from every category beneath the chosen one.

### Step narrative text

Teachers can add story text tied to the student's progress. Each **step message** has a step number and optional text "before feedback" and "after feedback". When a correct answer brings the tally up to that step, the before-text appears as its own message just before the correct/incorrect feedback and the after-text just after it. Step 0 is the opening narrative: both texts are shown before the first question. Only one message per step is allowed, and the step can be no higher than **Steps to complete**.

### Generic response pools

Optional pools of **correct-answer responses** and **incorrect-answer responses** replace or accompany the default "Correct!"/"Not quite" messages. Each turn a random response the student has not yet seen in this attempt is drawn from the relevant pool; once every response has been shown, the pool reshuffles. **Display generic responses** decides when a pool response is shown relative to the matched answer's own feedback: only when that feedback is absent (the default), never, before it, or after it.

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
| Open/Close the quest | Optional open and close dates (shown on the course page like the quiz module); attempts still open at the close date are automatically abandoned |
| Question bank | The question bank activity (in this course, or shared to it) to draw questions from |
| Question category | The category in that bank that questions are drawn from |
| Include questions from subcategories | Also draw questions from every subcategory of the chosen category |
| Steps to complete | Number of correct answers required to complete (1–100) |
| Maximum attempts | How many attempts a student may make |
| Wrong answers subtract a step | Optional penalty; the tally never drops below zero |
| Show progress | Display the step progress bar to students during play |
| Allow student review | Let students re-read completed attempts |
| Partial score on quit | Award proportional grade when a student quits early |
| Progress images | Raster images (PNG, JPEG, GIF, WebP) displayed alongside the dialogue, switching with progress |
| Step narrative text | Story text shown at given steps, before and/or after that turn's feedback (step 0 opens the quest) |
| Generic responses | Pools of correct-answer and incorrect-answer responses, shuffled without repeats |
| Display generic responses | When a pool response is shown: only when the answer has no feedback, never, before it, or after it |

## Privacy

This plugin stores attempt records (status, progress, timestamps) and the responses students give to questions. All personal data is exportable and deletable via Moodle's Privacy API (`\mod_quizquest\privacy\provider`).

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/gpl-3.0.html
