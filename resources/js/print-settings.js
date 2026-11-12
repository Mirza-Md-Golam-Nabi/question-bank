/*
 * The print settings of the printable question paper
 * (resources/views/teacher/exam-print.blade.php): every control with a
 * `data-setting` changes the paper at once, and the settings are saved for
 * the user a moment later. What the settings are and their limits is
 * App\Support\PrintSettings; this only shows them.
 */

const PAPER_WIDTHS_MM = { A4: 210, Letter: 215.9, Legal: 215.9, A5: 148 };
const SAVE_DELAY_MS = 600;

const dataElement = document.getElementById('qb-print-settings-data');

if (dataElement) {
    const data = JSON.parse(dataElement.textContent);
    const settings = { ...data.settings };
    const paper = document.querySelector('.qb-paper');
    const pageRule = document.getElementById('qb-page-rule');
    const saveStatus = document.querySelector('[data-save-status]');
    const controls = [...document.querySelectorAll('[data-setting]')];
    const nameIds = Object.keys(data.institutionNames ?? {}).map(Number);

    // Never chosen yet: the first (free) name. A name no longer usable: none.
    if (settings.institution_name_id === null) {
        settings.institution_name_id = nameIds[0] ?? 0;
    } else if (settings.institution_name_id !== 0 && !nameIds.includes(settings.institution_name_id)) {
        settings.institution_name_id = 0;
    }

    const instructionsOf = (key) => settings[key] ?? data.defaultInstructions[key] ?? '';

    const valueOf = (control) => {
        const raw = control.type === 'checkbox' ? control.checked : (control.dataset.value ?? control.value);

        switch (control.dataset.type) {
            case 'bool':
                return Boolean(raw);
            case 'int':
                return Number.parseInt(raw, 10) || 0;
            default:
                return raw;
        }
    };

    const isOn = (value) => value === true || (typeof value === 'number' && value > 1);

    const showControls = () => {
        for (const control of controls) {
            const value = control.dataset.setting.endsWith('_instructions')
                ? instructionsOf(control.dataset.setting)
                : settings[control.dataset.setting];

            if (control.dataset.value !== undefined) {
                control.setAttribute('aria-pressed', String(String(value) === control.dataset.value));
            } else if (control.type === 'checkbox') {
                control.checked = Boolean(value);
            } else if (document.activeElement !== control) {
                control.value = value ?? '';
            }
        }

        for (const output of document.querySelectorAll('[data-output]')) {
            output.textContent = `${settings[output.dataset.output]}px`;
        }

        for (const field of document.querySelectorAll('[data-shown-with]')) {
            field.hidden = !isOn(settings[field.dataset.shownWith]);
        }
    };

    const applyToPaper = () => {
        const style = paper.style;
        const margin = `${settings.margin_top}mm ${settings.margin_right}mm ${settings.margin_bottom}mm ${settings.margin_left}mm`;

        style.setProperty('--qb-columns', settings.columns);
        style.setProperty('--qb-column-gap', `${settings.column_gap}px`);
        style.setProperty('--qb-question-gap', `${settings.question_gap}px`);
        style.setProperty('--qb-font-size', `${settings.font_size}px`);
        style.setProperty('--qb-font-family', data.fonts[settings.font] ?? 'inherit');
        style.setProperty('--qb-institution-size', `${settings.institution_font_size}px`);
        style.setProperty('--qb-text-align', settings.text_align);
        style.setProperty('--qb-paper-width', `${PAPER_WIDTHS_MM[settings.paper_size] ?? 210}mm`);
        style.setProperty('--qb-margin', margin);

        paper.dataset.optionStyle = settings.option_style;
        paper.dataset.optionsPerRow = settings.options_per_row;
        paper.dataset.columnDivider = settings.column_divider && settings.columns > 1 ? '1' : '0';

        for (const part of paper.querySelectorAll('[data-print-part]')) {
            part.hidden = !settings[`show_${part.dataset.printPart}`];
        }

        for (const block of paper.querySelectorAll('[data-print-instructions]')) {
            block.textContent = instructionsOf(block.dataset.printInstructions);
            block.hidden = block.hidden || block.textContent.trim() === '';
        }

        const institution = paper.querySelector('[data-print-institution]');
        const name = data.institutionNames?.[settings.institution_name_id] ?? '';
        institution.textContent = name;
        institution.hidden = name === '';

        const pageNumber = settings.page_numbers
            ? ` @bottom-${settings.page_number_position} { content: counter(page, ${data.pageNumberStyle}); font-size: 10pt; }`
            : '';
        pageRule.textContent = `@page { size: ${settings.paper_size}; margin: ${margin};${pageNumber} }`;

        fitToScreen();
    };

    // The paper keeps its real width, so its columns look as they will on
    // paper; a screen narrower than that shows it smaller.
    const fitToScreen = () => {
        paper.style.zoom = '';
        const available = paper.parentElement.clientWidth - 16;
        const scale = Math.min(1, available / paper.offsetWidth);
        paper.style.zoom = scale < 1 ? String(scale) : '';
    };

    window.addEventListener('resize', fitToScreen);

    let saveTimer;

    const save = () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(async () => {
            try {
                const response = await fetch(data.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(settings),
                });

                saveStatus.textContent = response.ok ? data.saved : data.notSaved;
            } catch {
                saveStatus.textContent = data.notSaved;
            }
        }, SAVE_DELAY_MS);
    };

    const change = (key, value) => {
        // An instruction left as the Admin's text stays "the Admin's", so a
        // later change of it reaches this paper too.
        settings[key] = key.endsWith('_instructions') && value === data.defaultInstructions[key] ? null : value;

        showControls();
        applyToPaper();
        save();
    };

    for (const control of controls) {
        const event = control.dataset.value !== undefined ? 'click' : (control.type === 'checkbox' || control.tagName === 'SELECT' ? 'change' : 'input');

        control.addEventListener(event, () => change(control.dataset.setting, valueOf(control)));
    }

    for (const reset of document.querySelectorAll('[data-reset]')) {
        reset.addEventListener('click', () => change(reset.dataset.reset, null));
    }

    for (const toggle of document.querySelectorAll('[data-settings-toggle]')) {
        toggle.addEventListener('click', () => document.body.toggleAttribute('data-settings-open'));
    }

    showControls();
    applyToPaper();
}
