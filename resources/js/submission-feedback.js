let pendingForm = null;
let guardedButtons = [];

function resetSubmission() {
    if (pendingForm) {
        pendingForm.removeAttribute('aria-busy');
        pendingForm = null;
    }
    guardedButtons.forEach(({ button, disabled }) => {
        if (disabled === null) button.removeAttribute('aria-disabled');
        else button.setAttribute('aria-disabled', disabled);
        button.removeAttribute('data-submitting');
        button.querySelector('[data-label-normal]')?.removeAttribute('aria-hidden');
        button.querySelector('[data-label-pending]')?.setAttribute('aria-hidden', 'true');
    });
    guardedButtons = [];
    const status = document.querySelector('[data-submission-status]');
    if (status) status.textContent = '';
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('button, input[type="submit"]');
    if (pendingForm && button?.form?.matches('[data-submit-feedback]') && button.type === 'submit') {
        event.preventDefault();
    }
}, true);

window.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-submit-feedback]') || event.defaultPrevented) return;
    if (pendingForm) {
        event.preventDefault();
        return;
    }
    if (!form.checkValidity()) return;

    pendingForm = form;
    form.setAttribute('aria-busy', 'true');
    document.querySelectorAll('[data-submit-feedback]').forEach((candidate) => {
        Array.from(candidate.elements).filter((control) => control.type === 'submit' && !control.disabled).forEach((button) => {
            guardedButtons.push({ button, disabled: button.getAttribute('aria-disabled') });
            button.setAttribute('aria-disabled', 'true');
        });
    });
    const button = event.submitter ?? Array.from(form.elements).find((control) => control.type === 'submit' && !control.disabled);
    const label = button?.querySelector('[data-label-pending]');
    if (label) {
        button.setAttribute('data-submitting', '');
        button.querySelector('[data-label-normal]').setAttribute('aria-hidden', 'true');
        label.removeAttribute('aria-hidden');
    }
    const status = document.querySelector('[data-submission-status]');
    if (status) status.textContent = [label?.textContent.trim(), form.dataset.submitContext].filter(Boolean).join(' ');
});

window.addEventListener('pageshow', resetSubmission);
