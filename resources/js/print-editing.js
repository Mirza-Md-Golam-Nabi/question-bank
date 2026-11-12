/*
 * The "editing mode" of the printable question paper
 * (resources/views/teacher/exam-print.blade.php): the teacher can change
 * the paper's own words — the heading lines, a question, its options, a
 * CQ part, a stimulus — just for this paper, before printing it.
 *
 * Nothing here reaches the server: a question in the bank changes only
 * through its own form (approval, versions). The changes are kept in this
 * browser, per exam, so a reload or the "with answers" copy shows them too;
 * "Undo all changes" brings the paper back as it was. The institution name
 * and the instructions are not editable here — the name is one of the
 * user's (paid) names, the instructions have their own field.
 */

const dataElement = document.getElementById('qb-print-settings-data');
const paper = document.querySelector('.qb-paper');

if (dataElement && paper) {
    const { editsKey, pageNumberStyle } = JSON.parse(dataElement.textContent);
    const inPaperDigits = (number) => (pageNumberStyle === 'bengali' ? String(number).replace(/[0-9]/g, (digit) => '০১২৩৪৫৬৭৮৯'[digit]) : String(number));
    const toggle = document.querySelector('[data-editing-toggle]');
    const counter = document.querySelector('[data-edits-count]');
    const reset = document.querySelector('[data-edits-reset]');

    const read = () => {
        try {
            return JSON.parse(localStorage.getItem(editsKey) ?? '{}') ?? {};
        } catch {
            return {};
        }
    };

    const write = (edits) => {
        try {
            if (Object.keys(edits).length === 0) {
                localStorage.removeItem(editsKey);
            } else {
                localStorage.setItem(editsKey, JSON.stringify(edits));
            }
        } catch {
            // Private window or storage blocked: the edit stays on screen only.
        }
    };

    // Every editable block gets a key that stays the same between visits:
    // its question (`data-edit-scope`) plus what it is within it.
    const blocks = new Map();
    const add = (element, key) => element && blocks.set(key, element);

    for (const element of paper.querySelectorAll('[data-editable]')) {
        add(element, `head:${element.dataset.editable}`);
    }

    paper.querySelectorAll('.qb-question-stimulus').forEach((stimulus, index) => {
        add(stimulus.querySelector('.qb-question-text'), `stimulus:${index}`);
    });

    for (const question of paper.querySelectorAll('[data-edit-scope]')) {
        const scope = question.dataset.editScope;
        add(question.querySelector(':scope .qb-question-body > .qb-question-text'), `${scope}:text`);
        question.querySelectorAll('.qb-question-option .qb-question-option-content > div:first-child').forEach((option, index) => add(option, `${scope}:option${index}`));
        question.querySelectorAll('.qb-question-part .qb-question-option-content > div:first-child').forEach((part, index) => add(part, `${scope}:part${index}`));
    }

    const edits = read();

    const showCount = () => {
        const count = Object.keys(edits).filter((key) => blocks.has(key)).length;
        counter.textContent = count > 0 ? counter.dataset.template.replace(':count', inPaperDigits(count)) : '';
        reset.hidden = count === 0;
    };

    for (const [key, element] of blocks) {
        if (key in edits) {
            element.innerHTML = edits[key];
        }

        let timer;
        element.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                edits[key] = element.innerHTML;
                write(edits);
                showCount();
            }, 400);
        });
    }

    const setEditing = (on) => {
        paper.toggleAttribute('data-editing', on);

        for (const element of blocks.values()) {
            element.contentEditable = on ? 'true' : 'false';
            // A formula is kept whole: it can be deleted, not broken.
            element.querySelectorAll('.qb-katex-embed').forEach((formula) => {
                formula.contentEditable = 'false';
            });
        }
    };

    toggle?.addEventListener('change', () => setEditing(toggle.checked));

    reset?.addEventListener('click', () => {
        if (window.confirm(reset.dataset.confirm)) {
            write({});
            window.location.reload();
        }
    });

    showCount();
}
