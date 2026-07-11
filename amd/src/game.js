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
 * AMD module: Quiz Quest game controller.
 *
 * @module     mod_quizquest/game
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/templates', 'core/str'],
function(Ajax, Notification, Templates, Str) {

    /** @type {number} Course module id */
    var cmid = 0;
    /** @type {number} Current attempt id */
    var attemptId = 0;
    /** @type {number} Steps needed */
    var steps = 10;
    /** @type {boolean} Show progress bar */
    var showProgress = false;
    /** @type {boolean} Whether input is currently disabled */
    var busy = false;
    /** @type {Array<string>} Progress image URLs, ordered; image k shows at k/N of required steps */
    var images = [];

    /**
     * Wrapper around core/str get_string.
     *
     * @param {string} key
     * @param {string} component
     * @param {*} data
     * @returns {Promise<string>}
     */
    var getString = function(key, component, data) {
        return Str.get_string(key, component, data);
    };

    /**
     * Calls start_attempt and bootstraps the UI with any existing history
     * plus the current question.
     */
    var startAttempt = function() {
        setLoading(true);
        callService('mod_quizquest_start_attempt', {cmid: cmid})
        .then(function(result) {
            attemptId    = result.attemptid;
            steps        = result.steps;
            showProgress = result.showprogress;

            var renderAll = Promise.resolve();
            result.messages.forEach(function(msg) {
                renderAll = renderAll.then(function() {
                    return renderMessage(msg.role, msg.message);
                });
            });

            return renderAll.then(function() {
                updateProgress(result.tally, steps);
                updateImage(result.tally);
                setLoading(false);
                return renderQuestion(result.question);
            });
        })
        .catch(function(e) {
            Notification.exception(e);
            setLoading(false);
        });
    };

    /**
     * Renders a question: its text as an assistant bubble, then either the
     * choice buttons (multichoice) or the typed-answer input (shortanswer).
     *
     * @param {Object} question {text: string, qtype: string, choices: Array}
     * @returns {Promise}
     */
    var renderQuestion = function(question) {
        return renderMessage('assistant', question.text)
        .then(function() {
            var freetextArea = document.getElementById('quizquest-freetext-area');
            if (question.qtype === 'multichoice') {
                freetextArea.classList.add('d-none');
                renderChoices(question.choices);
            } else {
                hideChoices();
                freetextArea.classList.remove('d-none');
                enableInput();
            }
            return null;
        });
    };

    /**
     * Submits the student's answer (a choice or typed text) and renders the
     * feedback plus the next question.
     *
     * @param {number} answerId    The chosen answer id (multichoice), 0 otherwise
     * @param {string} answerLabel The label shown in the student's chat bubble
     * @param {string} answerText  The typed answer (shortanswer), '' otherwise
     * @returns {Promise}
     */
    var submitAnswer = function(answerId, answerLabel, answerText) {
        if (busy) {
            return Promise.resolve();
        }
        setLoading(true);
        disableInput();

        return renderMessage('user', answerLabel || answerText)
        .then(function() {
            return callService('mod_quizquest_submit_answer', {
                cmid: cmid,
                attemptid: attemptId,
                answerid: answerId,
                answertext: answerText,
            });
        })
        .then(function(result) {
            return renderMessage('assistant', result.feedback)
            .then(function() {
                updateProgress(result.tally, result.steps);
                updateImage(result.tally);

                if (result.completed) {
                    hideQuitButton();
                    return showCompletion(result.canrestart !== false);
                }

                return renderQuestion(result.question);
            });
        })
        .catch(function(e) {
            Notification.exception(e);
            enableInput();
        })
        .then(function() {
            setLoading(false);
        });
    };

    /**
     * Renders a message bubble in the chat log.
     *
     * @param {string} role    'user' or 'assistant'
     * @param {string} message Message text
     * @returns {Promise}
     */
    var renderMessage = function(role, message) {
        var context = {role: role, message: message, isuser: role === 'user'};
        return Templates.render('mod_quizquest/message', context)
        .then(function(html) {
            var chatlog = document.getElementById('quizquest-chatlog');
            chatlog.insertAdjacentHTML('beforeend', html);
            chatlog.scrollTop = chatlog.scrollHeight;
        });
    };

    /**
     * Renders (or replaces) the answer choice buttons in random order.
     *
     * @param {Array<{id: number, label: string}>} choices
     */
    var renderChoices = function(choices) {
        var container = document.getElementById('quizquest-choices');
        container.innerHTML = '';
        container.classList.remove('d-none');

        // Fisher-Yates shuffle (non-destructive copy).
        var shuffled = choices.slice();
        for (var i = shuffled.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = shuffled[i]; shuffled[i] = shuffled[j]; shuffled[j] = tmp;
        }

        shuffled.forEach(function(choice) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary';
            btn.textContent = choice.label;
            btn.addEventListener('click', function() {
                hideChoices();
                submitAnswer(choice.id, choice.label, '');
            });
            container.appendChild(btn);
        });
    };

    /** Empties and hides the choice button container. */
    var hideChoices = function() {
        var container = document.getElementById('quizquest-choices');
        container.innerHTML = '';
        container.classList.add('d-none');
    };

    /**
     * Updates the progress bar if it is enabled.
     *
     * @param {number} tally
     * @param {number} total
     */
    var updateProgress = function(tally, total) {
        if (!showProgress) {
            return;
        }
        var bar   = document.getElementById('quizquest-progress-bar');
        var label = document.getElementById('quizquest-progress-label');
        if (!bar) {
            return;
        }
        var pct = total > 0 ? Math.min(100, Math.round((tally / total) * 100)) : 0;
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', tally);

        getString('progresslabel', 'mod_quizquest', {tally: tally, steps: total})
        .then(function(str) {
            if (label) {
                label.textContent = str;
            }
            return str;
        })
        .catch(Notification.exception);
    };

    /**
     * Switches the progress image to match the current tally.
     *
     * With N images, image k (0-based) is shown once the student has completed
     * k/N of the required steps; the last image shows at completion.
     *
     * @param {number} tally
     */
    var updateImage = function(tally) {
        if (!images.length) {
            return;
        }
        var img = document.getElementById('quizquest-progress-image');
        if (!img) {
            return;
        }
        var index = steps > 0
            ? Math.min(images.length - 1, Math.floor((tally / steps) * images.length))
            : 0;
        if (img.getAttribute('src') !== images[index]) {
            img.setAttribute('src', images[index]);
        }
    };

    /**
     * Shows the completion banner and disables all input.
     *
     * @param {boolean} canRestart Whether the user has remaining attempts
     * @returns {Promise}
     */
    var showCompletion = function(canRestart) {
        if (canRestart === undefined) {
            canRestart = true;
        }
        disableInput();
        hideQuitButton();
        hideChoices();
        document.getElementById('quizquest-freetext-area').classList.add('d-none');

        return Promise.all([
            getString('attemptcompleted', 'mod_quizquest'),
            getString('attemptcompletedmessage', 'mod_quizquest'),
            getString('newattempt', 'mod_quizquest'),
        ])
        .then(function(strings) {
            var newattempturl = canRestart
                ? new URL(window.location.href).pathname + '?id=' + cmid
                : '';
            return Templates.render('mod_quizquest/completion', {
                title: strings[0],
                message: strings[1],
                newattempturl: newattempturl,
                newattemptlabel: strings[2],
            });
        })
        .then(function(html) {
            var completionDiv = document.getElementById('quizquest-completion');
            completionDiv.innerHTML = html;
            completionDiv.classList.remove('d-none');
            completionDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
    };

    /**
     * Toggles the game container in and out of fullscreen mode.
     */
    var toggleFullscreen = function() {
        var game = document.getElementById('quizquest-game');
        if (!document.fullscreenElement) {
            game.requestFullscreen().catch(function() {
                // Fullscreen refusals (e.g. missing user gesture) are non-fatal.
            });
        } else {
            document.exitFullscreen();
        }
    };

    /**
     * Updates the fullscreen button icons, aria-label, and title to reflect current state.
     */
    var updateFullscreenButton = function() {
        var btn = document.getElementById('quizquest-fullscreen-btn');
        if (!btn) {
            return;
        }
        var isFs = !!document.fullscreenElement;
        var label = isFs ? btn.dataset.exitlabel : btn.dataset.enterlabel;
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);
        var enterIcon = btn.querySelector('.quizquest-icon-enter');
        var exitIcon  = btn.querySelector('.quizquest-icon-exit');
        if (enterIcon) {
            enterIcon.classList.toggle('d-none', isFs);
        }
        if (exitIcon) {
            exitIcon.classList.toggle('d-none', !isFs);
        }
    };

    /** Hides the quit button (called after completion or abandonment). */
    var hideQuitButton = function() {
        var btn = document.getElementById('quizquest-quit-btn');
        if (btn) {
            btn.closest('.text-end').classList.add('d-none');
        }
    };

    /**
     * Abandons the current attempt after user confirmation.
     */
    var quitAttempt = function() {
        if (busy) {
            return;
        }
        var btn = document.getElementById('quizquest-quit-btn');
        var confirmMsgPromise = (btn && btn.dataset.confirm)
            ? Promise.resolve(btn.dataset.confirm)
            : getString('quitattempt_confirm', 'mod_quizquest');

        Promise.all([confirmMsgPromise, getString('quitattempt', 'mod_quizquest')])
            .then(function(results) {
                Notification.confirm(
                    '',
                    results[0],
                    results[1],
                    '',
                    doQuitAttempt
                );
            }).catch(Notification.exception);
    };

    /**
     * Performs the actual quit-attempt service call once the user has confirmed.
     */
    var doQuitAttempt = function() {
        setLoading(true);
        disableInput();
        hideQuitButton();
        hideChoices();
        document.getElementById('quizquest-freetext-area').classList.add('d-none');

        callService('mod_quizquest_quit_attempt', {cmid: cmid, attemptid: attemptId})
        .then(function(result) {
            return Str.get_string('attemptabandoned', 'mod_quizquest')
            .then(function(str) {
                var completionDiv = document.getElementById('quizquest-completion');
                completionDiv.innerHTML = '';

                var alert = document.createElement('div');
                alert.className = 'alert alert-warning';
                alert.textContent = str;
                completionDiv.appendChild(alert);

                if (result.canrestart) {
                    return Str.get_string('newattempt', 'mod_quizquest').then(function(label) {
                        var link = document.createElement('a');
                        link.href = new URL(window.location.href).pathname + '?id=' + cmid;
                        link.className = 'btn btn-primary mt-2';
                        link.textContent = label;
                        completionDiv.appendChild(link);
                    });
                }
                return null;
            });
        })
        .then(function() {
            var completionDiv = document.getElementById('quizquest-completion');
            completionDiv.classList.remove('d-none');
            completionDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        })
        .catch(Notification.exception)
        .then(function() {
            setLoading(false);
        });
    };

    /**
     * Shows the loading spinner and marks the UI as busy.
     * @param {boolean} state True to show loading, false to hide.
     */
    var setLoading = function(state) {
        busy = state;
        var el = document.getElementById('quizquest-loading');
        if (el) {
            el.classList.toggle('d-none', !state);
        }
    };

    /** Disables all interactive inputs. */
    var disableInput = function() {
        var freetext = document.getElementById('quizquest-freetext');
        var sendbtn  = document.getElementById('quizquest-send-btn');
        if (freetext) {
            freetext.disabled = true;
        }
        if (sendbtn) {
            sendbtn.disabled = true;
        }
        document.querySelectorAll('#quizquest-choices button').forEach(function(btn) {
            btn.disabled = true;
        });
    };

    /** Re-enables interactive inputs and wires the send button. */
    var enableInput = function() {
        var freetext = document.getElementById('quizquest-freetext');
        var sendbtn  = document.getElementById('quizquest-send-btn');

        if (freetext) {
            freetext.disabled = false;
            freetext.focus();
            if (sendbtn && !sendbtn.dataset.wired) {
                sendbtn.dataset.wired = '1';
                sendbtn.disabled = false;
                sendbtn.addEventListener('click', function() {
                    var text = freetext.value.trim();
                    if (!text) {
                        return;
                    }
                    freetext.value = '';
                    submitAnswer(0, '', text);
                });
                freetext.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendbtn.click();
                    }
                });
            } else if (sendbtn) {
                sendbtn.disabled = false;
            }
        }

        document.querySelectorAll('#quizquest-choices button').forEach(function(btn) {
            btn.disabled = false;
        });
    };

    /**
     * Calls a Moodle web service and returns the result.
     *
     * @param {string} methodname
     * @param {Object} args
     * @returns {Promise<Object>}
     */
    var callService = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    };

    return {
        /**
         * Initialises the game module.
         *
         * @param {number} cmidParam Course module id
         * @param {Array<string>} imageUrls Progress image URLs, ordered
         */
        init: function(cmidParam, imageUrls) {
            cmid = cmidParam;
            images = imageUrls || [];

            var startBtn = document.getElementById('quizquest-start-btn');
            if (startBtn) {
                startBtn.addEventListener('click', function() {
                    document.getElementById('quizquest-start-screen').classList.add('d-none');
                    document.getElementById('quizquest-game').classList.remove('d-none');
                    startAttempt();
                });
            } else {
                startAttempt();
            }

            var quitBtn = document.getElementById('quizquest-quit-btn');
            if (quitBtn) {
                quitBtn.addEventListener('click', quitAttempt);
            }

            var fsBtn = document.getElementById('quizquest-fullscreen-btn');
            if (fsBtn) {
                if (!document.fullscreenEnabled) {
                    fsBtn.classList.add('d-none');
                } else {
                    fsBtn.addEventListener('click', toggleFullscreen);
                    document.addEventListener('fullscreenchange', updateFullscreenButton);
                }
            }
        },
    };
});
