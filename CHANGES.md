# Changes

### 0.6.5 (2026111000) ###

### Changed

- The README now explains the question bank and category menus, step narrative text and the
  generic response pools, and lists their settings.

### Fixed

- A student's requests within one quest (starting, answering, quitting) are now handled one at a
  time. Sending several at once, for example by double-clicking or from two browser tabs, can no
  longer start more attempts than the activity allows, answer the same question twice or lose a
  step from the tally. A request that has to wait too long gets the message "Another request for
  this attempt is still being processed. Please try again in a moment."
- Restoring a backup now keeps the activity settings within the limits of the settings form
  (steps to complete, maximum attempts, grade, the generic response display mode and the yes/no
  options), and keeps each attempt's status and step tally valid.

### Security

- Restoring a backup on the same site keeps a pending question in a student's attempt only if the
  person restoring may use the question bank that question is in. Otherwise the question is shown
  in the history as no longer available.
- Progress images are shown in the page only when they are PNG, JPEG, GIF or WebP images. Any other
  file in the progress images area, such as one brought in by a restored backup, is offered as a
  download instead.
