/**
 * SN News Widget — public config builder (Node-testable).
 *
 * The public [steigerwald_news_widget_builder] form shares the exact same
 * markup/field IDs as the admin builder, so this pure helper mirrors the
 * browser-side config extraction (admin.js getConfig) for unit tests. The
 * browser UI itself is driven by admin.js, which is loaded on both admin and
 * public builder pages.
 */
(function () {
    'use strict';

    /**
     * Build a widget config object from a form using the shared snw-* IDs.
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

        var mode = val('snw-mode') || 'latest';
        var categoryEl = form.querySelector('#snw-category');
        var category = [];
        if ((mode === 'category' || mode === 'category_tags') && categoryEl && categoryEl.value) {
            var cid = parseInt(categoryEl.value, 10);
            if (!isNaN(cid) && cid > 0) { category = [cid]; }
        }

        var limit = parseInt(val('snw-limit'), 10);
        if (isNaN(limit) || limit < 1) { limit = 5; }
        if (limit > 20) { limit = 20; }

        var radiusVal = parseInt(val('snw-radius'), 10);

        return {
            v: 1,
            mode: mode,
            category: category,
            layout: val('snw-layout') || 'grid',
            limit: limit,
            sort: val('snw-sort') || 'newest',
            title: (val('snw-title') || '').trim(),
            partner: (val('snw-partner') || '').trim(),
            show: {
                image: checked('snw-show-image'),
                date: checked('snw-show-date'),
                category: checked('snw-show-category'),
                excerpt: checked('snw-show-excerpt'),
                readmore: checked('snw-show-readmore'),
                branding: checked('snw-show-branding'),
                author: checked('snw-show-author')
            },
            design: {
                accent: (val('snw-color-accent') || '#c59a20'),
                columns: (parseInt(val('snw-columns'), 10) || 2),
                radius: (isNaN(radiusVal) ? 8 : radiusVal)
            }
        };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { buildConfigFromForm: buildConfigFromForm };
    }
})();
