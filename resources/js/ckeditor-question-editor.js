import {
    BlockQuote,
    Bold,
    ButtonView,
    ClassicEditor,
    Command,
    Essentials,
    Italic,
    Link,
    List,
    Paragraph,
    Plugin,
    toWidget,
    Underline,
    Undo,
    viewToModelPositionOutsideModelElement,
    Widget,
} from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';
import katex from 'katex';
import 'katex/dist/katex.min.css';
import { MathfieldElement } from 'mathlive';
import 'mathlive/fonts.css';
import '../css/ckeditor-question-editor.css';

// Registering the <math-field> custom element also installs its default
// virtual-keyboard sound effects, which try to fetch audio files from
// MathLive's own CDN path — pointless (and a console error) in a modal
// that's only ever used for typing a formula, so turn them off up front.
MathfieldElement.soundsDirectory = null;

function renderMathInto(domElement, latex) {
    try {
        katex.render(latex || '', domElement, { throwOnError: false });
    } catch {
        domElement.textContent = latex;
    }
}

/**
 * Opens a small modal with a MathLive `<math-field>` — a visual math input
 * (its own on-screen math keyboard, live-rendered as you type) — instead of
 * a plain LaTeX text prompt. Scoped to just the equation itself: MathLive
 * never touches the surrounding Bangla prose, which is what the earlier
 * (fully MathLive-based) editor mode got wrong and was dropped for.
 *
 * Resolves `null` on cancel (no change), `''` if the user clears the field /
 * hits "remove" (caller deletes the widget), or the LaTeX string otherwise.
 */
