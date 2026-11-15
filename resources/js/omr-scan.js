/*
 * The OMR scanning page (resources/views/teacher/omr-scan.blade.php;
 * CLAUDE.md rule 10.11): reads sheets from the camera — one after another,
 * each as soon as it is held still — or from chosen photos/scans, lists them
 * for the teacher to check (anything not read clearly is marked), and saves
 * each one's answers to the server, which marks them. Reading is
 * omr-reader.js; nothing but the answers leaves the phone.
 */

import { imageFrom, readSheet, straightened } from './omr-reader.js';

const PREVIEW_PX_PER_MM = 3;
const CAMERA_MAX_SIDE = 1280;
const PHOTO_MAX_SIDE = 2000;
const FRAME_INTERVAL_MS = 250;

const dataElement = document.getElementById('qb-omr-scan-data');

if (dataElement) {
    start(JSON.parse(dataElement.textContent));
}

function start(data) {
    const { layout, text } = data;
    const find = (selector) => document.querySelector(selector);
    const status = find('[data-status]');
    const sheetsSection = find('[data-sheets-section]');
    const sheetsList = find('[data-sheets]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const questionsByKey = new Map(layout.pages.flatMap((page) => page.questions.map((question) => [question.key, { ...question, page: page.index }])));
    const pageCount = layout.pages.length;

    const digits = (value) => (data.banglaDigits ? String(value).replace(/[0-9]/g, (digit) => '০১২৩৪৫৬৭৮৯'[digit]) : String(value));
    const say = (template, values = {}) => template.replace(/:(\w+)/g, (match, key) => (key in values ? values[key] : match));

    const element = (tag, attributes = {}, children = []) => {
        const node = document.createElement(tag);
        for (const [name, value] of Object.entries(attributes)) {
            if (name === 'text') {
                node.textContent = value;
            } else if (name.startsWith('on')) {
                node.addEventListener(name.slice(2), value);
            } else if (value !== false && value !== null && value !== undefined) {
                node.setAttribute(name, value === true ? '' : value);
            }
        }
        node.append(...children.filter(Boolean));
        return node;
    };

    const setStatus = (message) => {
        status.textContent = message;
    };

    /* ---------- The School's class and section ---------- */

    let classId = null;
    let sectionId = null;
    const classSelect = find('[data-class]');

    if (classSelect) {
        const sectionField = find('[data-section-field]');
        const sectionSelect = find('[data-section]');

        for (const schoolClass of data.classes ?? []) {
            classSelect.append(element('option', { value: schoolClass.id, text: schoolClass.name }));
        }

        classSelect.addEventListener('change', () => {
            const schoolClass = (data.classes ?? []).find((candidate) => String(candidate.id) === classSelect.value);
            classId = schoolClass ? schoolClass.id : null;
            sectionId = null;
            sectionSelect.replaceChildren();
            sectionField.hidden = !schoolClass || schoolClass.sections.length === 0;

            if (schoolClass && schoolClass.sections.length > 0) {
                sectionSelect.append(...schoolClass.sections.map((section) => element('option', { value: section.id, text: section.name })));
                sectionId = schoolClass.sections[0].id;
            }
        });

        sectionSelect.addEventListener('change', () => {
            sectionId = Number(sectionSelect.value) || null;
        });
    }

    const classIsChosen = () => !classSelect || classId !== null;

    /* ---------- The sheets read so far ---------- */

    const sheets = [];
    let nextSheetId = 1;

    const signatureOf = (result) => `${result.page}|${result.roll.value}|${result.answers.map((answer) => answer.mark ?? '-').join(',')}`;

    const missingPages = (sheet) => layout.pages.map((page) => page.index).filter((index) => !sheet.pages.has(index));
    const unresolved = (sheet) => [...sheet.answers.values()].filter((answer) => answer.state === 'unclear');
    const isReady = (sheet) => !sheet.saved && !sheet.saving && sheet.roll.trim() !== '' && unresolved(sheet).length === 0 && missingPages(sheet).length === 0;

    /**
     * Adds what was read to the list: a new sheet, or the next page of a
     * roll already there. Nothing when it is a sheet already read.
     */
    const accept = (result, image) => {
        const signature = signatureOf(result);
        const roll = result.roll.unclear ? '' : result.roll.value;

        const known = sheets.find((sheet) => sheet.signatures.has(signature)
            || (roll !== '' && sheet.roll === roll && sheet.pages.has(result.page)));
        if (known) {
            return false;
        }

        let sheet = roll === '' ? null : sheets.find((candidate) => !candidate.saved && candidate.roll === roll && !candidate.pages.has(result.page));

        if (!sheet) {
            sheet = {
                id: nextSheetId++,
                roll,
                rollUnclear: result.roll.unclear || result.roll.value === '',
                name: '',
                pages: new Map(),
                answers: new Map(),
                signatures: new Set(),
                saved: null,
                saving: false,
                error: '',
                showAll: false,
                node: null,
            };
            sheets.unshift(sheet);
        }

        sheet.signatures.add(signature);
        sheet.pages.set(result.page, straightened(image, result.h, layout, PREVIEW_PX_PER_MM));
        for (const answer of result.answers) {
            sheet.answers.set(answer.key, { ...answer, read: answer.state });
        }

        render(sheet);
        return true;
    };

    const remove = (sheet) => {
        sheets.splice(sheets.indexOf(sheet), 1);
        sheet.node?.remove();
        updateList();
    };

    const updateList = () => {
        sheetsSection.hidden = sheets.length === 0;
        find('[data-count]').textContent = `(${digits(sheets.length)})`;
    };

    /* ---------- One sheet's card ---------- */

    const choose = (sheet, answer, mark) => {
        answer.mark = mark;
        answer.state = mark === null ? 'blank' : mark === 'several' ? 'several' : 'ok';
        render(sheet);
    };

    const choiceButtons = (sheet, answer) => {
        const question = questionsByKey.get(answer.key);
        const buttons = question.options.map((option, index) => element('button', {
            type: 'button',
            class: 'qb-omr-choice',
            'aria-pressed': String(answer.state !== 'unclear' && answer.mark === index),
            text: data.letters[index] ?? digits(index + 1),
            disabled: Boolean(sheet.saved),
            onclick: () => choose(sheet, answer, index),
        }));

        buttons.push(element('button', {
            type: 'button',
            class: 'qb-omr-choice',
            'aria-pressed': String(answer.state === 'blank'),
            text: text.blank,
            disabled: Boolean(sheet.saved),
            onclick: () => choose(sheet, answer, null),
        }));

        if (answer.read === 'several') {
            buttons.push(element('button', {
                type: 'button',
                class: 'qb-omr-choice',
                'aria-pressed': String(answer.state === 'several'),
                text: text.several,
                disabled: Boolean(sheet.saved),
                onclick: () => choose(sheet, answer, 'several'),
            }));
        }

        return buttons;
    };

    const answerRow = (sheet, answer) => element('div', {
        class: `flex flex-wrap items-center gap-1.5 rounded-lg px-2 py-1 ${answer.read === 'unclear' ? 'qb-omr-flag--unclear' : answer.read === 'several' ? 'qb-omr-flag--several' : ''}`,
    }, [
        element('span', { class: 'w-24 text-sm font-semibold', text: say(text.question, { number: digits(answer.number) }) }),
        ...choiceButtons(sheet, answer),
        answer.read === 'several' && answer.state === 'several' ? element('span', { class: 'text-xs text-red-700', text: text.severalNote }) : null,
    ]);

    /**
     * The straightened sheet with what was read ringed: green the answer
     * taken, amber what needs a look, red more than one filled.
     */
    const preview = (sheet, pageIndex) => {
        const imageData = sheet.pages.get(pageIndex);
        const canvas = element('canvas', { class: 'qb-omr-preview', width: imageData.width, height: imageData.height });
        const context = canvas.getContext('2d');
        context.putImageData(imageData, 0, 0);
        context.lineWidth = 2;

        const bubble = layout.pages.find((page) => page.index === pageIndex).bubble;
        const ring = ([x, y], colour) => {
            context.strokeStyle = colour;
            context.beginPath();
            context.arc(x * PREVIEW_PX_PER_MM, y * PREVIEW_PX_PER_MM, (bubble / 2 + 0.6) * PREVIEW_PX_PER_MM, 0, Math.PI * 2);
            context.stroke();
        };

        for (const answer of sheet.answers.values()) {
            if (answer.page !== pageIndex) {
                continue;
            }
            const question = questionsByKey.get(answer.key);
            if (answer.state === 'unclear') {
                question.options.forEach((option) => ring(option, '#f59e0b'));
            } else if (answer.state === 'several') {
                question.options.forEach((option, index) => answer.scores[index] >= Math.max(...answer.scores) * 0.6 && ring(option, '#dc2626'));
            } else if (typeof answer.mark === 'number') {
                ring(question.options[answer.mark], '#16a34a');
            }
        }

        return canvas;
    };

    const render = (sheet) => {
        const toCheck = [...sheet.answers.values()].filter((answer) => answer.read === 'unclear' || answer.read === 'several');
        const missing = missingPages(sheet);
        const answers = [...sheet.answers.values()].sort((a, b) => a.number - b.number);

        const card = element('article', { class: 'qb-omr-card flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm', 'data-saved': Boolean(sheet.saved) }, [
            element('div', { class: 'flex flex-wrap items-end gap-2' }, [
                element('label', { class: 'flex flex-col gap-1 text-sm font-medium' }, [
                    text.roll,
                    element('input', {
                        type: 'text',
                        inputmode: 'numeric',
                        value: sheet.roll,
                        class: 'w-28 rounded-lg border border-gray-300 px-2 py-1.5',
                        disabled: Boolean(sheet.saved),
                        oninput: (event) => {
                            sheet.roll = event.target.value;
                            sheet.rollUnclear = false;
                        },
                        onchange: () => render(sheet),
                    }),
                ]),
                element('label', { class: 'flex flex-1 flex-col gap-1 text-sm font-medium' }, [
                    text.name,
                    element('input', {
                        type: 'text',
                        value: sheet.name,
                        class: 'min-w-0 rounded-lg border border-gray-300 px-2 py-1.5',
                        disabled: Boolean(sheet.saved),
                        oninput: (event) => {
                            sheet.name = event.target.value;
                        },
                    }),
                ]),
                sheet.saved ? null : element('button', { type: 'button', class: 'text-sm text-red-600 hover:underline', text: text.remove, onclick: () => remove(sheet) }),
            ]),
            sheet.rollUnclear && !sheet.saved ? element('p', { class: 'rounded-lg bg-amber-50 px-3 py-1.5 text-sm text-amber-800', text: sheet.roll === '' ? text.needsRoll : text.rollUnclear }) : null,
            missing.length > 0 ? element('p', { class: 'rounded-lg bg-amber-50 px-3 py-1.5 text-sm text-amber-800', text: say(text.missingPages, { pages: missing.map((index) => digits(index + 1)).join(', ') }) }) : null,
            element('div', { class: 'flex flex-wrap gap-2' }, [...sheet.pages.keys()].sort().map((index) => preview(sheet, index))),
            element('p', {
                class: `text-sm font-medium ${unresolved(sheet).length > 0 ? 'text-amber-700' : 'text-gray-600'}`,
                text: toCheck.length > 0 ? say(text.needsCheck, { count: digits(unresolved(sheet).length) }) : text.allGood,
            }),
            toCheck.length > 0 ? element('div', { class: 'flex flex-col gap-1' }, toCheck.sort((a, b) => a.number - b.number).map((answer) => answerRow(sheet, answer))) : null,
            element('button', {
                type: 'button',
                class: 'self-start text-sm font-medium text-blue-700 hover:underline',
                text: sheet.showAll ? text.hideAll : text.showAll,
                onclick: () => {
                    sheet.showAll = !sheet.showAll;
                    render(sheet);
                },
            }),
            sheet.showAll ? element('div', { class: 'flex flex-col gap-1' }, answers.map((answer) => answerRow(sheet, answer))) : null,
            element('div', { class: 'flex flex-wrap items-center gap-3' }, [
                sheet.saved
                    ? element('p', { class: 'text-sm font-semibold text-green-700', text: say(text.saved, { score: digits(sheet.saved.score), total: digits(sheet.saved.out_of) }) })
                    : element('button', {
                        type: 'button',
                        class: 'qb-btn qb-btn--primary text-sm',
                        text: sheet.saving ? text.saving : text.save,
                        disabled: !isReady(sheet),
                        onclick: () => save(sheet),
                    }),
                sheet.error ? element('p', { class: 'text-sm text-red-700', text: sheet.error }) : null,
            ]),
        ]);

        if (sheet.node) {
            sheet.node.replaceWith(card);
        } else {
            sheetsList.prepend(card);
        }
        sheet.node = card;
        updateList();
    };

    /* ---------- Saving ---------- */

    const save = async (sheet) => {
        if (!isReady(sheet)) {
            return;
        }
        if (!classIsChosen()) {
            sheet.error = text.chooseClass;
            render(sheet);
            return;
        }

        sheet.saving = true;
        sheet.error = '';
        render(sheet);

        const answers = {};
        for (const answer of sheet.answers.values()) {
            answers[answer.key] = answer.mark;
        }

        try {
            const response = await fetch(data.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    roll: sheet.roll,
                    name: sheet.name,
                    answers,
                    ...(classSelect ? { class_id: classId, section_id: sectionId } : {}),
                }),
            });
            const body = await response.json().catch(() => ({}));

            if (response.ok) {
                sheet.saved = body;
                sheet.roll = body.roll ?? sheet.roll;
            } else {
                sheet.error = Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? text.failed;
            }
        } catch {
            sheet.error = text.failed;
        }

        sheet.saving = false;
        render(sheet);
    };

    find('[data-save-all]').addEventListener('click', async () => {
        for (const sheet of [...sheets].reverse()) {
            if (isReady(sheet)) {
                await save(sheet);
            }
        }
    });

    /* ---------- The camera ---------- */

    const cameraBox = find('[data-camera]');
    const video = find('[data-video]');
    const overlay = find('[data-overlay]');
    const cameraToggle = find('[data-camera-toggle]');
    let stream = null;
    let audio = null;
    let previousSignature = null;
    let framesWithoutSheet = 0;

    const beep = () => {
        navigator.vibrate?.(80);
        if (!audio) {
            return;
        }
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        oscillator.frequency.value = 880;
        gain.gain.value = 0.15;
        oscillator.connect(gain).connect(audio.destination);
        oscillator.start();
        oscillator.stop(audio.currentTime + 0.12);
    };

    const flash = () => {
        cameraBox.dataset.flash = '';
        setTimeout(() => delete cameraBox.dataset.flash, 400);
    };

    const drawOverlay = (image, result) => {
        overlay.width = image.width;
        overlay.height = image.height;
        const context = overlay.getContext('2d');
        context.clearRect(0, 0, image.width, image.height);
        if (!result) {
            return;
        }
        context.strokeStyle = '#10b981';
        context.lineWidth = Math.max(3, image.width / 300);
        context.beginPath();
        result.corners.forEach(([x, y], index) => (index === 0 ? context.moveTo(x, y) : context.lineTo(x, y)));
        context.closePath();
        context.stroke();
    };

    const scanFrame = () => {
        if (!stream) {
            return;
        }

        if (video.videoWidth > 0) {
            const image = imageFrom(video, video.videoWidth, video.videoHeight, CAMERA_MAX_SIDE);
            const result = readSheet(image, layout);
            drawOverlay(image, result);

            if (!result) {
                previousSignature = null;
                framesWithoutSheet++;
                setStatus(framesWithoutSheet > 12 ? text.notThisExam : text.searching);
            } else {
                framesWithoutSheet = 0;
                const signature = signatureOf(result);
                // Held still: the same reading twice running.
                if (signature === previousSignature) {
                    if (accept(result, image)) {
                        beep();
                        flash();
                        setStatus(say(text.read, { roll: digits(result.roll.value || '?') }));
                    } else {
                        setStatus(text.alreadyRead);
                    }
                }
                previousSignature = signature;
            }
        }

        setTimeout(scanFrame, FRAME_INTERVAL_MS);
    };

    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        cameraBox.hidden = true;
        cameraToggle.textContent = text.startCamera;
        setStatus('');
    };

    cameraToggle.addEventListener('click', async () => {
        if (stream) {
            stopCamera();
            return;
        }
        if (!classIsChosen()) {
            setStatus(text.chooseClass);
            return;
        }

        audio ??= window.AudioContext ? new AudioContext() : null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            });
        } catch {
            setStatus(text.cameraFailed);
            return;
        }

        video.srcObject = stream;
        await video.play();
        cameraBox.hidden = false;
        cameraToggle.textContent = text.stopCamera;
        setStatus(text.searching);
        scanFrame();
    });

    /* ---------- Chosen photos / scans ---------- */

    find('[data-files]').addEventListener('change', async (event) => {
        if (!classIsChosen()) {
            setStatus(text.chooseClass);
            event.target.value = '';
            return;
        }

        const notFound = [];
        for (const file of event.target.files) {
            setStatus(text.searching);
            try {
                const bitmap = await createImageBitmap(file);
                const image = imageFrom(bitmap, bitmap.width, bitmap.height, PHOTO_MAX_SIDE);
                bitmap.close?.();
                const result = readSheet(image, layout);
                if (result) {
                    accept(result, image);
                } else {
                    notFound.push(file.name);
                }
            } catch {
                notFound.push(file.name);
            }
            // Let the page draw between photos.
            await new Promise((resolve) => setTimeout(resolve, 0));
        }

        setStatus(notFound.map((name) => say(text.noSheetInPhoto, { file: name })).join(' '));
        event.target.value = '';
    });
}
