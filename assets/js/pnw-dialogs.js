(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const config = () => window.PNWUX || {};

    let root = null;
    let resolver = null;
    let previousFocus = null;

    const ensureRoot = () => {
        if (root) {
            return root;
        }
        root = document.createElement('div');
        root.className = 'pnw-confirm-layer';
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML = `
            <div class="pnw-confirm-backdrop" data-pnw-dialog-cancel></div>
            <section class="pnw-confirm-card" role="dialog" aria-modal="true" aria-labelledby="pnw-dialog-title" aria-describedby="pnw-dialog-message">
                <button type="button" class="pnw-confirm-close" data-pnw-dialog-cancel aria-label="Bezárás">×</button>
                <div class="pnw-confirm-visual" data-pnw-dialog-visual aria-hidden="true">!</div>
                <div class="pnw-confirm-copy">
                    <div class="pnw-confirm-kicker" data-pnw-dialog-kicker>Megerősítés</div>
                    <h3 id="pnw-dialog-title" data-pnw-dialog-title>Megerősítés szükséges</h3>
                    <p id="pnw-dialog-message" data-pnw-dialog-message></p>
                    <div class="pnw-confirm-detail" data-pnw-dialog-detail hidden></div>
                </div>
                <div class="pnw-confirm-actions">
                    <button type="button" class="pnw-button pnw-button-secondary" data-pnw-dialog-cancel-button data-pnw-dialog-cancel>Mégse</button>
                    <button type="button" class="pnw-button" data-pnw-dialog-confirm>Folytatás</button>
                </div>
            </section>`;
        document.body.append(root);
        return root;
    };

    const tones = {
        danger: { icon: '!', kicker: 'Fontos művelet', cls: 'pnw-confirm-danger' },
        warning: { icon: '!', kicker: 'Megerősítés', cls: 'pnw-confirm-warning' },
        success: { icon: '✓', kicker: 'Sikeres művelet', cls: 'pnw-confirm-success' },
        info: { icon: 'i', kicker: 'Információ', cls: 'pnw-confirm-info' },
    };

    const closeDialog = (result) => {
        const modal = ensureRoot();
        modal.classList.remove('pnw-confirm-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pnw-dialog-open');
        const resolve = resolver;
        resolver = null;
        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus({ preventScroll: true });
        }
        previousFocus = null;
        resolve?.(Boolean(result));
    };

    const showDialog = (options = {}) => new Promise((resolve) => {
        const modal = ensureRoot();
        resolver?.(false);
        resolver = resolve;
        previousFocus = document.activeElement;

        const tone = options.tone || 'info';
        const meta = tones[tone] || tones.info;
        modal.className = `pnw-confirm-layer ${meta.cls}`;
        $('[data-pnw-dialog-visual]', modal).textContent = options.icon || meta.icon;
        $('[data-pnw-dialog-kicker]', modal).textContent = options.kicker || meta.kicker;
        $('[data-pnw-dialog-title]', modal).textContent = options.title || 'Megerősítés szükséges';
        $('[data-pnw-dialog-message]', modal).textContent = options.message || '';

        const detail = $('[data-pnw-dialog-detail]', modal);
        detail.textContent = options.detail || '';
        detail.hidden = !options.detail;

        const cancel = $('[data-pnw-dialog-cancel-button]', modal);
        cancel.textContent = options.cancelLabel || 'Mégse';
        cancel.hidden = Boolean(options.alertOnly);

        const confirm = $('[data-pnw-dialog-confirm]', modal);
        confirm.textContent = options.confirmLabel || (options.alertOnly ? 'Rendben' : 'Folytatás');
        confirm.className = 'pnw-button';
        if (tone === 'danger') {
            confirm.classList.add('pnw-button-danger');
        } else if (tone === 'success') {
            confirm.classList.add('pnw-button-success');
        }

        modal.classList.add('pnw-confirm-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pnw-dialog-open');
        window.setTimeout(() => confirm.focus(), 20);
    });

    const confirmDialog = (options = {}) => showDialog({ ...options, alertOnly: false });
    const alertDialog = (options = {}) => showDialog({ ...options, alertOnly: true });

    document.addEventListener('click', (event) => {
        if (!root?.classList.contains('pnw-confirm-open')) {
            return;
        }
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }
        if (target.closest('[data-pnw-dialog-confirm]')) {
            event.preventDefault();
            closeDialog(true);
        } else if (target.closest('[data-pnw-dialog-cancel]')) {
            event.preventDefault();
            closeDialog(false);
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (!root?.classList.contains('pnw-confirm-open')) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog(false);
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const buttons = $$('button:not([hidden]):not([disabled])', root);
        if (!buttons.length) {
            return;
        }
        const first = buttons[0];
        const last = buttons[buttons.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const optionsForForm = (message, form) => {
        const text = String(message || 'Biztosan folytatod?');
        const lower = text.toLocaleLowerCase('hu-HU');
        if (lower.includes('archivál')) {
            return {
                tone: 'danger',
                title: 'Hír archiválása',
                message: text,
                detail: 'A hír nem törlődik, később az Archívumból visszaállítható.',
                confirmLabel: 'Archiválás',
            };
        }
        if (lower.includes('visszaállít') || lower.includes('újra megjelenik')) {
            return {
                tone: 'success',
                title: 'Hír visszaállítása',
                message: text,
                confirmLabel: 'Visszaállítás',
            };
        }
        if (lower.includes('lomtár') || lower.includes('töröl')) {
            return {
                tone: 'danger',
                title: 'Piszkozat törlése',
                message: text,
                confirmLabel: 'Törlés',
            };
        }
        return {
            tone: 'warning',
            title: 'Megerősítés szükséges',
            message: text,
            confirmLabel: form?.querySelector('button[type="submit"]')?.textContent?.trim() || 'Folytatás',
        };
    };

    // Existing data-confirm forms are intercepted before the old browser confirm handler.
    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form?.hasAttribute('data-confirm')) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (await confirmDialog(optionsForForm(form.getAttribute('data-confirm'), form))) {
            HTMLFormElement.prototype.submit.call(form);
        }
    }, true);

    const editorContent = (form) => {
        const textarea = $('textarea[name="post_content"]', form);
        if (!textarea) {
            return '';
        }
        const editor = window.tinymce?.get?.(textarea.id);
        return editor ? editor.getContent() : (textarea.value || '');
    };

    const setEditorContent = (form, html) => {
        const textarea = $('textarea[name="post_content"]', form);
        if (!textarea) {
            return;
        }
        const editor = window.tinymce?.get?.(textarea.id);
        if (editor) {
            editor.setContent(html);
            editor.fire('change');
        }
        textarea.value = html;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const templateData = {
        competition: ['Versenyeredmény – ', '<p><strong>Örömmel számolunk be tanulóink eredményéről.</strong></p><h3>A versenyről</h3><p>Írd ide a verseny nevét, helyszínét és időpontját.</p><h3>Eredmények</h3><ul><li>Tanuló neve – elért helyezés / eredmény</li></ul><h3>Felkészítő tanár</h3><p>Írd ide a felkészítő tanár nevét.</p><p>Gratulálunk a résztvevőknek!</p>'],
        event: ['', '<p>Röviden írd le, miről szól az esemény.</p><h3>Mikor?</h3><p>Dátum és időpont.</p><h3>Hol?</h3><p>Helyszín.</p><h3>Kiknek szól?</h3><p>Résztvevők / célcsoport.</p><h3>Program</h3><p>A legfontosabb programok és tudnivalók.</p>'],
        notice: ['Tájékoztató – ', '<p>Írd ide röviden a legfontosabb információt.</p><h3>Fontos tudnivalók</h3><ul><li>Első fontos információ</li><li>Második fontos információ</li></ul><h3>Határidő</h3><p>Ha van határidő, írd ide.</p><h3>Teendő</h3><p>Írd le, mit kell tenni az érintetteknek.</p>'],
        trip: ['', '<p>Rövid bevezető a programról.</p><h3>Helyszín és időpont</h3><p>Írd ide, hol és mikor volt a program.</p><h3>Mi történt?</h3><p>Rövid beszámoló a napról és a legfontosabb élményekről.</p><h3>Résztvevők</h3><p>Írd ide az érintett osztályt, csoportot vagy tanulókat.</p>'],
        application: ['Felhívás – ', '<p>Röviden foglald össze a lehetőséget.</p><h3>Kik jelentkezhetnek?</h3><p>Írd ide a jogosultak körét.</p><h3>Határidő</h3><p>Jelentkezési határidő.</p><h3>Jelentkezés módja</h3><p>Írd le a szükséges lépéseket.</p><h3>További információ</h3><p>Kapcsolattartó vagy további tudnivalók.</p>'],
    };

    // Template confirmations are custom and run before the legacy click listener.
    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-pnw-template-apply]') : null;
        if (!button) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();

        const form = button.closest('.pnw-editor-form');
        const select = button.closest('.pnw-template-panel')?.querySelector('[data-pnw-template-select]');
        const template = templateData[select?.value || ''];
        if (!form || !template) {
            select?.focus();
            return;
        }

        const titleInput = $('input[name="post_title"]', form);
        const currentTitle = titleInput?.value?.trim() || '';
        const holder = document.createElement('div');
        holder.innerHTML = editorContent(form);
        const currentText = (holder.textContent || '').trim();

        if (currentTitle || currentText) {
            const approved = await confirmDialog({
                tone: 'warning',
                title: 'Sablon alkalmazása',
                message: 'A kiválasztott sablon lecserélheti a jelenlegi hír címének vagy szövegének egy részét.',
                detail: 'A módosítás után továbbra is szabadon átírhatod a teljes tartalmat.',
                confirmLabel: 'Sablon alkalmazása',
            });
            if (!approved) {
                return;
            }
        }

        let useTitle = !currentTitle;
        if (currentTitle && template[0]) {
            useTitle = await confirmDialog({
                tone: 'info',
                title: 'Címkezdet használata?',
                message: `A sablon javasolt címkezdete: „${template[0]}”`,
                detail: 'A jelenlegi címed csak akkor változik meg, ha ezt külön jóváhagyod.',
                confirmLabel: 'Igen, használjuk',
                cancelLabel: 'Nem, maradjon a cím',
            });
        }
        if (titleInput && useTitle) {
            titleInput.value = template[0];
            titleInput.dispatchEvent(new Event('input', { bubbles: true }));
            titleInput.focus();
            titleInput.setSelectionRange?.(titleInput.value.length, titleInput.value.length);
        }
        setEditorContent(form, template[1]);
    }, true);

    const storageKey = (postId = 0) => `pnw_draft_${String(config().userId || 0)}_${postId || 'new'}`;
    const clearLocal = (postId = 0) => {
        try {
            localStorage.removeItem(storageKey(postId));
        } catch (error) {
            // Local storage is only an additional safety net.
        }
    };

    const clearClientDraft = (form) => {
        const title = $('input[name="post_title"]', form);
        const excerpt = $('textarea[name="post_excerpt"]', form);
        if (title) {
            title.value = '';
            title.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (excerpt) {
            excerpt.value = '';
            excerpt.dispatchEvent(new Event('input', { bubbles: true }));
        }
        setEditorContent(form, '');
        $$('input[name="post_category[]"]', form).forEach((input) => {
            input.checked = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        const image = $('input[name="featured_image"]', form);
        if (image) {
            image.value = '';
        }
        const live = $('.pnw-image-live', form);
        if (live) {
            live.innerHTML = '<div class="pnw-image-placeholder"><strong>Nincs még kiemelt kép</strong><span>A kiválasztott kép előnézete itt jelenik meg.</span></div>';
        }
        const select = $('[data-pnw-template-select]', form);
        if (select) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
        clearLocal(postId);
        clearLocal(0);
        title?.focus();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Tiszta lap uses the custom danger dialog and then the existing secured AJAX endpoint.
    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-pnw-clean-slate]') : null;
        if (!button) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        const form = button.closest('.pnw-editor-form');
        if (!form) {
            return;
        }

        const holder = document.createElement('div');
        holder.innerHTML = editorContent(form);
        const hasContent = Boolean(
            $('input[name="post_title"]', form)?.value?.trim()
            || $('textarea[name="post_excerpt"]', form)?.value?.trim()
            || (holder.textContent || '').trim()
            || $$('input[name="post_category[]"]:checked', form).length
            || $('input[name="featured_image"]', form)?.files?.length
        );

        if (hasContent && !await confirmDialog({
            tone: 'danger',
            title: 'Tiszta lappal újrakezded?',
            message: 'A jelenlegi cím, szöveg, kivonat, kategóriák és kiválasztott kép eltűnik ebből a piszkozatból.',
            detail: 'A médiatárban már meglévő képfájlokat nem töröljük.',
            confirmLabel: 'Igen, tiszta lap',
        })) {
            return;
        }

        const cfg = config();
        const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Törlés…';

        if (postId && cfg.ajaxUrl && cfg.resetNonce) {
            try {
                const body = new FormData();
                body.append('action', 'pnw_reset_draft');
                body.append('nonce', cfg.resetNonce);
                body.append('post_id', String(postId));
                const response = await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
                const payload = await response.json();
                if (!payload?.success) {
                    throw new Error(payload?.data?.message || 'A piszkozat kiürítése nem sikerült.');
                }
            } catch (error) {
                button.disabled = false;
                button.textContent = original;
                await alertDialog({ tone: 'danger', title: 'A tiszta lap nem sikerült', message: error instanceof Error ? error.message : 'A piszkozat kiürítése nem sikerült.' });
                return;
            }
        }

        clearClientDraft(form);
        button.textContent = '✓ Üres hír';
        window.setTimeout(() => {
            button.textContent = original;
            button.disabled = false;
        }, 1200);
    }, true);

    // Quick draft delete is also fully custom.
    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-pnw-quick-delete]') : null;
        if (!button) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();

        const editLink = button.closest('tr')?.querySelector('a[href*="pnw_view=edit"][href*="post_id="]');
        let postId = 0;
        try {
            postId = editLink ? parseInt(new URL(editLink.href, window.location.href).searchParams.get('post_id') || '0', 10) || 0 : 0;
        } catch (error) {
            postId = 0;
        }
        if (!postId) {
            await alertDialog({ tone: 'danger', title: 'A törlés nem érhető el', message: 'Nem sikerült azonosítani ezt a piszkozatot.' });
            return;
        }

        if (!await confirmDialog({
            tone: 'danger',
            title: 'Piszkozat törlése',
            message: 'Biztosan törlöd ezt a piszkozatot?',
            detail: 'A hír a WordPress Lomtárába kerül, és nem jelenik meg az aktív listában.',
            confirmLabel: 'Piszkozat törlése',
        })) {
            return;
        }

        const cfg = config();
        if (!cfg.ajaxUrl || !cfg.deleteDraftNonce) {
            await alertDialog({ tone: 'danger', title: 'A törlés nem érhető el', message: 'Töltsd újra az oldalt, majd próbáld meg újra.' });
            return;
        }

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Törlés…';
        try {
            const body = new FormData();
            body.append('action', 'pnw_delete_draft');
            body.append('nonce', cfg.deleteDraftNonce);
            body.append('post_id', String(postId));
            const response = await fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
            const payload = await response.json();
            if (!payload?.success) {
                throw new Error(payload?.data?.message || 'A piszkozat törlése nem sikerült.');
            }
            clearLocal(postId);
            const target = new URL(cfg.managerUrl || window.location.href, window.location.href);
            target.searchParams.set('pnw_notice', 'trashed');
            window.location.assign(target.toString());
        } catch (error) {
            button.disabled = false;
            button.textContent = original;
            await alertDialog({ tone: 'danger', title: 'A törlés nem sikerült', message: error instanceof Error ? error.message : 'A piszkozat törlése nem sikerült.' });
        }
    }, true);

    // Any remaining legacy alert() call is visually normalized as well.
    window.alert = (message) => {
        const text = String(message || '');
        const isError = /nem sikerült|nem érhető el|hiba|sikertelen/i.test(text);
        void alertDialog({ tone: isError ? 'danger' : 'info', title: isError ? 'Hiba történt' : 'Információ', message: text, confirmLabel: 'Rendben' });
    };

    window.PNWDialog = { confirm: confirmDialog, alert: alertDialog };
})();
