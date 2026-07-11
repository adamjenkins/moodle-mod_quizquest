# Changes

### 0.5.0 (2026071107) ###

* Added backup and restore support (course backup, duplicate, import): all activity
  configuration including step messages, generic response pools and progress images,
  plus attempts and responses when user data is included (teacher previews excluded).
  Question bank references are remapped on restore where possible and cleared when
  they cannot be resolved.
* Added a PHPUnit test suite (48 tests) covering the privacy provider, attempt
  manager, question picker, message bank, lib callbacks, all external functions and
  a backup/restore roundtrip, plus a data generator.
* Fixed the "Course question bank" option crashing on Moodle 5.x — the bank list now
  offers the course's system-type question bank instance, created on demand.
* Security hardening from a full pre-submission review: output escaping contexts,
  upload size limits, plain-text cleaning of narrative fields, and a category picker
  that no longer injects server HTML into the DOM.
* Completed the privacy provider metadata and export for the narrative/feedback
  fields and attempt state added in 0.3.0.
* Course reset now supports shifting the open/close dates, rebuilding the calendar
  events; scale grading is rejected (points only).
* Maturity raised to BETA.

### 0.4.0 (2026071105) ###

* Teachers can pick which question bank to draw from: the course's own bank or any
  shared bank they are authorised to use, with an AJAX-refreshed category select.
* New option to include questions from subcategories of the chosen category.
* A step message configured for step 0 is shown as opening narrative before the
  first question.

### 0.3.0 (2026071104) ###

* Step-triggered narrative text: teacher-configured messages inserted before/after
  the feedback when the step tally reaches a given step.
* Shuffled pools of generic correct/incorrect feedback, used when the matched answer
  has no feedback of its own; shown text is persisted per turn so replays match.

### 0.2.0 (2026071102) ###

* Open and close dates with quiz-consistent display, calendar events and timeline
  integration; attempts still open at the close date are abandoned automatically.

### 0.1.0 (2026071101) ###

* Initial release: an escape-room style chat quest driven by question bank
  questions (single-answer multiple choice and short answer), with step progress,
  progress images, attempt management, grading and GDPR support.
