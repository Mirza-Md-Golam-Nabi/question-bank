import katex from 'katex';
import 'katex/dist/katex.min.css';

/**
 * Rendering of the math embedded in question content — shared by both
 * bundles that show questions: the full editor bundle
 * (ckeditor-question-editor.js: Admin/Teacher/Staff panels) and the
 * lightweight read-only one (katex-embed-renderer.js: Student panel, guest
 * exam flow, printable paper). Math is persisted as plain HTML,
 * `<span class="qb-katex-embed">latex</span>`.
 */

/**
 * Renders one LaTeX string into an element, falling back to showing the raw
 * LaTeX if KaTeX can't parse it.
 */
export function renderMathInto(domElement, latex) {
    try {
        katex.render(latex || '', domElement, { throwOnError: false });
    } catch {
        domElement.textContent = latex;
    }
}

/**
 * Renders every not-yet-rendered `.qb-katex-embed` span under `root`.
 * Idempotent: an already-rendered span is skipped, so calling it again on
 * every DOM change is cheap.
 */
export function renderKatexEmbeds(root = document) {
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
}

/**
 * Keeps every embed on the page rendered for as long as the page lives:
 * on load, after Livewire navigations, and whenever content appears or
 * changes later. Call once per page.
 */
export function installKatexEmbedRenderer() {
    window.renderKatexEmbeds = renderKatexEmbeds;

    let renderScheduled = false;

    const scheduleRender = () => {
        if (renderScheduled) {
            return;
        }

        renderScheduled = true;
        requestAnimationFrame(() => {
            renderScheduled = false;
            renderKatexEmbeds();
        });
    };

    document.addEventListener('DOMContentLoaded', scheduleRender);
    document.addEventListener('livewire:navigated', scheduleRender);

    // A MutationObserver (rather than only the two events above) catches
    // content that appears via a Livewire morph without a full navigation —
    // e.g. a reactive preview panel re-rendering as the user edits a
    // question, or a new page of questions in the question picker.
    new MutationObserver(scheduleRender).observe(document.body, {
        childList: true,
        subtree: true,
    });

    // Belt-and-suspenders for Filament's action modals (e.g. the Question
    // "View" action): their content lives inside a `wire:partial` region
    // that Livewire patches in place rather than always inserting fresh
    // nodes, which the MutationObserver above can miss — math in a freshly
    // opened modal then showed as raw LaTeX. Livewire's own morph hooks
    // fire for every element it touches, so they catch it reliably. A
    // no-op on pages without Livewire (the guest exam flow).
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.added', ({ el }) => renderKatexEmbeds(el));
        Livewire.hook('morph.updated', ({ el }) => renderKatexEmbeds(el));
    });
}
