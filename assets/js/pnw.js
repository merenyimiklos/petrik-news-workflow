(() => {
    'use strict';

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
                form.classList.add('pnw-is-submitting');
            });
        });
    });
})();
