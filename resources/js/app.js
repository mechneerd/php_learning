import mermaid from 'mermaid';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import plaintext from 'highlight.js/lib/languages/plaintext';
import xml from 'highlight.js/lib/languages/xml';
import { basicSetup, EditorView } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { php as phpLanguage } from '@codemirror/lang-php';

hljs.registerLanguage('php', php);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('json', json);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('plaintext', plaintext);

mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'strict',
    theme: 'neutral',
});

let mermaidSequence = 0;

const highlight = (root) => {
    (root ?? document).querySelectorAll('pre code').forEach((el) => {
        if (el.dataset.highlighted !== 'yes') {
            hljs.highlightElement(el);
        }
    });
};

const renderMermaid = async (root) => {
    const nodes = (root ?? document).querySelectorAll('.mermaid:not([data-processed])');

    for (const el of nodes) {
        el.setAttribute('data-processed', 'pending');

        try {
            const { svg } = await mermaid.render(`mermaid-${++mermaidSequence}`, el.textContent ?? '');
            el.innerHTML = svg;
            el.setAttribute('data-processed', 'yes');
        } catch (error) {
            console.warn('Mermaid render failed', error);
            el.setAttribute('data-processed', 'failed');
        }
    }
};

const init = (root) => {
    highlight(root);
    renderMermaid(root);
};

window.courseKit = { highlight, mermaid: renderMermaid, init };

// CodeMirror 6 editors for practice answers. Each [data-php-editor] textarea
// is mirrored into a wire:ignore container; the textarea keeps wire:model
// sync and gets its value replaced by Livewire morphs (reset / exercise
// switch), which we push back into the editor here.
const editorTheme = EditorView.theme({
    '&': { backgroundColor: 'transparent', fontSize: '0.875rem' },
    '.cm-content': {
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
        caretColor: 'currentColor',
        padding: '0.5rem 0',
    },
    '.cm-gutters': { backgroundColor: 'transparent', border: 'none' },
    '.cm-scroller': { overflow: 'auto' },
});

/** @type {Map<HTMLTextAreaElement, import('@codemirror/view').EditorView>} */
const editors = new Map();

const initEditors = (root) => {
    (root ?? document).querySelectorAll('[data-php-editor]').forEach((textarea) => {
        if (editors.has(textarea)) {
            return;
        }

        const target = textarea.closest('.editor-host')?.querySelector('[data-php-editor-target]');

        if (!target) {
            return;
        }

        try {
            const view = new EditorView({
                state: EditorState.create({
                    doc: textarea.value,
                    extensions: [
                        basicSetup,
                        phpLanguage(),
                        editorTheme,
                        EditorView.updateListener.of((update) => {
                            if (update.docChanged) {
                                textarea.value = update.state.doc.toString();
                            }
                        }),
                    ],
                }),
                parent: target,
            });

            editors.set(textarea, view);
        } catch (error) {
            console.warn('CodeMirror init failed', error);
        }
    });
};

const syncEditors = () => {
    document.querySelectorAll('[data-php-editor]').forEach((textarea) => {
        const view = editors.get(textarea);

        if (!view) {
            return;
        }

        const current = view.state.doc.toString();

        if (textarea.value !== current) {
            view.dispatch({ changes: { from: 0, to: current.length, insert: textarea.value } });
        }
    });
};

const pruneEditors = () => {
    editors.forEach((view, textarea) => {
        if (!document.contains(textarea)) {
            view.destroy();
            editors.delete(textarea);
        }
    });
};

const registerMorphHook = () => {
    if (window.Livewire && !window.__cmMorphHook) {
        window.__cmMorphHook = true;
        window.Livewire.hook('morph.updated', () => {
            window.setTimeout(syncEditors, 0);
        });
    }
};

document.addEventListener('livewire:init', registerMorphHook);
registerMorphHook();

// Focused-reading heartbeat: fires the Livewire `lesson-tick` event every
// 15s while the tab is visible (only LessonView listens server-side).
const TICK_SECONDS = 15;

const dispatchTick = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    window.dispatchEvent(new CustomEvent('lesson-tick', { detail: {}, bubbles: true }));
};

setInterval(dispatchTick, TICK_SECONDS * 1000);
document.addEventListener('visibilitychange', dispatchTick);

const start = () => {
    pruneEditors();
    initEditors(document);
    init(document);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}

document.addEventListener('livewire:navigated', start);
