# Changes

### Unreleased ###

### Changed

- The Japanese language pack (lang/ja) is no longer included: releases ship the English strings
  only, as the Moodle Plugins directory expects. Japanese is provided through Moodle's language
  packs.

### Fixed

- Restoring a backup on the same site no longer keeps a question category from another course's
  question bank unless the person restoring may use that bank (moodle/question:useall or
  usemine there). Otherwise the category is cleared and must be picked again in the settings.
- Restoring a hand-edited backup with a duplicate, non-numeric or out-of-range step message no
  longer aborts the restore: such rows are skipped, as the settings form would reject them.
- The privacy export of an attempt now includes the preview flag and the generic-response queues,
  matching the data the privacy metadata declares.

### 0.6.3 (2026100300) ###

* Declare Moodle 5.3 support.