function promptForLatexWithMathLive(initialLatex) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'qb-mathlive-overlay';
        overlay.innerHTML = `
            <div class="qb-mathlive-panel" role="dialog" aria-label="গণিত সমীকরণ">
                <p class="qb-mathlive-panel-title">গণিত সমীকরণ</p>
                <math-field class="qb-mathlive-field"></math-field>
                <div class="qb-mathlive-panel-actions">
                    <button type="button" data-action="remove" class="qb-mathlive-btn qb-mathlive-btn-danger">মুছুন</button>
                    <button type="button" data-action="cancel" class="qb-mathlive-btn qb-mathlive-btn-secondary">বাতিল</button>
                    <button type="button" data-action="insert" class="qb-mathlive-btn qb-mathlive-btn-primary">সংযুক্ত করুন</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const field = overlay.querySelector('math-field');
        field.value = initialLatex || '';

        const close = (result) => {
            document.removeEventListener('keydown', onKeydown, true);
            overlay.remove();
            resolve(result);
        };

        // Capture phase, not bubble: MathLive's <math-field> handles Escape
        // internally (e.g. to close its own popovers) and stops it there,
        // so a bubble-phase document listener never sees it. Capture runs
        // before that, so it always sees the keypress regardless.
        const onKeydown = (event) => {
            if (event.key === 'Escape') {
                close(null);
            }
        };

        document.addEventListener('keydown', onKeydown, true);
        overlay.addEventListener('mousedown', (event) => {
            if (event.target === overlay) {
                close(null);
            }
        });

        overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => close(null));
        overlay.querySelector('[data-action="remove"]').addEventListener('click', () => close(''));
        overlay.querySelector('[data-action="insert"]').addEventListener('click', () => close(field.value));

        requestAnimationFrame(() => field.focus());
    });
}

// One command handles both insert and edit: if a mathTex widget is currently
// selected, the modal edits its latex (clearing it deletes the widget);
// otherwise it inserts a new one at the cursor. This avoids needing a
// fragile DOM double-click → model-element lookup — CKEditor's Widget
// plugin already makes a single click select the whole widget for free.
class MathTexCommand extends Command {
    async execute() {
        const selectedElement = this.editor.model.document.selection.getSelectedElement();
        const isEditingExisting = selectedElement?.is('element', 'mathTex') ?? false;
        const currentLatex = isEditingExisting ? (selectedElement.getAttribute('latex') ?? '') : '';

        const latex = await promptForLatexWithMathLive(currentLatex);

        if (latex === null) {
            return;
        }

        this.editor.model.change((writer) => {
            if (isEditingExisting) {
                if (latex === '') {
                    writer.remove(selectedElement);
                }
                else {
                    writer.setAttribute('latex', latex, selectedElement);
                }

                return;
            }

            if (latex === '') {
                return;
            }

            const mathTex = writer.createElement('mathTex', { latex });
            this.editor.model.insertObject(mathTex, null, null, { setSelection: 'after' });
        });
    }
}

class MathTexEditing extends Plugin {
    static get pluginName() {
        return 'MathTexEditing';
    }

    init() {
        const editor = this.editor;

        editor.model.schema.register('mathTex', {
            inheritAllFrom: '$inlineObject',
            allowAttributes: ['latex'],
        });

        editor.conversion.for('upcast').elementToElement({
            view: { name: 'span', classes: 'qb-katex-embed' },
            model: (viewElement, { writer }) => writer.createElement('mathTex', {
                latex: viewElement.getChild(0)?.data ?? '',
            }),
        });

        editor.conversion.for('editingDowncast').elementToElement({
            model: 'mathTex',
            view: (modelElement, { writer }) => {
                const latex = modelElement.getAttribute('latex') ?? '';
                const mathView = writer.createContainerElement('span', { class: 'qb-katex-embed' });
                const renderElement = writer.createRawElement('span', {}, (domElement) => {
                    renderMathInto(domElement, latex);
                });
                writer.insert(writer.createPositionAt(mathView, 0), renderElement);

                return toWidget(mathView, writer, { label: 'গণিত সমীকরণ' });
            },
        });

        editor.conversion.for('dataDowncast').elementToElement({
            model: 'mathTex',
            view: (modelElement, { writer }) => {
                const latex = modelElement.getAttribute('latex') ?? '';
                const span = writer.createContainerElement('span', { class: 'qb-katex-embed' });
                writer.insert(writer.createPositionAt(span, 0), writer.createText(latex));

                return span;
            },
        });

        editor.editing.mapper.on(
            'viewToModelPosition',
            viewToModelPositionOutsideModelElement(editor.model, (viewElement) => viewElement.hasClass('qb-katex-embed')),
        );

        editor.commands.add('mathTex', new MathTexCommand(editor));
    }
}

class MathTexUI extends Plugin {
    static get pluginName() {
        return 'MathTexUI';
    }

    init() {
        const editor = this.editor;

        editor.ui.componentFactory.add('insertMathTex', (locale) => {
            const button = new ButtonView(locale);

            button.set({
                label: 'গণিত সমীকরণ (√x)',
                withText: true,
                tooltip: 'Insert / edit a math equation (LaTeX)',
            });

            const command = editor.commands.get('mathTex');
            button.bind('isEnabled').to(command);
            button.on('execute', () => editor.execute('mathTex'));

            return button;
        });
    }
}

class MathTex extends Plugin {
    static get requires() {
        return [MathTexEditing, MathTexUI, Widget];
    }

    static get pluginName() {
        return 'MathTex';
    }
}

/**
 * Creates a CKEditor 5 instance on `element` and wires its data changes back
 * out via a `ck-data-changed` CustomEvent (bubbling, `detail.html`) instead
 * of writing to Livewire state directly from inside CKEditor's own closure —
 * routing the write through an Alpine expression on the Blade side
 * (`x-on:ck-data-changed="$wire.$set($statePath, $event.detail.html)"`)
 * keeps this file framework-agnostic and lets the Blade template own exactly
 * how the value reaches Livewire.
 */
window.createQuestionCkEditor = async function createQuestionCkEditor(element, { initialData = '', compact = false } = {}) {
    // Compact mode (MCQ options): same plugin set — so pasted rich content
    // still upcasts cleanly — but a stripped-down toolbar, since a one-line
    // option has no real use for headings/lists/links and a full toolbar
    // would overwhelm its narrow column.
    const toolbar = compact
        ? ['bold', 'italic', '|', 'insertMathTex']
        : [
            'undo', 'redo', '|',
            'bold', 'italic', 'underline', '|',
            'bulletedList', 'numberedList', '|',
            'blockQuote', 'link', '|',
            'insertMathTex',
        ];

    const editor = await ClassicEditor.create(element, {
        licenseKey: 'GPL',
        plugins: [Essentials, Paragraph, Bold, Italic, Underline, Link, List, BlockQuote, Undo, MathTex],
        toolbar,
        initialData: initialData || '',
    });

    editor.model.document.on('change:data', () => {
        element.dispatchEvent(new CustomEvent('ck-data-changed', {
            detail: { html: editor.getData() },
            bubbles: true,
        }));
    });

    return editor;
};

/**
 * Renders every not-yet-rendered `.qb-katex-embed` span under `root` into
 * KaTeX math — reuses the same `katex` import already bundled above for the
 * editing widget, so the admin-side live preview panel (which shows the
 * question as plain persisted HTML, same shape as exam-taking pages) can
 * render it without loading a second copy of KaTeX. Exam-taking pages
 * (Student panel, guest flow) use the separate, much lighter
 * resources/js/katex-embed-renderer.js instead of this file, since they
 * never need the ~1.5MB CKEditor+MathLive bundle.
 */
window.renderKatexEmbeds = function renderKatexEmbeds(root = document) {
    // Livewire's `morph.added`/`morph.updated` hooks pass the exact element
    // that was patched, which can itself be a `.qb-katex-embed` span rather
    // than a container around one — querySelectorAll alone only searches
    // descendants, so it would silently skip that case.
    const isEmbed = root instanceof Element && root.matches('.qb-katex-embed:not(.qb-katex-embed--rendered)');
    const targets = isEmbed ? [root] : root.querySelectorAll('.qb-katex-embed:not(.qb-katex-embed--rendered)');

    targets.forEach((el) => {
        const latex = el.textContent;
        el.classList.add('qb-katex-embed--rendered');
        el.innerHTML = '';
        renderMathInto(el, latex);
    });
};

let renderScheduled = false;

function scheduleRenderKatexEmbeds() {
    if (renderScheduled) {
        return;
    }

    renderScheduled = true;
    requestAnimationFrame(() => {
        renderScheduled = false;
        window.renderKatexEmbeds();
    });
}

document.addEventListener('DOMContentLoaded', scheduleRenderKatexEmbeds);
document.addEventListener('livewire:navigated', scheduleRenderKatexEmbeds);

// Catches content appearing via a Livewire morph (e.g. the preview panel
// re-rendering as $get('question_text')/$get('question_image') change)
// without needing a precise Alpine hook per call site.
new MutationObserver(scheduleRenderKatexEmbeds).observe(document.body, {
    childList: true,
    subtree: true,
});

// Belt-and-suspenders for Filament's action modals (e.g. the Question
// "View" action): their content lives inside a `wire:partial` region
// (see vendor/filament/actions/.../components/modals.blade.php) that
// Livewire patches in place rather than always inserting fresh nodes —
// the MutationObserver above can miss that patch entirely, which is why
// math inside a freshly opened View-question modal was showing as raw
// LaTeX text instead of rendering. Livewire's own `morph.updated`/
// `morph.added` hooks fire for every element Livewire touches during a
// render, so they catch this reliably regardless of the exact DOM diff.
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.added', ({ el }) => window.renderKatexEmbeds(el));
    Livewire.hook('morph.updated', ({ el }) => window.renderKatexEmbeds(el));
});
