/*
 * The print settings of the printable question paper
 * (resources/views/teacher/exam-print.blade.php): every control with a
 * `data-setting` changes the paper at once, and the settings are saved for
 * the user a moment later. What the settings are and their limits is
 * App\Support\PrintSettings; this only shows them.
 */

const PAPER_WIDTHS_MM = { A4: 210, Letter: 215.9, Legal: 215.9, A5: 148 };
const SAVE_DELAY_MS = 600;

// A string as a CSS string literal, for the @page margin boxes' `content`.
const cssString = (text) => `"${String(text).replace(/[\\"]/g, '\\$&').replace(/\s+/g, ' ').trim()}"`;

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
            output.textContent = `${settings[output.dataset.output]}${output.dataset.unit ?? 'px'}`;
        }

        for (const field of document.querySelectorAll('[data-shown-with]')) {
            field.hidden = !isOn(settings[field.dataset.shownWith]);
        }
    };

    // The OMR sheet (App\Support\OmrSheet) fills an A4 page with its own
    // frame, or an A5 one with the same frame smaller; margins are what is
    // left around it, so no print setting can move what the scanner reads.
    const omrFrame = data.omrFrame;
    const PAPER_HEIGHTS_MM = { A4: 297, A5: 210 };

    const pageOf = () => {
        if (!omrFrame) {
            return { size: settings.paper_size, top: settings.margin_top, right: settings.margin_right, bottom: settings.margin_bottom, left: settings.margin_left };
        }

        const size = settings.omr_paper_size;
        const scale = size === 'A5' ? omrFrame.a5Scale : 1;
        const side = Math.floor(((PAPER_WIDTHS_MM[size] - omrFrame.width * scale) / 2) * 2) / 2;
        const top = Math.floor(((PAPER_HEIGHTS_MM[size] - omrFrame.height * scale) / 2) * 2) / 2 - 0.5;

        return { size, scale, top, right: side, bottom: top, left: side };
    };

    const applyToPaper = () => {
        const style = paper.style;
        const page = pageOf();
        const margin = `${page.top}mm ${page.right}mm ${page.bottom}mm ${page.left}mm`;

        style.setProperty('--qb-columns', settings.columns);
        style.setProperty('--qb-column-gap', `${settings.column_gap}px`);
        style.setProperty('--qb-question-gap', `${settings.question_gap}px`);
        style.setProperty('--qb-font-size', `${settings.font_size}px`);
        style.setProperty('--qb-font-family', data.fonts[settings.font] ?? 'inherit');
        style.setProperty('--qb-institution-size', `${settings.institution_font_size}px`);
        style.setProperty('--qb-logo-size', `${settings.logo_size}mm`);
        style.setProperty('--qb-text-align', settings.text_align);
        style.setProperty('--qb-paper-width', `${PAPER_WIDTHS_MM[page.size] ?? 210}mm`);
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

        // The name and its logo — on every sheet of an OMR paper.
        const name = data.institutionNames?.[settings.institution_name_id] ?? '';
        // The logo goes with its name: never without it.
        const logoUrl = name !== '' && settings.show_logo ? (data.institutionLogos?.[settings.institution_name_id] ?? '') : '';
        for (const brand of paper.querySelectorAll('[data-print-brand]')) {
            const institution = brand.querySelector('[data-print-institution]');
            institution.textContent = name;
            institution.hidden = name === '';

            const logo = brand.querySelector('[data-print-logo]');
            if (logoUrl !== '' && logo.getAttribute('src') !== logoUrl) {
                logo.src = logoUrl;
            }
            logo.hidden = logoUrl === '';
            brand.dataset.logoPosition = settings.logo_position;
        }

        for (const running of paper.querySelectorAll('[data-print-running]')) {
            running.textContent = settings[running.dataset.printRunning] ?? '';
            running.hidden = running.textContent.trim() === '';
        }

        if (omrFrame) {
            paper.toggleAttribute('data-omr-scale', page.scale !== 1);
            style.setProperty('--qb-omr-scale', String(page.scale));
            style.setProperty('--qb-omr-roll-digits', String(settings.omr_roll_digits));
            // Only the sheet's heading follows the "Text" settings (its size relative to the default 14px).
            style.setProperty('--qb-omr-font', String(settings.font_size / 14));
            style.setProperty('--qb-omr-align', { left: 'flex-start', right: 'flex-end' }[settings.text_align] ?? 'center');
        }

        for (const roll of paper.querySelectorAll('[data-omr-roll]')) {
            roll.hidden = settings.omr_roll_digits === 0;
            for (const column of roll.querySelectorAll('[data-omr-roll-column]')) {
                column.hidden = Number(column.dataset.omrRollColumn) >= settings.omr_roll_digits;
            }
        }

        const marginBox = (position, content) => ` @${position} { content: ${content}; font-size: 9pt; color: #4b5563; font-family: ${data.fonts[settings.font] ?? 'inherit'}; }`;
        // The OMR sheet's margins are too narrow for these, and it numbers its own sheets.
        const pageNumber = settings.page_numbers && !omrFrame ? marginBox(`bottom-${settings.page_number_position}`, `counter(page, ${data.pageNumberStyle})`) : '';
        const header = !omrFrame && (settings.header_text ?? '').trim() !== '' ? marginBox('top-center', cssString(settings.header_text)) : '';
        const footer = !omrFrame && (settings.footer_text ?? '').trim() !== '' ? marginBox('bottom-left', cssString(settings.footer_text)) : '';
        pageRule.textContent = `@page { size: ${page.size}; margin: ${margin};${pageNumber}${header}${footer} }`;

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
        // later change of it reaches this paper too; an emptied running line is none.
        if (key.endsWith('_instructions')) {
            settings[key] = value === data.defaultInstructions[key] ? null : value;
        } else if (key === 'header_text' || key === 'footer_text') {
            settings[key] = value === '' ? null : value;
        } else {
            settings[key] = value;
        }

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
