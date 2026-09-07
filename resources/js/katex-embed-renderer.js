import katex from 'katex';
import 'katex/dist/katex.min.css';

/**
 * Lightweight companion to ckeditor-question-editor.js — loaded wherever
 * question content is DISPLAYED read-only (exam-taking pages, guest exam
 * flow) rather than edited, so students don't pay for the ~1MB CKEditor
 * bundle just to see rendered math.
 *
 * Renders every not-yet-rendered `.qb-katex-embed` span (the persisted,
 * plain-HTML form: `<span class="qb-katex-embed">latex</span>`) into KaTeX
 * math.
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

        try {
            katex.render(latex || '', el, { throwOnError: false });
        } catch {
            el.textContent = latex;
        }
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

// A MutationObserver (rather than only the two events above) catches
// content that appears via a Livewire morph without a full navigation —
// e.g. a reactive preview panel re-rendering as the user edits a question.
// renderKatexEmbeds() is idempotent (skips already-rendered spans), so
// re-running it on every DOM change is cheap.
new MutationObserver(scheduleRenderKatexEmbeds).observe(document.body, {
    childList: true,
    subtree: true,
});

// Belt-and-suspenders for Filament action modals rendered inside a
// `wire:partial` region, which Livewire can patch in place in a way the
// MutationObserver above misses — see ckeditor-question-editor.js for the
// full explanation. A no-op on pages with no Livewire (the guest exam flow).
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.added', ({ el }) => window.renderKatexEmbeds(el));
    Livewire.hook('morph.updated', ({ el }) => window.renderKatexEmbeds(el));
});
