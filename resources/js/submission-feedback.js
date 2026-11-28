let pendingForm = null;
let guardedButtons = [];
let recoveryTimer = null;
let navigationSignal = null;
const submissionValues = new WeakMap();

function inputSignature(form) {
    return JSON.stringify(Array.from(new FormData(form)).filter(([name]) => !['_token', '_submission_id'].includes(name)));
}

document.querySelectorAll('form[data-submit-feedback]').forEach(form => {
    if (form.querySelector('input[name="_submission_id"]')) submissionValues.set(form, inputSignature(form));
});

function newSubmissionId() {
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function changedSubmission(event) {
    const form = event.target.closest('form[data-submit-feedback]');
    const identifier = form?.querySelector('input[name="_submission_id"]');
    if (identifier) {
        const values = inputSignature(form);
        if (submissionValues.get(form) !== values) {
            identifier.value = newSubmissionId();
            submissionValues.set(form, values);
        }
    }
}

document.addEventListener('input', changedSubmission);
document.addEventListener('change', changedSubmission);

function resetSubmission() {
    clearTimeout(recoveryTimer);
    recoveryTimer = null;
    navigationSignal?.removeEventListener('abort', resetSubmission);
    navigationSignal = null;
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
    changedSubmission({ target: form });

    pendingForm = form;
    // Recovery keeps the same identifier so the server can recognize an unchanged retry.
    recoveryTimer = setTimeout(resetSubmission, 15000);
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

window.navigation?.addEventListener('navigate', (event) => {
    if (!pendingForm) return;
    navigationSignal?.removeEventListener('abort', resetSubmission);
    navigationSignal = event.signal;
    navigationSignal.addEventListener('abort', resetSubmission, { once: true });
});

window.addEventListener('pageshow', resetSubmission);
