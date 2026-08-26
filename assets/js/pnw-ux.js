(() => {
    'use strict';

    const config = window.PNWUX || {};
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const editorContent = (form) => {
        const textarea = $('textarea[name="post_content"]', form);
        if (!textarea) {
            return '';
        }
        if (window.tinymce && typeof window.tinymce.get === 'function') {
            const editor = window.tinymce.get(textarea.id);
            if (editor) {
                return editor.getContent();
            }
        }
        return textarea.value || '';
    };

    const setEditorContent = (form, html) => {
        const textarea = $('textarea[name="post_content"]', form);
        if (!textarea) {
            return;
        }
        if (window.tinymce && typeof window.tinymce.get === 'function') {
            const editor = window.tinymce.get(textarea.id);
            if (editor) {
                editor.setContent(html);
                editor.fire('change');
            }
        }
        textarea.value = html;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const collectDraft = (form) => ({
        postId: parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0,
        title: $('input[name="post_title"]', form)?.value || '',
        content: editorContent(form),
        excerpt: $('textarea[name="post_excerpt"]', form)?.value || '',
        categories: $$('input[name="post_category[]"]:checked', form).map((input) => input.value),
        savedLocalAt: Date.now(),
    });

    const fingerprint = (draft) => JSON.stringify({
        postId: draft.postId,
        title: draft.title,
        content: draft.content,
        excerpt: draft.excerpt,
        categories: draft.categories,
    });

    const hasMeaningfulContent = (draft) => {
        const plain = document.createElement('div');
        plain.innerHTML = draft.content || '';
        return Boolean(
            draft.title.trim()
            || (plain.textContent || '').trim()
            || draft.excerpt.trim()
            || draft.categories.length
        );
    };

    const storageKey = (postId = 0) => `pnw_draft_${String(config.userId || 0)}_${postId || 'new'}`;

    const saveLocal = (draft) => {
        try {
            localStorage.setItem(storageKey(draft.postId), JSON.stringify(draft));
        } catch (error) {
            // Local storage is an extra safety net. A browser may block it.
        }
    };

    const clearLocal = (postId = 0) => {
        try {
            localStorage.removeItem(storageKey(postId));
        } catch (error) {
            // Ignore browser storage restrictions.
        }
    };

    const loadLocalNewDraft = () => {
        try {
            const raw = localStorage.getItem(storageKey(0));
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.savedLocalAt || Date.now() - parsed.savedLocalAt > 7 * 24 * 60 * 60 * 1000) {
                clearLocal(0);
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    };

    const createAutosaveBar = (form) => {
        const bar = document.createElement('div');
        bar.className = 'pnw-autosave-bar';
        bar.innerHTML = '<span class="pnw-autosave-dot" aria-hidden="true"></span><strong>Automatikus mentés</strong><span data-pnw-autosave-status>aktív</span>';
        const firstField = $('.pnw-field', form);
        if (firstField) {
            firstField.insertAdjacentElement('beforebegin', bar);
        } else {
            form.prepend(bar);
        }
        return bar;
    };

    const setAutosaveStatus = (bar, text, state = '') => {
        const target = $('[data-pnw-autosave-status]', bar);
        if (target) {
            target.textContent = text;
        }
        bar.dataset.state = state;
    };

    const restoreLocalDraft = (form, draft, banner) => {
        const title = $('input[name="post_title"]', form);
        const excerpt = $('textarea[name="post_excerpt"]', form);
        if (title) {
            title.value = draft.title || '';
        }
        if (excerpt) {
            excerpt.value = draft.excerpt || '';
        }
        setEditorContent(form, draft.content || '');
        $$('input[name="post_category[]"]', form).forEach((input) => {
            input.checked = Array.isArray(draft.categories) && draft.categories.includes(input.value);
        });
        banner.remove();
    };

    const offerLocalRecovery = (form) => {
        const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
        if (postId > 0) {
            return;
        }

        const current = collectDraft(form);
        if (hasMeaningfulContent(current)) {
            return;
        }

        const draft = loadLocalNewDraft();
        if (!draft || !hasMeaningfulContent(draft)) {
            return;
        }

        const banner = document.createElement('div');
        banner.className = 'pnw-recovery-banner';
        banner.innerHTML = `
            <div><strong>Találtunk egy félbehagyott hírt.</strong><span>Vissza tudod tölteni a legutóbbi helyi biztonsági mentést.</span></div>
            <div class="pnw-recovery-actions">
                <button type="button" class="pnw-button pnw-button-secondary" data-pnw-recovery-discard>Elvetés</button>
                <button type="button" class="pnw-button" data-pnw-recovery-restore>Visszaállítás</button>
            </div>`;
        form.prepend(banner);

        $('[data-pnw-recovery-restore]', banner)?.addEventListener('click', () => restoreLocalDraft(form, draft, banner));
        $('[data-pnw-recovery-discard]', banner)?.addEventListener('click', () => {
            clearLocal(0);
            banner.remove();
        });
    };

    const postAutosave = async (form, bar, draft) => {
        if (!config.ajaxUrl || !config.autosaveNonce || draft.categories.length === 0) {
            if (draft.categories.length === 0) {
                setAutosaveStatus(bar, 'helyben mentve – válassz kategóriát a rendszermentéshez', 'waiting');
            }
            return null;
        }

        const data = new FormData();
        data.append('action', 'pnw_autosave_news');
        data.append('nonce', config.autosaveNonce);
        data.append('post_id', String(draft.postId || 0));
        data.append('post_title', draft.title);
        data.append('post_content', draft.content);
        data.append('post_excerpt', draft.excerpt);
        draft.categories.forEach((category) => data.append('post_category[]', String(category)));

        setAutosaveStatus(bar, 'mentés…', 'saving');

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
            });
            const payload = await response.json();
            if (!payload?.success || !payload?.data?.postId) {
                throw new Error(payload?.data?.message || 'Az automatikus mentés most nem sikerült.');
            }

            const newId = parseInt(payload.data.postId, 10) || 0;
            const hiddenId = $('input[name="post_id"]', form);
            const oldId = draft.postId;
            if (hiddenId && newId > 0) {
                hiddenId.value = String(newId);
            }
            if (oldId === 0 && newId > 0) {
                clearLocal(0);
                if (payload.data.editUrl && window.history?.replaceState) {
                    window.history.replaceState({}, '', payload.data.editUrl);
                }
            }

            setAutosaveStatus(bar, `mentve ${payload.data.savedAt || ''}`.trim(), 'saved');
            return { ...draft, postId: newId || draft.postId };
        } catch (error) {
            setAutosaveStatus(bar, 'helyi mentés rendben, a rendszermentést újrapróbáljuk', 'error');
            return null;
        }
    };

    const installAutosave = () => {
        const form = $('.pnw-editor-form input[name="action"][value="pnw_save_news"]')?.closest('form');
        if (!form) {
            return;
        }

        const bar = createAutosaveBar(form);
        offerLocalRecovery(form);
        let lastLocalFingerprint = '';
        let lastServerFingerprint = '';
        let saving = false;
        let submitting = false;

        const localTick = () => {
            if (submitting) {
                return;
            }
            const draft = collectDraft(form);
            if (!hasMeaningfulContent(draft)) {
                return;
            }
            const current = fingerprint(draft);
            if (current !== lastLocalFingerprint) {
                saveLocal(draft);
                lastLocalFingerprint = current;
            }
        };

        const serverTick = async () => {
            if (submitting || saving) {
                return;
            }
            const draft = collectDraft(form);
            if (!hasMeaningfulContent(draft)) {
                return;
            }
            const current = fingerprint(draft);
            if (current === lastServerFingerprint) {
                return;
            }
            if (draft.categories.length === 0) {
                setAutosaveStatus(bar, 'helyben mentve – válassz kategóriát a rendszermentéshez', 'waiting');
                return;
            }

            saving = true;
            const saved = await postAutosave(form, bar, draft);
            saving = false;
            if (saved) {
                lastServerFingerprint = fingerprint(saved);
                lastLocalFingerprint = lastServerFingerprint;
            }
        };

        form.addEventListener('submit', () => {
            submitting = true;
            const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
            clearLocal(postId);
            clearLocal(0);
        });

        window.setInterval(localTick, 3000);
        window.setInterval(serverTick, Math.max(15000, parseInt(config.autosaveInterval || 30000, 10)));
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                localTick();
            }
        });
        window.addEventListener('beforeunload', localTick);
    };

    const templates = {
        competition: {
            name: 'Versenyeredmény',
            description: 'Verseny, helyezés, résztvevők és felkészítő tanár.',
            title: 'Versenyeredmény – ',
            html: '<p><strong>Örömmel számolunk be tanulóink eredményéről.</strong></p><h3>A versenyről</h3><p>Írd ide a verseny nevét, helyszínét és időpontját.</p><h3>Eredmények</h3><ul><li>Tanuló neve – elért helyezés / eredmény</li></ul><h3>Felkészítő tanár</h3><p>Írd ide a felkészítő tanár nevét.</p><p>Gratulálunk a résztvevőknek!</p>',
        },
        event: {
            name: 'Esemény / rendezvény',
            description: 'Iskolai program, nyílt nap, előadás vagy rendezvény.',
            title: '',
            html: '<p>Röviden írd le, miről szól az esemény.</p><h3>Mikor?</h3><p>Dátum és időpont.</p><h3>Hol?</h3><p>Helyszín.</p><h3>Kiknek szól?</h3><p>Résztvevők / célcsoport.</p><h3>Program</h3><p>A legfontosabb programok és tudnivalók.</p>',
        },
        notice: {
            name: 'Tájékoztató',
            description: 'Rövid, jól áttekinthető információ és teendők.',
            title: 'Tájékoztató – ',
            html: '<p>Írd ide röviden a legfontosabb információt.</p><h3>Fontos tudnivalók</h3><ul><li>Első fontos információ</li><li>Második fontos információ</li></ul><h3>Határidő</h3><p>Ha van határidő, írd ide.</p><h3>Teendő</h3><p>Írd le, mit kell tenni az érintetteknek.</p>',
        },
        trip: {
            name: 'Kirándulás / programbeszámoló',
            description: 'Beszámoló osztály- vagy iskolai programról.',
            title: '',
            html: '<p>Rövid bevezető a programról.</p><h3>Helyszín és időpont</h3><p>Írd ide, hol és mikor volt a program.</p><h3>Mi történt?</h3><p>Rövid beszámoló a napról és a legfontosabb élményekről.</p><h3>Résztvevők</h3><p>Írd ide az érintett osztályt, csoportot vagy tanulókat.</p>',
        },
        application: {
            name: 'Pályázat / felhívás',
            description: 'Jelentkezési feltételek, határidő és szükséges teendők.',
            title: 'Felhívás – ',
            html: '<p>Röviden foglald össze a lehetőséget.</p><h3>Kik jelentkezhetnek?</h3><p>Írd ide a jogosultak körét.</p><h3>Határidő</h3><p>Jelentkezési határidő.</p><h3>Jelentkezés módja</h3><p>Írd le a szükséges lépéseket.</p><h3>További információ</h3><p>Kapcsolattartó vagy további tudnivalók.</p>',
        },
    };

    const installTemplates = () => {
        const form = $('.pnw-editor-form input[name="action"][value="pnw_save_news"]')?.closest('form');
        if (!form || $('.pnw-template-panel', form)) {
            return;
        }
        const firstField = $('.pnw-field', form);
        if (!firstField) {
            return;
        }

        const panel = document.createElement('div');
        panel.className = 'pnw-template-panel';
        panel.innerHTML = `
            <div class="pnw-template-copy"><span class="pnw-template-icon" aria-hidden="true">✦</span><div><strong>Hírsablonok</strong><span>Válassz egy típust, és előkészítjük a hír szerkezetét.</span></div></div>
            <div class="pnw-template-controls">
                <select data-pnw-template-select aria-label="Hírsablon kiválasztása">
                    <option value="">— Válassz sablont —</option>
                    ${Object.entries(templates).map(([key, item]) => `<option value="${key}">${item.name}</option>`).join('')}
                </select>
                <button type="button" class="pnw-button pnw-button-secondary" data-pnw-template-apply>Sablon alkalmazása</button>
            </div>
            <small data-pnw-template-description>Nem kötelező sablont használni.</small>`;
        firstField.insertAdjacentElement('beforebegin', panel);

        const select = $('[data-pnw-template-select]', panel);
        const description = $('[data-pnw-template-description]', panel);
        select?.addEventListener('change', () => {
            const item = templates[select.value];
            if (description) {
                description.textContent = item?.description || 'Nem kötelező sablont használni.';
            }
        });

        $('[data-pnw-template-apply]', panel)?.addEventListener('click', () => {
            const key = select?.value || '';
            const template = templates[key];
            if (!template) {
                select?.focus();
                return;
            }

            const current = collectDraft(form);
            if ((current.title.trim() || current.content.replace(/<[^>]+>/g, '').trim()) && !window.confirm('A sablon a jelenlegi cím/szöveg egy részét lecserélheti. Biztosan alkalmazod?')) {
                return;
            }

            const title = $('input[name="post_title"]', form);
            if (title && (!title.value.trim() || window.confirm('A sablonhoz tartozó címkezdetet is beírjuk?'))) {
                title.value = template.title;
                title.focus();
                title.setSelectionRange(title.value.length, title.value.length);
            }
            setEditorContent(form, template.html);
        });
    };

    const installScheduling = () => {
        $$('.pnw-decision-approve').forEach((form) => {
            if ($('.pnw-schedule-box', form)) {
                return;
            }
            const submit = $('button[type="submit"]', form);
            const note = $('textarea[name="review_note"]', form);
            if (!submit || !note) {
                return;
            }

            const box = document.createElement('div');
            box.className = 'pnw-schedule-box';
            box.innerHTML = `
                <strong>Megjelenés időpontja</strong>
                <label class="pnw-schedule-choice"><input type="radio" name="publish_mode" value="now" checked> <span><b>Azonnal</b><small>A jóváhagyás után rögtön megjelenik.</small></span></label>
                <label class="pnw-schedule-choice"><input type="radio" name="publish_mode" value="schedule"> <span><b>Időzítve</b><small>Most jóváhagyod, de csak a megadott időpontban jelenik meg.</small></span></label>
                <div class="pnw-schedule-fields" data-pnw-schedule-fields hidden>
                    <label>Publikálás időpontja <input type="datetime-local" name="publish_at" min="${String(config.scheduleMin || '')}"></label>
                    <small>Időzóna: ${String(config.timezone || 'Europe/Budapest')}</small>
                    <div class="pnw-schedule-error" data-pnw-schedule-error hidden>Adj meg egy jövőbeli időpontot.</div>
                </div>`;
            note.insertAdjacentElement('beforebegin', box);

            const fields = $('[data-pnw-schedule-fields]', box);
            const dateInput = $('input[name="publish_at"]', box);
            const error = $('[data-pnw-schedule-error]', box);
            const updateMode = () => {
                const mode = $('input[name="publish_mode"]:checked', box)?.value || 'now';
                const scheduled = mode === 'schedule';
                fields.hidden = !scheduled;
                submit.textContent = scheduled ? '✓ Jóváhagyás és időzítés' : '✓ Jóváhagyás és publikálás';
                if (!scheduled && error) {
                    error.hidden = true;
                }
            };
            $$('input[name="publish_mode"]', box).forEach((radio) => radio.addEventListener('change', updateMode));
            updateMode();

            form.addEventListener('submit', (event) => {
                const mode = $('input[name="publish_mode"]:checked', box)?.value || 'now';
                if (mode !== 'schedule') {
                    return;
                }
                if (!dateInput?.value) {
                    event.preventDefault();
                    if (error) {
                        error.hidden = false;
                    }
                    dateInput?.focus();
                    dateInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, true);
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        installTemplates();
        installAutosave();
        installScheduling();
    });
})();
