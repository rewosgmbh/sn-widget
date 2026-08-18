/**
 * Steigerwald-News Widget — public intake form.
 *
 * Drives the [steigerwald_news_widget_builder] form: populates the category
 * dropdown from the public WP REST API and submits the configuration to the
 * custom /snw/v1/request endpoint. The pure config builder is exported for
 * Node-based unit tests.
 */
(function () {
    'use strict';

    var L = (typeof window !== 'undefined') ? (window.SNW_Public || {}) : {};

    /**
     * Build a widget config object from the public form fields.
     *
     * Only the fields a visitor may choose are collected; the server sanitizes
     * and completes the schema. Exposed for unit tests.
     *
     * @param {HTMLFormElement} form
     * @return {Object}
     */
    function buildConfigFromForm(form) {
        if (!form) { return {}; }
        var val = function (id) {
            var el = form.querySelector('#' + id);
            return el ? el.value : '';
        };
        var checked = function (id) {
            var el = form.querySelector('#' + id);
            return !!el && el.checked;
        };

        var mode = val('snw-pf-mode') || 'latest';
        var categoryEl = form.querySelector('#snw-pf-category');
        var category = [];
        if (mode === 'category' && categoryEl && categoryEl.value) {
            var cid = parseInt(categoryEl.value, 10);
            if (!isNaN(cid) && cid > 0) { category = [cid]; }
        }

        var limit = parseInt(val('snw-pf-limit'), 10);
        if (isNaN(limit) || limit < 1) { limit = 5; }
        if (limit > 20) { limit = 20; }

        return {
            v: 1,
            mode: mode,
            category: category,
            layout: val('snw-pf-layout') || 'grid',
            limit: limit,
            title: (val('snw-pf-title') || '').trim(),
            show: {
                image: checked('snw-pf-show-image'),
                date: checked('snw-pf-show-date'),
                category: false,
                excerpt: checked('snw-pf-show-excerpt'),
                readmore: true,
                branding: true,
                author: false
            },
            design: {
                accent: (val('snw-pf-accent') || '#c59a20'),
                columns: (parseInt(val('snw-pf-columns'), 10) || 2)
            }
        };
    }

    function populateCategories(select) {
        if (!select || !L.categoriesUrl) { return; }
        fetch(L.categoriesUrl + '?per_page=100&_fields=id,name&orderby=name&order=asc', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                if (!Array.isArray(rows)) { return; }
                rows.forEach(function (r) {
                    var o = document.createElement('option');
                    o.value = r.id;
                    o.textContent = r.name;
                    select.appendChild(o);
                });
            })
            .catch(function () { /* categories optional */ });
    }

    function setStatus(msg, isError) {
        var el = document.getElementById('snw-pf-status');
        if (el) {
            el.textContent = msg || '';
            el.className = 'snw-pf-status' + (isError ? ' snw-pf-status--error' : '');
        }
    }

    function init() {
        var form = document.getElementById('snw-public-form');
        if (!form) { return; }

        var modeEl = form.querySelector('#snw-pf-mode');
        var categoryWrap = document.getElementById('snw-pf-category-wrap');
        var categoryEl = form.querySelector('#snw-pf-category');

        if (modeEl && categoryWrap) {
            var toggleCategory = function () {
                categoryWrap.hidden = (modeEl.value !== 'category');
            };
            modeEl.addEventListener('change', toggleCategory);
            toggleCategory();
            populateCategories(categoryEl);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var emailEl = form.querySelector('#snw-pf-email');
            var domainEl = form.querySelector('#snw-pf-domain');
            var email = emailEl ? emailEl.value.trim() : '';
            var domain = domainEl ? domainEl.value.trim() : '';

            if (!email || email.indexOf('@') === -1 || !domain) {
                setStatus((L.i18n && L.i18n.invalid) || 'Bitte E-Mail und Domain ausfüllen.', true);
                return;
            }

            var submitBtn = form.querySelector('#snw-pf-submit');
            if (submitBtn) { submitBtn.disabled = true; }
            setStatus((L.i18n && L.i18n.submitting) || 'Wird gesendet …', false);

            var config = buildConfigFromForm(form);
            var payload = {
                name: (form.querySelector('#snw-pf-name') || {}).value || '',
                email: email,
                domain: domain,
                config: config
            };

            fetch(L.restUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (res) {
                if (res.status === 429) {
                    throw new Error((L.i18n && L.i18n.rate) || 'Zu viele Anfragen.');
                }
                if (!res.ok) {
                    return res.json().catch(function () { return {}; }).then(function (body) {
                        var msg = (body && body.message) ? body.message : ((L.i18n && L.i18n.error) || 'Fehler.');
                        throw new Error(msg);
                    });
                }
                return res.json();
            }).then(function () {
                setStatus((L.i18n && L.i18n.ok) || 'Danke!', false);
                form.reset();
            }).catch(function (err) {
                setStatus(err.message || ((L.i18n && L.i18n.error) || 'Fehler.'), true);
            }).then(function () {
                if (submitBtn) { submitBtn.disabled = false; }
            });
        });
    }

    if (typeof window !== 'undefined' && typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { buildConfigFromForm: buildConfigFromForm };
    }
})();
