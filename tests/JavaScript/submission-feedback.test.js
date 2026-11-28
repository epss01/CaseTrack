import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { webcrypto } from 'node:crypto';

const source = readFileSync(new URL('../../resources/js/submission-feedback.js', import.meta.url), 'utf8');

function element(attributes = {}, textContent = '') {
    const attrs = new Map(Object.entries(attributes));
    return {
        textContent,
        setAttribute: (name, value) => attrs.set(name, value),
        getAttribute: (name) => attrs.get(name) ?? null,
        removeAttribute: (name) => attrs.delete(name),
    };
}

function fixture({ navigation = true } = {}) {
    const window = new EventTarget();
    window.crypto = webcrypto;
    const document = new EventTarget();
    if (navigation) window.navigation = new EventTarget();
    const status = element();
    const forms = ['Save', 'Logout'].map((label) => {
        const normal = element({}, label);
        const pending = element({ 'aria-hidden': 'true' }, `${label} pending`);
        const button = Object.assign(element(), {
            type: 'submit', disabled: false,
            querySelector: (selector) => selector === '[data-label-normal]' ? normal : pending,
            closest: () => button,
            normal, pending,
        });
        const form = Object.assign(element(), {
            dataset: { submitContext: 'Synthetic record' }, elements: [button], valid: true, guarded: true,
            matches: () => form.guarded,
            checkValidity: () => form.valid,
            identifier: label === 'Save' ? { value: '11111111-1111-4111-8111-111111111111' } : null,
            querySelector: () => form.identifier,
            closest: () => form.guarded ? form : null,
            value: 'initial',
        });
        button.form = form;
        return form;
    });
    document.querySelector = () => status;
    document.querySelectorAll = () => forms;
    let now = 0;
    let nextTimer = 0;
    const timers = new Map();
    runInNewContext(source, {
        window, document,
        FormData: class {
            constructor(form) { this.form = form; }
            *[Symbol.iterator]() { yield ['value', this.form.value]; }
        },
        setTimeout(callback, delay) {
            const id = ++nextTimer;
            timers.set(id, { callback, due: now + delay });
            return id;
        },
        clearTimeout: (id) => timers.delete(id),
    });
    function dispatch(target, type, properties = {}, prevented = false) {
        const event = new Event(type, { cancelable: true });
        Object.entries(properties).forEach(([key, value]) => Object.defineProperty(event, key, { value }));
        if (prevented) event.preventDefault();
        target.dispatchEvent(event);
        return event;
    }
    return {
        forms, status, timers,
        submit: (form = forms[0], prevented = false) => dispatch(window, 'submit', { target: form, submitter: form.elements[0] }, prevented),
        click: (form = forms[0]) => dispatch(document, 'click', { target: form.elements[0] }),
        navigate() {
            const controller = new AbortController();
            dispatch(window.navigation, 'navigate', { signal: controller.signal });
            return controller;
        },
        pageshow: () => dispatch(window, 'pageshow'),
        change(type = 'input', form = forms[0], changed = true) {
            if (changed) form.value += ' edited';
            return dispatch(document, type, { target: form });
        },
        advance(milliseconds) {
            now += milliseconds;
            for (const [id, timer] of timers) {
                if (timer.due <= now) {
                    timers.delete(id);
                    timer.callback();
                }
            }
        },
    };
}

function assertReset(f) {
    for (const form of f.forms) {
        assert.equal(form.getAttribute('aria-busy'), null);
        for (const button of form.elements) {
            assert.equal(button.getAttribute('data-submitting'), null);
            assert.equal(button.normal.getAttribute('aria-hidden'), null);
            assert.equal(button.pending.getAttribute('aria-hidden'), 'true');
        }
    }
    assert.equal(f.status.textContent, '');
    assert.equal(f.timers.size, 0);
}

