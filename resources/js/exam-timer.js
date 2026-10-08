/**
 * Runs an exam page's countdown (exam-timer.blade.php) and locks the answers
 * when it reaches zero — shared by the guest exam page and the Student
 * panel's exam page.
 *
 * The lock uses `inert` rather than `disabled`: both stop every click, tap
 * and key press, but a disabled field is left out of the submitted form,
 * which would throw away the answers already given.
 *
 * This only locks the page. The time limit itself is enforced on the server
 * (ExamAttempt::recordAnswers()), which is why `onTimeUp` sends the answers
 * there at the moment the clock runs out.
 *
 * @param {object} options
 * @param {HTMLElement} options.root      Element containing the timer and the answers.
 * @param {() => void} [options.onTimeUp] Called once, when time runs out.
 * @returns {{ isTimeUp: () => boolean }}
 */
window.startExamTimer = function startExamTimer({ root, onTimeUp }) {
    const timer = root.querySelector('[data-qb-timer]');
    const clock = root.querySelector('[data-qb-timer-clock]');
    const timeUpNotice = root.querySelector('[data-qb-timer-timeup]');
    const answers = root.querySelector('[data-qb-answers]');

    let timeIsUp = false;
    const state = { isTimeUp: () => timeIsUp };

    if (! timer || ! clock) {
        return state;
    }

    // Counted from when the page loaded, against the seconds the server
    // said were left — so a wrong clock on the device doesn't matter.
    const endsAt = performance.now() + Number(timer.dataset.seconds) * 1000;
    let interval = null;

    const format = (seconds) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const pad = (value) => String(value).padStart(2, '0');

        return hours > 0
            ? `${hours}:${pad(minutes)}:${pad(seconds % 60)}`
            : `${pad(minutes)}:${pad(seconds % 60)}`;
    };

    const lock = () => {
        timeIsUp = true;
        timer.classList.add('qb-exam-timer--ended');

        if (timeUpNotice) {
            timeUpNotice.hidden = false;
        }

        if (answers) {
            answers.inert = true;
            answers.classList.add('qb-exam-answers--locked');

            // Drop the cursor out of any field it was still sitting in.
            if (answers.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        }
    };

    const tick = () => {
        const seconds = Math.max(0, Math.ceil((endsAt - performance.now()) / 1000));

        clock.textContent = format(seconds);
        timer.classList.toggle('qb-exam-timer--ending', seconds > 0 && seconds <= 60);

        if (seconds > 0) {
            return;
        }

        clearInterval(interval);
        lock();
        onTimeUp?.();
    };

    interval = setInterval(tick, 250);
    tick();

    return state;
};

/**
 * Asks before an exam is submitted with questions left unanswered
 * (exam-submit-warning.blade.php). Returns the function the page calls when
 * the student presses "Submit": it submits straight away when everything is
 * answered or when time is already up (nothing can be answered any more, so
 * a warning would be pointless), and otherwise shows how many questions are
 * unanswered and lets the student go back or submit anyway.
 *
 * A question counts as answered when its `[data-qb-question]` block holds a
 * ticked option or a non-empty text answer.
 *
 * @param {object} options
 * @param {HTMLElement} options.root        Element containing the questions and the dialog.
 * @param {() => boolean} options.isTimeUp  Whether the exam's time has run out.
 * @param {() => void} options.submit       Actually submits the exam.
 * @returns {() => void}
 */
window.guardExamSubmit = function guardExamSubmit({ root, isTimeUp, submit }) {
    const dialog = root.querySelector('[data-qb-submit-warning]');
    const count = root.querySelector('[data-qb-unanswered-count]');

    const unansweredCount = () => [...root.querySelectorAll('[data-qb-question]')].filter((question) => {
        const ticked = question.querySelector('input[type="radio"]:checked');
        const written = [...question.querySelectorAll('textarea')].some((field) => field.value.trim() !== '');

        return ! ticked && ! written;
    }).length;

    const close = () => {
        dialog.hidden = true;
    };

    if (dialog) {
        dialog.querySelector('[data-qb-submit-cancel]')?.addEventListener('click', close);
        dialog.querySelector('[data-qb-submit-confirm]')?.addEventListener('click', () => {
            close();
            submit();
        });
        dialog.addEventListener('click', (event) => event.target === dialog && close());
        document.addEventListener('keydown', (event) => event.key === 'Escape' && ! dialog.hidden && close());
    }

    return () => {
        const unanswered = unansweredCount();

        if (! dialog || isTimeUp() || unanswered === 0) {
            submit();

            return;
        }

        if (count) {
            count.textContent = unanswered;
        }

        dialog.hidden = false;
        dialog.querySelector('[data-qb-submit-cancel]')?.focus();
    };
};
