# Changelog

All notable changes to `mod_quizquest` are documented in this file.

## [0.5.0] - 2026-07-11

### Added

- **Backup and restore support** (`FEATURE_BACKUP_MOODLE2`): full activity configuration (settings, step messages, generic response pools, progress images) plus optional user data (attempts and responses; teacher previews excluded). On restore, the question bank reference and per-response question ids are remapped when the bank was part of the backup, kept when restoring on the same site with the source still present, and cleared/zeroed otherwise so stale ids can never point at the wrong question. Duplicate/import now work from the course page.
- **PHPUnit test suite** (48 tests): privacy provider, attempt manager, question picker, message bank, lib callbacks (grades, course reset incl. date shift, calendar events, deletion), all four external functions (incl. capability, open-window and IDOR checks), and a backup/restore roundtrip — plus a `mod_quizquest` data generator for third-party tests.
- Maturity raised to BETA.

## [0.4.1] - 2026-07-11

### Fixed

- **"Course question bank" selection crashed** with a coding error on Moodle 5.x: question categories now only exist in qbank activity contexts, so the bank list offers the course's system-type question bank instance (creating it on demand, as core does) instead of the raw course context. The settings form also tolerates instances saved with the old course-context value by showing an empty category list to re-pick from rather than crashing.
- Security-review hardening: `format_string`/`format_text` now always receive the module context (and page context is set before use); the settings form's category picker decodes server labels to plain text instead of injecting HTML; the progress-image areas enforce the course upload size limit; step-message and generic-response fields are cleaned as plain text (`PARAM_TEXT`) on submission.
- Privacy provider: declared the previously missing `feedbacktext`/`stepmsgbefore`/`stepmsgafter` (responses) and `correctpoolqueue`/`incorrectpoolqueue` (attempts) fields in the metadata, and included the per-turn text fields in data exports.
- Grading: scales are now rejected in the settings form — the grade logic is point-based and a scale would have been stored as a negative point value.
- Course reset: the "shift dates" option now moves the open/close dates and rebuilds the matching calendar events.
- Replaced hard-coded UI strings ('#' column header, back link) with language strings and the FontAwesome completion icon with a core pix icon.

## [0.2.0] - 2026-07-11

### Added

- **Open and close dates** (Timing section in the activity settings), mimicking mod_quiz. The dates are surfaced through Moodle's activity-dates API, so "Opens:/Closes:" lines on the course page and activity page render identically to the quiz module. Students cannot start or answer attempts outside the window; teachers/managers can preview at any time. Attempts still in progress at the close date are automatically abandoned — lazily when next accessed, and by a new scheduled task (`\mod_quizquest\task\abandon_expired_attempts`) — awarding a partial grade when "Partial score on quit" is enabled.
- **Calendar events** for the open and close dates, matching mod_quiz: "… opens" / "… closes" events appear in the calendar and the timeline block (with a "Start Quest" action for students who can still play), dragging an event in the calendar updates the activity dates within a validated range, and `quizquest_refresh_events()` supports course restore and the "Refresh calendar events" tool.

## [0.1.0] - 2026-07-11

### Added

- Initial release, adapting the escape-room chat interface of `mod_aiescape` to a question bank driven game:
  - Questions drawn at random from a configurable question bank category (multiple choice single-answer and short answer types), preferring questions the student has never seen, then questions not yet asked in the current attempt.
  - Multiple choice questions rendered as shuffled choice buttons; short answer questions accept a typed response evaluated with the question's own matching rules.
  - Step-tally progress with an optional progress bar and an optional "wrong answers subtract a step" penalty.
  - **Progress images** — multiple images displayed alongside the dialogue, switching as the student advances through equal shares of the required steps.
  - Attempt management: max attempts, resume with chat replay, quit with optional partial grade, student review of past attempts (`myattempts.php`).
  - Gradebook integration (full grade on completion, optional partial on quit), "Complete the quest" custom completion rule, attempt started/completed/abandoned events, course reset support, GDPR privacy provider, and teacher preview attempts excluded from real data.