test('submit, cancel navigation, then retry or Logout without pageshow', () => {
    for (const nextForm of [0, 1]) {
        const f = fixture();
        f.forms[1].elements[0].setAttribute('aria-disabled', 'false');
        assert.equal(f.submit().defaultPrevented, false);
        assert.equal(f.forms[0].getAttribute('aria-busy'), 'true');
        assert.equal(f.forms[0].elements[0].normal.getAttribute('aria-hidden'), 'true');
        assert.equal(f.forms[0].elements[0].pending.getAttribute('aria-hidden'), null);
        assert.equal(f.status.textContent, 'Save pending Synthetic record');
        f.navigate().abort();
        assertReset(f);
        assert.equal(f.forms[0].elements[0].getAttribute('aria-disabled'), null);
        assert.equal(f.forms[1].elements[0].getAttribute('aria-disabled'), 'false');
        assert.equal(f.click(f.forms[nextForm]).defaultPrevented, false);
        assert.equal(f.submit(f.forms[nextForm]).defaultPrevented, false);
        assert.equal(f.forms[nextForm].getAttribute('aria-busy'), 'true');
    }
});

test('blocks rapid duplicate and conflicting submissions before recovery', () => {
    const f = fixture();
    f.submit();
    for (const form of f.forms) {
        assert.equal(f.click(form).defaultPrevented, true);
        assert.equal(f.submit(form).defaultPrevented, true);
        assert.equal(form.elements[0].getAttribute('aria-disabled'), 'true');
        assert.equal(form.elements[0].disabled, false);
    }
    assert.equal(f.timers.size, 1);
});

test('recovers after 15 seconds when cancellation is unreported or the Navigation API is absent', () => {
    for (const navigation of [true, false]) {
        const f = fixture({ navigation });
        f.submit();
        f.advance(14999);
        assert.equal(f.submit().defaultPrevented, true);
        f.advance(1);
        assertReset(f);
        assert.equal(f.forms[0].elements[0].getAttribute('aria-disabled'), null);
        assert.equal(f.submit().defaultPrevented, false);
    }
});

test('pageshow clears old timers and abort listeners before a newer attempt', () => {
    const f = fixture();
    f.submit();
    const oldNavigation = f.navigate();
    f.advance(10000);
    f.pageshow();
    assertReset(f);
    f.submit();
    const currentNavigation = f.navigate();
    oldNavigation.abort();
    f.advance(5000);
    assert.equal(f.forms[0].getAttribute('aria-busy'), 'true');
    assert.equal(f.submit().defaultPrevented, true);
    currentNavigation.abort();
    assertReset(f);
});

test('timeout removes old abort listeners even when retrying the same form', () => {
    const f = fixture();
    f.submit();
    const oldNavigation = f.navigate();
    f.advance(15000);
    f.submit();
    oldNavigation.abort();
    assert.equal(f.submit().defaultPrevented, true);
    f.navigate().abort();
    assertReset(f);
});

test('invalid, prevented and unguarded submissions do not start recovery timers', () => {
    const f = fixture();
    f.forms[0].valid = false;
    f.submit();
    f.forms[0].valid = true;
    f.submit(f.forms[0], true);
    f.forms[0].guarded = false;
    f.submit();
    assertReset(f);
    f.forms[0].guarded = true;
    assert.equal(f.submit().defaultPrevented, false);
});

test('unchanged retries retain their identifier across cancellation and timeout', () => {
    const f = fixture();
    const id = f.forms[0].identifier.value;
    f.submit();
    f.navigate().abort();
    assert.equal(f.forms[0].identifier.value, id);
    f.submit();
    f.advance(15000);
    assert.equal(f.forms[0].identifier.value, id);
    f.pageshow();
    assert.equal(f.forms[0].identifier.value, id);
});

test('editing a form starts a new UUID while session-only forms need none', () => {
    const f = fixture();
    const initial = f.forms[0].identifier.value;
    f.change();
    const edited = f.forms[0].identifier.value;
    assert.match(edited, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    assert.notEqual(edited, initial);
    f.change('change');
    assert.notEqual(f.forms[0].identifier.value, edited);
    f.change('input', f.forms[1]);
    assert.equal(f.forms[1].identifier, null);
});

test('an input event without changed values keeps the intended submission identifier', () => {
    const f = fixture();
    const id = f.forms[0].identifier.value;
    f.change('input', f.forms[0], false);
    f.submit();
    f.navigate().abort();
    f.change('change', f.forms[0], false);
    assert.equal(f.forms[0].identifier.value, id);
});

test('submit detects changed values even when no input event was dispatched', () => {
    const f = fixture();
    f.submit();
    f.navigate().abort();
    const previous = f.forms[0].identifier.value;
    f.forms[0].value = 'programmatic correction';
    f.submit();
    assert.notEqual(f.forms[0].identifier.value, previous);
});
