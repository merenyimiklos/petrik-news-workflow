(() => {
    'use strict';

    const CATEGORY_SELECTOR = 'input[name="post_category[]"]';
    const ERROR_CLASS = 'pnw-category-validation-error';
    const FIELD_ERROR_CLASS = 'pnw-category-field-invalid';

    const categoryFieldFor = (form) => form.querySelector('.pnw-category-field');

    const selectedCategoryCount = (form) => form.querySelectorAll(`${CATEGORY_SELECTOR}:checked`).length;

    const ensureErrorMessage = (field) => {
        let message = field.querySelector(`.${ERROR_CLASS}`);
        if (message) {
            return message;
        }

        message = document.createElement('div');
        message.className = ERROR_CLASS;
        message.setAttribute('role', 'alert');
        message.setAttribute('aria-live', 'assertive');
        message.textContent = 'Válassz legalább egy kategóriát a folytatáshoz.';

        const grid = field.querySelector('.pnw-category-grid');
        if (grid) {
            grid.insertAdjacentElement('afterend', message);
        } else {
            field.append(message);
        }

        return message;
    };

    const clearCategoryError = (form) => {
        const field = categoryFieldFor(form);
        if (!field) {
            return;
        }

        field.classList.remove(FIELD_ERROR_CLASS);
        field.removeAttribute('aria-invalid');
        const message = field.querySelector(`.${ERROR_CLASS}`);
        if (message) {
            message.remove();
        }
    };

    const showCategoryError = (form) => {
        const field = categoryFieldFor(form);
        if (!field) {
            return;
        }

        field.classList.add(FIELD_ERROR_CLASS);
        field.setAttribute('aria-invalid', 'true');
        ensureErrorMessage(field);

        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => {
            const first = field.querySelector(CATEGORY_SELECTOR);
            if (first instanceof HTMLElement) {
                first.focus({ preventScroll: true });
            }
        }, 350);
    };

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.matches(CATEGORY_SELECTOR)) {
            return;
        }

        const form = target.closest('.pnw-editor-form');
        if (form && selectedCategoryCount(form) > 0) {
            clearCategoryError(form);
        }
    });

    // Capture phase is deliberate: this runs before the generic Hírkezelő
    // submit handler can lock/fade the form. Invalid forms never leave the page,
    // so the user's already entered title, text and selected image remain intact.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('pnw-editor-form')) {
            return;
        }

        const categoryField = categoryFieldFor(form);
        if (!categoryField) {
            return;
        }

        if (selectedCategoryCount(form) > 0) {
            clearCategoryError(form);
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        form.classList.remove('pnw-is-submitting');
        showCategoryError(form);
    }, true);
})();
