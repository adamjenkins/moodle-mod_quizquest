# Changes

### 0.6.0 (2026071400) ###

* New "Display generic responses" option in the Generic responses settings section,
  controlling when a generic pool response is shown relative to the matched
  answer's own feedback: only when question feedback is absent (default, the
  previous behaviour), never, before the question feedback, or after it.
* Fixed the settings form rejecting a category chosen after switching question
  banks ("contains no questions" error even for full categories): the submitted
  category value was discarded because the options swapped in client-side were
  never registered server-side.
* The answer choice buttons are now right-aligned, matching the student's own
  chat bubbles.
