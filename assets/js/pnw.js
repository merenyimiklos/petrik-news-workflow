(() => {
    'use strict';

    const editorToolsConfig = window.PNWEditorTools || {};

    const getEditorContent = (form) => {
        const textarea = form.querySelector('textarea[name="post_content"]');
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

    const sanitizePreviewHtml = (html) => {
        const template = document.createElement('template');
        template.innerHTML = html || '';

        template.content.querySelectorAll('script, style, iframe, object, embed, form, input, button').forEach((node) => node.remove());
        template.content.querySelectorAll('*').forEach((node) => {
            Array.from(node.attributes).forEach((attribute) => {
                const name = attribute.name.toLowerCase();
                const value = attribute.value.trim().toLowerCase();
                if (name.startsWith('on') || ((name === 'href' || name === 'src') && value.startsWith('javascript:'))) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return template.innerHTML;
    };

    const closePreview = (modal) => {
        if (!modal) {
            return;
        }
        modal.classList.remove('pnw-preview-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pnw-modal-open');
    };

    const openPreview = (form) => {
        const modal = document.querySelector('.pnw-preview-modal');
        if (!modal) {
            return;
        }

        const title = (form.querySelector('[name="post_title"]')?.value || '').trim();
        const excerpt = (form.querySelector('[name="post_excerpt"]')?.value || '').trim();
        const content = getEditorContent(form);
        const categories = Array.from(form.querySelectorAll('input[name="post_category[]"]:checked'))
            .map((input) => input.closest('label')?.querySelector('span')?.textContent?.trim())
            .filter(Boolean);
        const liveImage = form.querySelector('.pnw-image-live img');

        const titleTarget = modal.querySelector('.pnw-preview-title');
        const excerptTarget = modal.querySelector('.pnw-preview-excerpt');
        const contentTarget = modal.querySelector('.pnw-preview-content');
        const metaTarget = modal.querySelector('.pnw-preview-meta');
        const imageWrap = modal.querySelector('.pnw-preview-image-wrap');
        const imageTarget = modal.querySelector('.pnw-preview-image');

        if (titleTarget) {
            titleTarget.textContent = title || 'Hír címe';
        }
        if (metaTarget) {
            metaTarget.textContent = categories.length ? categories.join(' • ') : 'Nincs még kategória kiválasztva';
        }
        if (excerptTarget) {
            excerptTarget.textContent = excerpt;
            excerptTarget.hidden = !excerpt;
        }
        if (contentTarget) {
            contentTarget.innerHTML = content.trim()
                ? sanitizePreviewHtml(content)
                : '<p class="pnw-preview-empty">A hír szövege még üres.</p>';
        }

        if (imageWrap && imageTarget) {
            if (liveImage?.src) {
                imageTarget.src = liveImage.src;
                imageWrap.hidden = false;
            } else {
                imageTarget.removeAttribute('src');
                imageWrap.hidden = true;
            }
        }

        modal.classList.add('pnw-preview-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pnw-modal-open');
        modal.querySelector('.pnw-preview-close')?.focus();
    };

    const updateImagePreview = (input) => {
        const field = input.closest('.pnw-image-field');
        const live = field?.querySelector('.pnw-image-live');
        if (!live) {
            return;
        }

        const file = input.files?.[0];
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.addEventListener('load', () => {
            live.innerHTML = '';
            const image = document.createElement('img');
            image.src = String(reader.result || '');
            image.alt = 'Kiválasztott kiemelt kép előnézete';

            const meta = document.createElement('div');
            meta.className = 'pnw-image-live-meta';
            meta.innerHTML = '<strong>Új kiemelt kép</strong><span>A fájl mentéskor kerül feltöltésre.</span>';

            live.append(image, meta);
        });
        reader.readAsDataURL(file);
    };

    const createToolModal = (type) => {
        const modal = document.createElement('div');
        modal.className = `pnw-tool-modal pnw-${type}-modal`;
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.dataset.pnwToolModal = type;

        const backdrop = document.createElement('div');
        backdrop.className = 'pnw-tool-modal-backdrop';
        backdrop.dataset.pnwToolClose = '1';
        modal.append(backdrop);

        const dialog = document.createElement('div');
        dialog.className = 'pnw-tool-dialog';
        modal.append(dialog);
        document.body.append(modal);
        return { modal, dialog };
    };

    const closeToolModal = (modal) => {
        if (!modal) {
            return;
        }
        modal.classList.remove('pnw-tool-modal-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pnw-modal-open');
    };

    const openToolModal = (modal) => {
        if (!modal) {
            return;
        }
        modal.classList.add('pnw-tool-modal-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pnw-modal-open');
        modal.querySelector('input, select, button')?.focus();
    };

    const insertHtmlIntoEditor = (editorId, html) => {
        if (!editorId || !html) {
            return false;
        }

        if (window.tinymce && typeof window.tinymce.get === 'function') {
            const editor = window.tinymce.get(editorId);
            if (editor) {
                editor.focus();
                editor.insertContent(html + '<p></p>');
                return true;
            }
        }

        const textarea = document.getElementById(editorId);
        if (!textarea) {
            return false;
        }

        const start = Number.isInteger(textarea.selectionStart) ? textarea.selectionStart : textarea.value.length;
        const end = Number.isInteger(textarea.selectionEnd) ? textarea.selectionEnd : start;
        textarea.value = textarea.value.slice(0, start) + html + '\n\n' + textarea.value.slice(end);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    };

    const buildTableHtml = ({ rows, columns, header, caption }) => {
        const borderStyle = 'border:1px solid #cfd7e3;padding:10px 12px;text-align:left;vertical-align:top;';
        let html = '<div class="pnw-news-table-wrap" style="width:100%;overflow-x:auto;margin:20px 0;">';
        html += '<table class="pnw-news-table" style="width:100%;border-collapse:collapse;background:#ffffff;">';

        if (caption) {
            const escapedCaption = document.createElement('div');
            escapedCaption.textContent = caption;
            html += `<caption style="caption-side:top;text-align:left;font-weight:700;padding:0 0 8px;">${escapedCaption.innerHTML}</caption>`;
        }

        if (header) {
            html += '<thead><tr>';
            for (let column = 1; column <= columns; column += 1) {
                html += `<th scope="col" style="${borderStyle}background:#eef3f9;color:#173b6c;font-weight:700;">Oszlop ${column}</th>`;
            }
            html += '</tr></thead>';
        }

        html += '<tbody>';
        for (let row = 0; row < rows; row += 1) {
            html += '<tr>';
            for (let column = 0; column < columns; column += 1) {
                html += `<td style="${borderStyle}">&nbsp;</td>`;
            }
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        return html;
    };

    const ensureTableModal = () => {
        let modal = document.querySelector('[data-pnw-tool-modal="table"]');
        if (modal) {
            return modal;
        }

        const created = createToolModal('table');
        modal = created.modal;
        created.dialog.innerHTML = `
            <div class="pnw-tool-dialog-header">
                <div><div class="pnw-kicker">Tartalmi elem</div><h4>Táblázat beszúrása</h4><p>Nem kell kódolni: add meg a méretet, majd a cellákba kattintva írj.</p></div>
                <button type="button" class="pnw-tool-close" data-pnw-tool-close aria-label="Bezárás">×</button>
            </div>
            <div class="pnw-tool-field">
                <label for="pnw-table-caption">Táblázat címe <small>(opcionális)</small></label>
                <input id="pnw-table-caption" type="text" maxlength="120" placeholder="Pl. Versenyeredmények">
            </div>
            <div class="pnw-tool-grid">
                <div class="pnw-tool-field">
                    <label for="pnw-table-columns">Oszlopok száma</label>
                    <input id="pnw-table-columns" type="number" min="1" max="10" value="3">
                </div>
                <div class="pnw-tool-field">
                    <label for="pnw-table-rows">Adatsorok száma</label>
                    <input id="pnw-table-rows" type="number" min="1" max="20" value="3">
                </div>
            </div>
            <div class="pnw-tool-field">
                <label class="pnw-tool-check"><input id="pnw-table-header" type="checkbox" checked> <span>Legyen külön fejlécsor az oszlopneveknek</span></label>
                <p class="pnw-tool-help">A beszúrás után az „Oszlop 1” feliratokat és az üres cellákat közvetlenül a vizuális szerkesztőben tudod átírni.</p>
            </div>
            <div class="pnw-tool-message" data-pnw-tool-message></div>
            <div class="pnw-tool-actions">
                <button type="button" class="pnw-button pnw-button-secondary" data-pnw-tool-close>Mégse</button>
                <button type="button" class="pnw-button" data-pnw-table-insert>Táblázat beszúrása</button>
            </div>`;

        modal.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof Element && target.closest('[data-pnw-tool-close]')) {
                closeToolModal(modal);
            }
        });

        modal.querySelector('[data-pnw-table-insert]')?.addEventListener('click', () => {
            const rows = Math.max(1, Math.min(20, parseInt(modal.querySelector('#pnw-table-rows')?.value || '3', 10)));
            const columns = Math.max(1, Math.min(10, parseInt(modal.querySelector('#pnw-table-columns')?.value || '3', 10)));
            const header = Boolean(modal.querySelector('#pnw-table-header')?.checked);
            const caption = (modal.querySelector('#pnw-table-caption')?.value || '').trim();
            const editorId = modal.dataset.editorId || '';
            const inserted = insertHtmlIntoEditor(editorId, buildTableHtml({ rows, columns, header, caption }));
            const message = modal.querySelector('[data-pnw-tool-message]');

            if (!inserted) {
                if (message) {
                    message.textContent = 'A szerkesztő nem érhető el. Töltsd újra az oldalt és próbáld újra.';
                    message.className = 'pnw-tool-message pnw-tool-message-show pnw-tool-message-error';
                }
                return;
            }

            closeToolModal(modal);
        });

        return modal;
    };

    const installTableTools = () => {
        document.querySelectorAll('.pnw-editor-field').forEach((field) => {
            if (field.querySelector('.pnw-content-tools')) {
                return;
            }

            const textarea = field.querySelector('textarea[name="post_content"]');
            const editorWrap = field.querySelector('.wp-editor-wrap');
            if (!textarea || !editorWrap || !textarea.id) {
                return;
            }

            const tools = document.createElement('div');
            tools.className = 'pnw-content-tools';
            tools.innerHTML = `
                <div class="pnw-content-tools-copy"><strong>Egyszerű tartalmi elemek</strong><span>Táblázatot is beszúrhatsz kódolás nélkül.</span></div>
                <button type="button" class="pnw-tool-button" data-pnw-table-open>▦ Táblázat beszúrása</button>`;
            editorWrap.insertAdjacentElement('afterend', tools);

            tools.querySelector('[data-pnw-table-open]')?.addEventListener('click', () => {
                const modal = ensureTableModal();
                modal.dataset.editorId = textarea.id;
                const message = modal.querySelector('[data-pnw-tool-message]');
                if (message) {
                    message.className = 'pnw-tool-message';
                    message.textContent = '';
                }
                openToolModal(modal);
            });
        });
    };

    const getVisibleCategories = (field) => Array.from(field.querySelectorAll('.pnw-category-grid label')).map((label) => {
        const input = label.querySelector('input[name="post_category[]"]');
        const name = label.querySelector('span')?.textContent?.trim() || '';
        return input ? { id: input.value, name } : null;
    }).filter(Boolean);

    const ensureCategoryModal = () => {
        let modal = document.querySelector('[data-pnw-tool-modal="category"]');
        if (modal) {
            return modal;
        }

        const created = createToolModal('category');
        modal = created.modal;
        created.dialog.innerHTML = `
            <div class="pnw-tool-dialog-header">
                <div><div class="pnw-kicker">Kategória</div><h4>Új kategória hozzáadása</h4><p>Adj neki egy rövid, egyértelmű nevet. Mentés után rögtön kiválasztjuk a hírhez.</p></div>
                <button type="button" class="pnw-tool-close" data-pnw-tool-close aria-label="Bezárás">×</button>
            </div>
            <div class="pnw-tool-field">
                <label for="pnw-category-name">Kategória neve</label>
                <input id="pnw-category-name" type="text" maxlength="80" placeholder="Pl. Matematika versenyek">
            </div>
            <div class="pnw-tool-field">
                <label for="pnw-category-parent">Szülőkategória <small>(opcionális)</small></label>
                <select id="pnw-category-parent"><option value="0">— Nincs, önálló kategória —</option></select>
                <p class="pnw-tool-help">Például a „Matematika versenyek” lehet a „Hírek” egyik alkategóriája.</p>
            </div>
            <div class="pnw-tool-message" data-pnw-tool-message></div>
            <div class="pnw-tool-actions">
                <button type="button" class="pnw-button pnw-button-secondary" data-pnw-tool-close>Mégse</button>
                <button type="button" class="pnw-button" data-pnw-category-save>Kategória létrehozása</button>
            </div>`;

        modal.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof Element && target.closest('[data-pnw-tool-close]')) {
                closeToolModal(modal);
            }
        });

        modal.querySelector('[data-pnw-category-save]')?.addEventListener('click', async () => {
            const nameInput = modal.querySelector('#pnw-category-name');
            const parentInput = modal.querySelector('#pnw-category-parent');
            const saveButton = modal.querySelector('[data-pnw-category-save]');
            const message = modal.querySelector('[data-pnw-tool-message]');
            const name = (nameInput?.value || '').trim();

            if (!name) {
                if (message) {
                    message.textContent = 'Írd be az új kategória nevét.';
                    message.className = 'pnw-tool-message pnw-tool-message-show pnw-tool-message-error';
                }
                nameInput?.focus();
                return;
            }

            if (!editorToolsConfig.ajaxUrl || !editorToolsConfig.categoryNonce) {
                if (message) {
                    message.textContent = 'A kategória-kezelő kapcsolat nem érhető el. Töltsd újra az oldalt.';
                    message.className = 'pnw-tool-message pnw-tool-message-show pnw-tool-message-error';
                }
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = 'Létrehozás…';
            if (message) {
                message.className = 'pnw-tool-message';
                message.textContent = '';
            }

            try {
                const data = new FormData();
                data.append('action', 'pnw_create_category');
                data.append('nonce', editorToolsConfig.categoryNonce);
                data.append('name', name);
                data.append('parent', parentInput?.value || '0');

                const response = await fetch(editorToolsConfig.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data,
                });
                const payload = await response.json();
                if (!payload?.success || !payload?.data?.id) {
                    throw new Error(payload?.data?.message || 'A kategória létrehozása nem sikerült.');
                }

                const category = payload.data;
                const field = modal._pnwCategoryField;
                const grid = field?.querySelector('.pnw-category-grid');
                if (grid) {
                    let checkbox = grid.querySelector(`input[name="post_category[]"][value="${String(category.id)}"]`);
                    if (!checkbox) {
                        const label = document.createElement('label');
                        checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'post_category[]';
                        checkbox.value = String(category.id);
                        checkbox.checked = true;
                        const span = document.createElement('span');
                        span.textContent = category.name;
                        label.append(checkbox, document.createTextNode(' '), span);
                        grid.append(label);
                    } else {
                        checkbox.checked = true;
                    }
                }

                if (message) {
                    message.textContent = payload.data.message || 'A kategória létrejött.';
                    message.className = 'pnw-tool-message pnw-tool-message-show pnw-tool-message-success';
                }
                nameInput.value = '';
                window.setTimeout(() => closeToolModal(modal), 700);
            } catch (error) {
                if (message) {
                    message.textContent = error instanceof Error ? error.message : 'A kategória létrehozása nem sikerült.';
                    message.className = 'pnw-tool-message pnw-tool-message-show pnw-tool-message-error';
                }
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'Kategória létrehozása';
            }
        });

        return modal;
    };

    const installCategoryTools = () => {
        document.querySelectorAll('.pnw-category-field').forEach((field) => {
            if (field.querySelector('.pnw-category-tools')) {
                return;
            }

            const grid = field.querySelector('.pnw-category-grid');
            if (!grid) {
                return;
            }

            const toolbar = document.createElement('div');
            toolbar.className = 'pnw-category-tools';
            toolbar.innerHTML = `
                <small>Jelöld ki, hová tartozik a hír. Ha nincs megfelelő, létrehozhatsz újat.</small>
                <button type="button" class="pnw-tool-button" data-pnw-category-open>+ Új kategória</button>`;
            grid.insertAdjacentElement('beforebegin', toolbar);

            toolbar.querySelector('[data-pnw-category-open]')?.addEventListener('click', () => {
                const modal = ensureCategoryModal();
                modal._pnwCategoryField = field;
                const select = modal.querySelector('#pnw-category-parent');
                if (select) {
                    select.innerHTML = '<option value="0">— Nincs, önálló kategória —</option>';
                    getVisibleCategories(field).forEach((category) => {
                        const option = document.createElement('option');
                        option.value = String(category.id);
                        option.textContent = category.name;
                        select.append(option);
                    });
                }
                const message = modal.querySelector('[data-pnw-tool-message]');
                if (message) {
                    message.className = 'pnw-tool-message';
                    message.textContent = '';
                }
                const nameInput = modal.querySelector('#pnw-category-name');
                if (nameInput) {
                    nameInput.value = '';
                }
                openToolModal(modal);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.pnw-delete-form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const message = form.getAttribute('data-confirm') || 'Biztosan folytatod?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.pnw-form, .pnw-decision').forEach((form) => {
            form.addEventListener('submit', () => {
                if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
                    window.tinymce.triggerSave();
                }
                form.classList.add('pnw-is-submitting');
            });
        });

        document.querySelectorAll('.pnw-preview-trigger').forEach((button) => {
            button.addEventListener('click', () => {
                const form = button.closest('.pnw-editor-form');
                if (form) {
                    openPreview(form);
                }
            });
        });

        document.querySelectorAll('[data-pnw-preview-close]').forEach((button) => {
            button.addEventListener('click', () => closePreview(button.closest('.pnw-preview-modal')));
        });

        document.querySelectorAll('.pnw-featured-input').forEach((input) => {
            input.addEventListener('change', () => updateImagePreview(input));
        });

        installTableTools();
        installCategoryTools();

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePreview(document.querySelector('.pnw-preview-modal.pnw-preview-open'));
                document.querySelectorAll('.pnw-tool-modal.pnw-tool-modal-open').forEach((modal) => closeToolModal(modal));
            }
        });
    });
})();
