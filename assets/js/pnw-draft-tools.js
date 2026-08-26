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

    const storageKey = (postId = 0) => `pnw_draft_${String(config.userId || 0)}_${postId || 'new'}`;

    const clearLocal = (postId = 0) => {
        try {
            localStorage.removeItem(storageKey(postId));
        } catch (error) {
            // A böngésző blokkolhatja a localStorage használatát.
        }
    };

    const hasContent = (form) => {
        const title = $('input[name="post_title"]', form)?.value || '';
        const excerpt = $('textarea[name="post_excerpt"]', form)?.value || '';
        const wrapper = document.createElement('div');
        wrapper.innerHTML = editorContent(form);
        return Boolean(
            title.trim()
            || excerpt.trim()
            || (wrapper.textContent || '').trim()
            || $$('input[name="post_category[]"]:checked', form).length
            || $('input[name="featured_image"]', form)?.files?.length
        );
    };

    const resetImagePicker = (form) => {
        const input = $('input[name="featured_image"]', form);
        if (input) {
            input.value = '';
        }

        const live = $('.pnw-image-live', form);
        if (live) {
            live.innerHTML = '<div class="pnw-image-placeholder"><strong>Nincs még kiemelt kép</strong><span>A kiválasztott kép előnézete itt jelenik meg.</span></div>';
            live.dataset.currentSrc = '';
        }
    };

    const clearClientForm = (form) => {
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
        resetImagePicker(form);

        const select = $('[data-pnw-template-select]', form);
        if (select) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        $('.pnw-recovery-banner', form)?.remove();

        const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
        clearLocal(postId);
        clearLocal(0);

        title?.focus();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const resetServerDraft = async (form) => {
        const postId = parseInt($('input[name="post_id"]', form)?.value || '0', 10) || 0;
        if (!postId || !config.ajaxUrl || !config.resetNonce) {
            return true;
        }

        const body = new FormData();
        body.append('action', 'pnw_reset_draft');
        body.append('nonce', config.resetNonce);
        body.append('post_id', String(postId));

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            const payload = await response.json();
            if (!payload?.success) {
                throw new Error(payload?.data?.message || 'A piszkozat kiürítése nem sikerült.');
            }
            return true;
        } catch (error) {
            window.alert(error.message || 'A piszkozat kiürítése nem sikerült.');
            return false;
        }
    };

    const installCleanSlateButton = () => {
        const form = $('.pnw-editor-form input[name="action"][value="pnw_save_news"]')?.closest('form');
        if (!form || $('.pnw-inline-notice')) {
            return;
        }

        const panel = $('.pnw-template-panel', form);
        const controls = panel ? $('.pnw-template-controls', panel) : null;
        if (!controls || $('[data-pnw-clean-slate]', controls)) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pnw-button pnw-button-ghost pnw-clean-slate-button';
        button.dataset.pnwCleanSlate = '1';
        button.textContent = '✕ Tiszta lap';
        controls.append(button);

        button.addEventListener('click', async () => {
            if (hasContent(form) && !window.confirm('Biztosan tiszta lappal szeretnél kezdeni? A jelenlegi cím, szöveg, kivonat, kategóriák és kiválasztott kép törlődik ebből a piszkozatból.')) {
                return;
            }

            button.disabled = true;
            const original = button.textContent;
            button.textContent = 'Törlés…';
            const reset = await resetServerDraft(form);
            if (reset) {
                clearClientForm(form);
                button.textContent = '✓ Üres hír';
                window.setTimeout(() => {
                    button.textContent = original;
                    button.disabled = false;
                }, 1200);
            } else {
                button.textContent = original;
                button.disabled = false;
            }
        });
    };

    const draftIdFromRow = (row) => {
        const editLink = $('a[href*="pnw_view=edit"][href*="post_id="]', row);
        if (!editLink) {
            return 0;
        }
        try {
            return parseInt(new URL(editLink.href, window.location.href).searchParams.get('post_id') || '0', 10) || 0;
        } catch (error) {
            return 0;
        }
    };

    const deleteDraft = async (postId, button) => {
        if (!config.ajaxUrl || !config.deleteDraftNonce) {
            window.alert('A piszkozat törlése jelenleg nem érhető el.');
            return;
        }

        if (!window.confirm('Biztosan törlöd ezt a piszkozatot? A hír a WordPress Lomtárába kerül.')) {
            return;
        }

        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Törlés…';

        const body = new FormData();
        body.append('action', 'pnw_delete_draft');
        body.append('nonce', config.deleteDraftNonce);
        body.append('post_id', String(postId));

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            const payload = await response.json();
            if (!payload?.success) {
                throw new Error(payload?.data?.message || 'A piszkozat törlése nem sikerült.');
            }

            clearLocal(postId);
            const target = new URL(config.managerUrl || window.location.href, window.location.href);
            target.searchParams.set('pnw_notice', 'trashed');
            window.location.assign(target.toString());
        } catch (error) {
            window.alert(error.message || 'A piszkozat törlése nem sikerült.');
            button.textContent = original;
            button.disabled = false;
        }
    };

    const installDraftDeleteButtons = () => {
        $$('.pnw-table tbody tr').forEach((row) => {
            if (!$('.pnw-badge-draft', row) || $('[data-pnw-quick-delete]', row)) {
                return;
            }

            const postId = draftIdFromRow(row);
            const actions = $('.pnw-actions', row);
            if (!postId || !actions) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pnw-button pnw-button-danger pnw-button-small pnw-quick-delete-button';
            button.dataset.pnwQuickDelete = '1';
            button.textContent = 'Törlés';
            button.addEventListener('click', () => deleteDraft(postId, button));
            actions.append(button);
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        installCleanSlateButton();
        installDraftDeleteButtons();
    });
})();
