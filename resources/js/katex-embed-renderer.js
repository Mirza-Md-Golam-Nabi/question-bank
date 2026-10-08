import { installKatexEmbedRenderer } from './katex-embeds';

/**
 * Lightweight companion to ckeditor-question-editor.js — loaded wherever
 * question content is DISPLAYED read-only (exam-taking pages, guest exam
 * flow, printable paper) rather than edited, so students don't pay for the
 * ~1.5MB CKEditor bundle just to see rendered math. The rendering itself
 * lives in katex-embeds.js, shared with the editor bundle.
 */
installKatexEmbedRenderer();
