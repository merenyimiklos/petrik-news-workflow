(() => {
    'use strict';

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

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePreview(document.querySelector('.pnw-preview-modal.pnw-preview-open'));
            }
        });
    });
})();
