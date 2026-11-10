/**
 * Shared inline form validation — WBPMS
 *
 * Drop <script src="/assets/inline-validate.js"></script> into any form view.
 * The script auto-attaches to every <form novalidate data-validate> on the page.
 *
 * Behaviour:
 *   - On blur   : validate the field that just lost focus.
 *   - On input  : re-validate a field that is already marked invalid (clear as soon as valid).
 *   - On change : same as input, plus always validate <select> elements.
 *   - On submit : validate every field; block submission and focus the first invalid one.
 *
 * Error display:
 *   - Adds/removes the `is-invalid` class on the input/select/textarea.
 *   - Inserts (or updates) a <span class="field-error" data-js="1"> immediately after
 *     the field. Any pre-existing server-rendered .field-error without data-js is left alone.
 *
 * Custom messages:
 *   Set data-msg-required, data-msg-pattern, data-msg-type, data-msg-min, data-msg-max
 *   directly on the input element to override the default browser message.
 *
 * Usage:
 *   <form novalidate data-validate>
 *     <input name="email" type="email" required data-msg-required="Email is required.">
 *   </form>
 */
(function () {
    'use strict';

    function getMsg(field) {
        var v = field.validity;
        if (v.valueMissing)    return field.dataset.msgRequired || 'This field is required.';
        if (v.typeMismatch)    return field.dataset.msgType     || 'Please enter a valid value.';
        if (v.patternMismatch) return field.dataset.msgPattern  || 'The value entered is not in the correct format.';
        if (v.rangeUnderflow)  return field.dataset.msgMin      || 'Value is too low.';
        if (v.rangeOverflow)   return field.dataset.msgMax      || 'Value is too high.';
        if (v.tooShort)        return field.dataset.msgMin      || 'Value is too short.';
        if (v.tooLong)         return field.dataset.msgMax      || 'Value is too long.';
        return field.validationMessage || 'Please enter a valid value.';
    }

    function getSpan(field) {
        var parent = field.closest('.form-group') || field.parentNode;
        if (!parent) return null;
        var span = parent.querySelector('.field-error[data-js]');
        if (!span) {
            span = document.createElement('span');
            span.className = 'field-error';
            span.setAttribute('data-js', '1');
            field.insertAdjacentElement('afterend', span);
        }
        return span;
    }

    function validate(field) {
        if (field.disabled || field.readOnly || field.type === 'hidden') return true;
        field.setCustomValidity('');
        var ok   = field.checkValidity();
        var span = getSpan(field);
        if (!ok) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            if (span) span.textContent = getMsg(field);
        } else {
            field.classList.remove('is-invalid');
            if (span) span.textContent = '';
        }
        return ok;
    }

    function attachTo(form) {
        // Suppress native browser tooltip UI; we handle everything ourselves.
        form.setAttribute('novalidate', '');

        form.addEventListener('blur', function (e) {
            var f = e.target;
            if (f.tagName === 'INPUT' || f.tagName === 'SELECT' || f.tagName === 'TEXTAREA') {
                validate(f);
            }
        }, true);

        form.addEventListener('input', function (e) {
            if (e.target.classList.contains('is-invalid')) validate(e.target);
        }, true);

        form.addEventListener('change', function (e) {
            var f = e.target;
            if (f.tagName === 'SELECT' || f.classList.contains('is-invalid')) validate(f);
        }, true);

        form.addEventListener('submit', function (e) {
            var fields = form.querySelectorAll('input:not([type=hidden]), select:not([disabled]), textarea');
            var first  = null;
            fields.forEach(function (f) {
                if (!validate(f) && !first) first = f;
            });
            if (first) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    function init() {
        document.querySelectorAll('form[data-validate]').forEach(attachTo);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
