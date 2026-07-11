# Changelog

All notable changes to `mod_quizquest` are documented in this file.

## [0.1.0] - 2026-07-11

### Added

- Initial release, adapting the escape-room chat interface of `mod_aiescape` to a question bank driven game:
  - Questions drawn at random from a configurable question bank category (multiple choice single-answer and short answer types), preferring questions the student has never seen, then questions not yet asked in the current attempt.
  - Multiple choice questions rendered as shuffled choice buttons; short answer questions accept a typed response evaluated with the question's own matching rules.
  - Step-tally progress with an optional progress bar and an optional "wrong answers subtract a step" penalty.
  - **Progress images** — multiple images displayed alongside the dialogue, switching as the student advances through equal shares of the required steps.
  - Attempt management: max attempts, resume with chat replay, quit with optional partial grade, student review of past attempts (`myattempts.php`).
  - Gradebook integration (full grade on completion, optional partial on quit), "Complete the quest" custom completion rule, attempt started/completed/abandoned events, course reset support, GDPR privacy provider, and teacher preview attempts excluded from real data.
