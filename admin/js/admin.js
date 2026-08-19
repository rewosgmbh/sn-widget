/**
 * SN News Widget - Admin Builder.
 *
 * Drives the Widget Builder UI: content configuration, design builder,
 * tag/post search, live preview (via the public renderer), and preset
 * CRUD through admin-ajax. Uses only WordPress-core REST endpoints and the
 * public widget renderer so the preview matches production exactly.
 */
(function () {
    'use strict';

    var L = window.SNW_Admin || {};
    var W = window.SteigerwaldNewsWidget;
    var I = L.i18n || {};

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    // Safe field access: returns a default when the element is absent
    // (the advanced/internal fields are hidden on the public builder).
    function val(sel, def) {
        var el = $(sel);
        if (!el) { return (def === undefined ? '' : def); }
        return el.value;
    }
    function setVal(sel, v) {
        var el = $(sel);
        if (el) { el.value = (v === undefined || v === null) ? '' : v; }
    }

    function clamp(v, min, max) {
        v = parseInt(v, 10);
        if (isNaN(v)) { return min; }
        return Math.max(min, Math.min(max, v));
    }

    function parseIdList(str) {
        if (!str) { return []; }
        return str.split(',').map(function (s) { return parseInt(s.trim(), 10); })
            .filter(function (n) { return !isNaN(n) && n > 0; });
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms || 300);
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function apiUrl(path) {
        return (L.apiBase || '').replace(/\/+$/, '') + '/' + path;
    }

    function rest(path, params) {
        var url = apiUrl(path);
        var keys = Object.keys(params || {});
        if (keys.length) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + keys.map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
        }
        return fetch(url, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); });
    }

    var state = {
        tags: [],
        posts: [],
        pinned: [],
        currentId: '',
        name: ''
    };

    // ------------------------------------------------------------------
    // Config <-> UI
    // ------------------------------------------------------------------
    function getConfig() {
        var show = {
            image: $('#snw-show-image').checked,
            date: $('#snw-show-date').checked,
            category: $('#snw-show-category').checked,
            excerpt: $('#snw-show-excerpt').checked,
            readmore: $('#snw-show-readmore').checked,
            branding: $('#snw-show-branding').checked,
            author: $('#snw-show-author').checked
        };

        var mode = $('#snw-mode').value;
        var $cat = $('#snw-category');

        // Content modes are isolated: a value only leaves the builder when the
        // active mode actually uses it. Hidden category/tag/post selections
        // must never keep filtering in another mode.
        var category = ((mode === 'category' || mode === 'category_tags') && $cat && $cat.value)
            ? [parseInt($cat.value, 10)]
            : [];
        var tags = (mode === 'tags' || mode === 'category_tags')
            ? state.tags.map(function (t) { return t.id; })
            : [];
        var include = (mode === 'manual') ? state.posts.map(function (p) { return p.id; }) : [];
        var pinned = (mode === 'hybrid') ? state.pinned.map(function (p) { return p.id; }) : [];

        var radiusVal = parseInt($('#snw-radius').value, 10);

        return {
            v: 1,
            api: (val('#snw-api') || '').trim() || L.apiBase,
            source_name: (val('#snw-source-name') || '').trim() || L.sourceName,
            source_url: (val('#snw-source-url') || '').trim() || L.sourceUrl,
            widget_id: (val('#snw-widget-id') || '').trim(),
            partner: ($('#snw-partner').value || '').trim(),
            title: ($('#snw-title').value || '').trim(),
            mode: mode,
            category: category,
            tags: tags,
            include: include,
            exclude: parseIdList(val('#snw-exclude')),
            pinned: pinned,
            auto_count: clamp($('#snw-auto-count').value, 0, 20),
            limit: clamp($('#snw-limit').value, 1, 20),
            sort: $('#snw-sort').value,
            layout: $('#snw-layout').value,
            show: show,
            teaser: clamp($('#snw-teaser').value, 50, 400),
            design: {
                accent: $('#snw-color-accent').value,
                background: $('#snw-color-background').value,
                text: $('#snw-color-text').value,
                muted: $('#snw-color-muted').value,
                border: $('#snw-color-border').value,
                link: $('#snw-color-link').value,
                radius: (isNaN(radiusVal) ? 8 : radiusVal),
                spacing: $('#snw-spacing').value,
                typography: $('#snw-typography').value,
                columns: clamp($('#snw-columns').value, 1, 4),
                image_ratio: $('#snw-image-ratio').value,
                image_fit: $('#snw-image-fit').value,
                image_position: $('#snw-image-position').value,
                date_format: $('#snw-date-format').value,
                heading_level: $('#snw-heading-level').value,
                theme: $('#snw-theme').value,
                shadow: $('#snw-shadow').value,
                separator: $('#snw-separator').value,
                align: $('#snw-align').value,
                title_length: clamp($('#snw-title-length').value, 0, 200),
                link_mode: $('#snw-link-mode').value,
                custom_css: $('#snw-custom-css').value
            },
            readmore_label: ($('#snw-readmore-label').value || '').trim(),
            empty_label: ($('#snw-empty-label').value || '').trim(),
            error_label: ($('#snw-error-label').value || '').trim(),
            on_error: $('#snw-on-error').value
        };
    }

    function applyConfig(cfg) {
        cfg = cfg || {};
        var d = cfg.design || {};
        $('#snw-name').value = state.name || '';
        $('#snw-partner').value = cfg.partner || '';
        $('#snw-title').value = cfg.title || '';
        $('#snw-mode').value = cfg.mode || 'latest';
        $('#snw-limit').value = cfg.limit || 5;
        $('#snw-auto-count').value = (cfg.auto_count !== undefined && cfg.auto_count !== '') ? cfg.auto_count : 3;
        $('#snw-sort').value = cfg.sort || 'newest';
        $('#snw-layout').value = cfg.layout || 'list';
        syncPicker('#snw-layout-picker', '.snw-layout-opt', 'data-layout', $('#snw-layout').value);
        $('#snw-teaser').value = cfg.teaser || 180;
        $('#snw-radius').value = (d.radius !== undefined && d.radius !== '') ? d.radius : 8;
        $('#snw-spacing').value = d.spacing || 'normal';
        $('#snw-typography').value = d.typography || 'host';
        syncPicker('#snw-radius-picker', '[data-radius]', 'data-radius', $('#snw-radius').value);
        syncPicker('#snw-spacing-picker', '[data-spacing]', 'data-spacing', $('#snw-spacing').value);
        $('#snw-on-error').value = cfg.on_error || 'message';

        $('#snw-show-image').checked = !cfg.show || cfg.show.image !== false;
        $('#snw-show-date').checked = !cfg.show || cfg.show.date !== false;
        $('#snw-show-category').checked = !!(cfg.show && cfg.show.category);
        $('#snw-show-excerpt').checked = !cfg.show || cfg.show.excerpt !== false;
        $('#snw-show-readmore').checked = !cfg.show || cfg.show.readmore !== false;
        $('#snw-show-branding').checked = !cfg.show || cfg.show.branding !== false;
        $('#snw-show-author').checked = !!(cfg.show && cfg.show.author);

        $('#snw-color-accent').value = d.accent || '#c59a20';
        $('#snw-color-background').value = d.background || '';
        $('#snw-color-text').value = d.text || '';
        $('#snw-color-muted').value = d.muted || '';
        $('#snw-color-border').value = d.border || '';
        $('#snw-color-link').value = d.link || '';

        $('#snw-columns').value = d.columns || 2;
        $('#snw-image-ratio').value = d.image_ratio || '16:9';
        $('#snw-image-fit').value = d.image_fit || 'cover';
        $('#snw-image-position').value = d.image_position || 'left';
        $('#snw-date-format').value = d.date_format || 'absolute';
        $('#snw-heading-level').value = d.heading_level || 'h3';
        $('#snw-theme').value = d.theme || 'light';
        syncPicker('#snw-theme-picker', '.snw-seg-opt', 'data-theme', $('#snw-theme').value);
        $('#snw-shadow').value = d.shadow || 'none';
        $('#snw-separator').value = d.separator || 'line';
        $('#snw-align').value = d.align || 'left';
        $('#snw-title-length').value = (d.title_length !== undefined && d.title_length !== '') ? d.title_length : 0;
        $('#snw-link-mode').value = d.link_mode || 'title';
        $('#snw-custom-css').value = d.custom_css || '';

        $('#snw-readmore-label').value = cfg.readmore_label || '';
        $('#snw-empty-label').value = cfg.empty_label || '';
        $('#snw-error-label').value = cfg.error_label || '';

        setVal('#snw-api', cfg.api || L.apiBase);
        setVal('#snw-source-name', cfg.source_name || L.sourceName);
        setVal('#snw-source-url', cfg.source_url || L.sourceUrl);
        setVal('#snw-widget-id', cfg.widget_id || '');

        setVal('#snw-exclude', (cfg.exclude || []).join(','));

        var $cat = $('#snw-category');
        $cat.value = (cfg.category && cfg.category.length) ? String(cfg.category[0]) : '';

        state.tags = (cfg.tags || []).map(function (id) { return { id: id, name: String(id) }; });
        renderTagChips();

        state.posts = (cfg.include || []).map(function (id) { return { id: id, title: String(id), date: '' }; });
        renderSelected('#snw-selected-posts', 'posts', makeRemove('posts'));

        state.pinned = (cfg.pinned || []).map(function (id) { return { id: id, title: String(id), date: '' }; });
        renderSelected('#snw-pinned-list', 'pinned', makeRemove('pinned'));

        if (window.jQuery && jQuery.fn.wpColorPicker) {
            $all('.snw-color__input').forEach(function (el) { jQuery(el).wpColorPicker('color', el.value); });
        }

        applyModeVisibility();
        updateModeHint();
        updatePreview();
        hydratePreset(cfg);
    }

    // ------------------------------------------------------------------
    // Live preview
    // ------------------------------------------------------------------
    function updatePreview() {
        if (!W || !W.encodeConfig) { return; }
        var cfg = getConfig();
        if (L && L.branding) {
            cfg = Object.assign({}, cfg, { branding: L.branding });
        }
        var raw = $('#snw-raw');
        if (raw) { raw.value = JSON.stringify(cfg, null, 2); }
        var el = $('#snw-preview');
        if (!el) { return; }
        el.setAttribute('data-config', W.encodeConfig(cfg));
        el.className = 'steigerwald-news-widget snw-preview';
        W.refresh(el);
    }

    function applyModeVisibility() {
        var mode = $('#snw-mode').value;
        var layout = $('#snw-layout').value;
        var isPublic = !!document.querySelector('.snw-builder--public');
        $all('.snw-cond').forEach(function (el) {
            var modes = (el.getAttribute('data-show-modes') || '').split(',');
            var showMode = !modes[0] || modes.indexOf(mode) !== -1;
            var layouts = (el.getAttribute('data-show-layout') || '').split(',');
            var showLayout = !layouts[0] || layouts.indexOf(layout) !== -1;
            var cb = el.getAttribute('data-show-checkbox');
            var showCb = !cb || !!(document.getElementById(cb) && document.getElementById(cb).checked);
            el.style.display = (showMode && showLayout && showCb) ? '' : 'none';
        });
        // "Manuelle Reihenfolge" only applies to manually chosen posts (public creator).
        if (isPublic) {
            var manualOpt = document.querySelector('.snw-sort-manual');
            if (manualOpt) { manualOpt.style.display = (mode === 'manual') ? '' : 'none'; }
        }
    }

    var MODE_HINTS = {
        latest:        'Zeigt automatisch deine neuesten Beiträge.',
        category:      'Nur Beiträge aus einer gewählten Kategorie.',
        tags:          'Beiträge, die mindestens ein ausgewähltes Schlagwort tragen.',
        category_tags: 'Kategorie und Schlagwörter kombiniert.',
        manual:        'Du wählst die Beiträge einzeln – in genau dieser Reihenfolge.',
        hybrid:        'Feste, angeheftete Beiträge oben, darunter automatische.'
    };

    function updateModeHint() {
        var hint = $('#snw-mode-hint');
        if (!hint) { return; }
        hint.textContent = MODE_HINTS[$('#snw-mode').value] || '';
    }

    function syncPicker(pickerSel, optSel, dataAttr, value) {
        var picker = $(pickerSel);
        if (!picker) { return; }
        $all(optSel, picker).forEach(function (b) {
            var on = b.getAttribute(dataAttr) === String(value);
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------
    function loadCategories() {
        var $cat = $('#snw-category');
        if (!$cat) { return; }
        $cat.innerHTML = '<option value="">— bitte wählen —</option>';
        rest('categories', { per_page: 100, _fields: 'id,name', orderby: 'name', order: 'asc' })
            .then(function (rows) {
                if (!Array.isArray(rows)) { return; }
                rows.forEach(function (r) {
                    var o = document.createElement('option');
                    o.value = r.id;
                    o.textContent = r.name;
                    $cat.appendChild(o);
                });
            })
            .catch(function () { /* categories optional */ });
    }

    // ------------------------------------------------------------------
    // Tag token input
    // ------------------------------------------------------------------
    function renderTagChips() {
        var list = $('#snw-tag-list');
        if (!list) { return; }
        list.innerHTML = '';
        state.tags.forEach(function (t) {
            var li = document.createElement('li');
            li.className = 'snw-token__chip';
            li.dataset.id = t.id;
            li.innerHTML = '<span>' + escapeHtml(t.name) + '</span> <button type="button" aria-label="Entfernen">×</button>';
            li.querySelector('button').addEventListener('click', function () {
                state.tags = state.tags.filter(function (x) { return x.id !== t.id; });
                renderTagChips();
                updatePreview();
            });
            list.appendChild(li);
        });
    }

    function searchTags(q) {
        var box = $('#snw-tag-results');
        if (!box) { return; }
        rest('tags', { search: q, per_page: 20, _fields: 'id,name' }).then(function (rows) {
            if (!Array.isArray(rows) || !rows.length) {
                box.innerHTML = '<li class="snw-token__noresult">' + (I.noTags || 'Keine Schlagwörter gefunden.') + '</li>';
                return;
            }
            box.innerHTML = '';
            rows.forEach(function (r) {
                if (state.tags.some(function (t) { return t.id === r.id; })) { return; }
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.textContent = r.name;
                li.addEventListener('click', function () {
                    state.tags.push({ id: r.id, name: r.name });
                    renderTagChips();
                    box.innerHTML = '';
                    $('#snw-tag-search').value = '';
                    updatePreview();
                });
                box.appendChild(li);
            });
        }).catch(function () { box.innerHTML = ''; });
    }

    // ------------------------------------------------------------------
    // Post search (manual + pinned)
    // ------------------------------------------------------------------
    function doPostSearch(q, resultsSel, listSel, key) {
        var box = $(resultsSel);
        if (!box) { return; }
        rest('posts', { search: q, per_page: 8, status: 'publish', _fields: 'id,date,title', _embed: 'wp:featuredmedia' })
            .then(function (rows) {
                box.innerHTML = '';
                if (!Array.isArray(rows)) { return; }
                rows.forEach(function (p) {
                    if (state[key].some(function (x) { return x.id === p.id; })) { return; }
                    var title = (p.title && p.title.rendered) ? (W.stripHtml ? W.stripHtml(p.title.rendered) : p.title.rendered) : (I.untitled || 'Beitrag');
                    var li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.innerHTML = '<span>' + escapeHtml(title) + '</span> <small>' + escapeHtml(p.date || '') + '</small>';
                    li.addEventListener('click', function () {
                        state[key].push({ id: p.id, title: title, date: p.date });
                        renderSelected(listSel, key, makeRemove(key));
                        box.innerHTML = '';
                    });
                    box.appendChild(li);
                });
            })
            .catch(function () { box.innerHTML = ''; });
    }

    function makeRemove(key) {
        return function (id) {
            state[key] = state[key].filter(function (item) { return item.id !== id; });
            renderSelected(key === 'posts' ? '#snw-selected-posts' : '#snw-pinned-list', key, makeRemove(key));
            updatePreview();
        };
    }

    function renderSelected(listSel, key, onRemove) {
        var ol = $(listSel);
        if (!ol) { return; }
        var items = state[key];
        ol.innerHTML = '';
        items.forEach(function (item, idx) {
            var li = document.createElement('li');
            li.className = 'snw-sel-item';
            li.draggable = true;
            li.dataset.id = item.id;
            li.innerHTML = '<span class="snw-sel-title">' + escapeHtml(item.title) + '</span>' +
                (item.date ? ' <span class="snw-sel-date">' + escapeHtml(item.date) + '</span>' : '');
            var up = document.createElement('button'); up.type = 'button'; up.className = 'snw-sel-up'; up.textContent = '↑'; up.setAttribute('aria-label', 'Hoch');
            var down = document.createElement('button'); down.type = 'button'; down.className = 'snw-sel-down'; down.textContent = '↓'; down.setAttribute('aria-label', 'Runter');
            var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'snw-sel-rm'; rm.textContent = '×'; rm.setAttribute('aria-label', 'Entfernen');
            up.addEventListener('click', function () {
                if (idx > 0) { state[key].splice(idx, 1); state[key].splice(idx - 1, 0, item); renderSelected(listSel, key, onRemove); updatePreview(); }
            });
            down.addEventListener('click', function () {
                if (idx < items.length - 1) { state[key].splice(idx, 1); state[key].splice(idx + 1, 0, item); renderSelected(listSel, key, onRemove); updatePreview(); }
            });
            rm.addEventListener('click', function () { onRemove(item.id); });
            li.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(item.id)); });
            li.addEventListener('dragover', function (e) { e.preventDefault(); });
            li.addEventListener('drop', function (e) {
                e.preventDefault();
                var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
                var to = item.id;
                if (from === to) { return; }
                var fi = -1, ti = -1;
                state[key].forEach(function (it, i) { if (it.id === from) { fi = i; } if (it.id === to) { ti = i; } });
                if (fi === -1 || ti === -1) { return; }
                var moved = state[key].splice(fi, 1)[0];
                state[key].splice(ti, 0, moved);
                renderSelected(listSel, key, onRemove); updatePreview();
            });
            li.appendChild(up); li.appendChild(down); li.appendChild(rm);
            ol.appendChild(li);
        });
    }

    // ------------------------------------------------------------------
    // Rehydrate stored IDs to human-readable labels (edit saved preset)
    // ------------------------------------------------------------------
    function hydratePreset(cfg) {
        if (cfg.tags && cfg.tags.length) {
            rest('tags', { include: cfg.tags.join(','), per_page: 100, _fields: 'id,name' })
                .then(function (rows) {
                    if (Array.isArray(rows) && rows.length) {
                        var byId = {};
                        rows.forEach(function (r) { byId[r.id] = r.name; });
                        state.tags = cfg.tags.map(function (id) {
                            return { id: id, name: byId[id] || String(id) };
                        });
                        renderTagChips();
                    }
                })
                .catch(function () { /* keep id labels */ });
        }
        if (cfg.include && cfg.include.length) { hydratePosts('posts', '#snw-selected-posts', cfg.include); }
        if (cfg.pinned && cfg.pinned.length) { hydratePosts('pinned', '#snw-pinned-list', cfg.pinned); }
    }

    function hydratePosts(key, listSel, ids) {
        rest('posts', { include: ids.join(','), per_page: 100, status: 'publish', _fields: 'id,date,title' })
            .then(function (rows) {
                if (!Array.isArray(rows)) { return; }
                var map = {};
                rows.forEach(function (p) {
                    map[p.id] = {
                        id: p.id,
                        title: (p.title && p.title.rendered) ? (W.stripHtml ? W.stripHtml(p.title.rendered) : p.title.rendered) : String(p.id),
                        date: p.date || ''
                    };
                });
                state[key] = ids.map(function (id) { return map[id] || { id: id, title: String(id), date: '' }; });
                renderSelected(listSel, key, makeRemove(key));
                updatePreview();
            })
            .catch(function () { /* keep id labels */ });
    }

    // ------------------------------------------------------------------
    // Presets (AJAX)
    // ------------------------------------------------------------------
    var presetsStore = L.presets || [];

    function setStatus(msg, isError) {
        var el = $('#snw-save-status');
        if (el) {
            el.textContent = msg || '';
            el.classList.toggle('snw-status--error', !!isError);
        }
    }

    function tokenSnippet(code) {
        return '<div class="steigerwald-news-widget" data-code="' + escapeHtml(code) + '"></div>\n' +
            '<script src="' + L.widgetJsUrl + '" async><\/script>';
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                resolve();
            } catch (e) { reject(e); }
        });
    }

    function ajax(action, extra) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('snw_nonce', L.nonce);
        if (extra) { Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); }); }
        return fetch(L.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); });
    }

    function savePreset() {
        var cfg = getConfig();
        var name = $('#snw-name').value.trim() || (I.untitled || 'Unbenanntes Widget');
        state.name = name;
        setStatus('');
        ajax('snw_save_preset', { name: name, config: JSON.stringify(cfg), id: state.currentId })
            .then(function (res) {
                if (res.success) {
                    state.currentId = res.data.id;
                    $('#snw-widget-id').value = (res.data.config && res.data.config.widget_id) || '';
                    setStatus(I.saveOk || 'Gespeichert.');
                    loadPresets();
                } else {
                    setStatus(I.saveError || 'Fehler.');
                }
            })
            .catch(function () { setStatus(I.saveError || 'Fehler.'); });
    }

    // Public builder submit: send a partner request to the REST endpoint.
    function submitRequest() {
        var emailEl = $('#snw-req-email');
        var domainEl = $('#snw-req-domain');
        var nameEl = $('#snw-req-name');
        var email = emailEl ? emailEl.value.trim() : '';
        var domain = domainEl ? domainEl.value.trim() : '';
        var name = nameEl ? nameEl.value.trim() : '';

        if (!email || email.indexOf('@') === -1 || !domain) {
            setStatus(I.invalid || 'Bitte E-Mail und Domain ausfüllen.', true);
            return;
        }

        var btn = $('#snw-request-submit');
        if (btn) { btn.disabled = true; }
        setStatus(I.submitting || 'Wird gesendet …', false);

        var cfg = getConfig();
        var payload = { name: name, email: email, domain: domain, config: cfg };
        var url = L.restRequestUrl || '';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (res.status === 429) {
                throw new Error(I.rate || 'Zu viele Anfragen. Bitte versuche es später erneut.');
            }
            if (!res.ok) {
                return res.json().catch(function () { return {}; }).then(function (body) {
                    throw new Error((body && body.message) ? body.message : (I.error || 'Fehler.'));
                });
            }
            return res.json();
        }).then(function () {
            setStatus(I.ok || 'Danke! Deine Anfrage wurde übermittelt.', false);
            if (btn) { btn.disabled = false; }
        }).catch(function (err) {
            setStatus(err.message || (I.error || 'Fehler.'), true);
            if (btn) { btn.disabled = false; }
        });
    }

    function copyToken(id) {
        copyText(tokenSnippet(id)).then(function () {
            setStatus(I.copied || 'Kopiert.');
        }).catch(function () { setStatus(I.copyError || 'Kopieren fehlgeschlagen.'); });
    }

    function copyCurrent() {
        if (state.currentId) {
            copyToken(state.currentId);
            return;
        }
        var cfg = getConfig();
        var name = $('#snw-name').value.trim() || (I.untitled || 'Unbenanntes Widget');
        state.name = name;
        setStatus('');
        ajax('snw_save_preset', { name: name, config: JSON.stringify(cfg), id: '' })
            .then(function (res) {
                if (res.success) {
                    state.currentId = res.data.id;
                    $('#snw-widget-id').value = (res.data.config && res.data.config.widget_id) || '';
                    copyToken(state.currentId);
                } else {
                    setStatus(I.saveError || 'Fehler.');
                }
            })
            .catch(function () { setStatus(I.saveError || 'Fehler.'); });
    }

    function loadPresets() {
        ajax('snw_get_presets').then(function (res) {
            if (res.success && Array.isArray(res.data)) {
                presetsStore = res.data;
                renderPresetTable();
            }
        }).catch(function () { /* ignore */ });
    }

    function renderPresetTable() {
        var tbody = $('#snw-preset-table tbody');
        if (!tbody) { return; }
        if (!presetsStore.length) {
            tbody.innerHTML = '<tr class="snw-preset-empty"><td colspan="4">' +
                (I.empty || 'Keine Widgets gespeichert.') + '</td></tr>';
            return;
        }
        tbody.innerHTML = '';
        presetsStore.forEach(function (p) {
            var id = escapeHtml(p.id);
            var tr = document.createElement('tr');
            tr.dataset.presetId = p.id;
            tr.innerHTML =
                '<td class="snw-preset-name">' + escapeHtml(p.name) + '</td>' +
                '<td class="snw-preset-id">' + id + '</td>' +
                '<td>' + escapeHtml((p.config && p.config.mode) || '') + '</td>' +
                '<td class="snw-preset-actions">' +
                '<button type="button" class="button snw-act-edit" data-id="' + id + '">Bearbeiten</button> ' +
                '<button type="button" class="button snw-act-duplicate" data-id="' + id + '">Duplizieren</button> ' +
                '<button type="button" class="button snw-act-copy" data-id="' + id + '">Code kopieren</button> ' +
                '<button type="button" class="button snw-act-delete" data-id="' + id + '">Löschen</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    function onPresetAction(e) {
        var btn = e.target.closest('button');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var preset = presetsStore.filter(function (p) { return p.id === id; })[0];
        if (btn.classList.contains('snw-act-edit') && preset) {
            state.currentId = id;
            state.name = preset.name;
            $('#snw-name').value = preset.name;
            applyConfig(preset.config);
        } else if (btn.classList.contains('snw-act-duplicate')) {
            ajax('snw_duplicate_preset', { id: id }).then(function () { loadPresets(); });
        } else if (btn.classList.contains('snw-act-copy') && preset) {
            copyToken(preset.id);
        } else if (btn.classList.contains('snw-act-delete')) {
            if (window.confirm(I.confirmDelete || 'Wirklich löschen?')) {
                ajax('snw_delete_preset', { id: id }).then(function () {
                    if (state.currentId === id) { state.currentId = ''; }
                    loadPresets();
                });
            }
        }
    }

    // ------------------------------------------------------------------
    // Partner requests
    // ------------------------------------------------------------------
    function requestsContainer() { return $('#snw-requests-list'); }
    function requestDetail() { return $('#snw-request-detail'); }

    function loadRequests() {
        ajax('snw_list_requests').then(function (res) {
            if (res.success && Array.isArray(res.data)) {
                renderRequests(res.data);
            } else {
                requestsContainer().innerHTML = '<p>' + (I.empty || 'Keine Anfragen.') + '</p>';
            }
        }).catch(function () {
            requestsContainer().innerHTML = '<p>Fehler beim Laden.</p>';
        });
    }

    function renderRequests(list) {
        var box = requestsContainer();
        if (!box) { return; }
        if (!list.length) {
            box.innerHTML = '<p>Keine Anfragen vorhanden.</p>';
            return;
        }
        var rows = list.map(function (r) {
            var cfg = r.config || {};
            var summ = (cfg.mode || 'latest') + ' / ' + (cfg.layout || 'list');
            return '<tr data-req-id="' + escapeHtml(r.id) + '">' +
                '<td>' + escapeHtml(r.email || '') + '</td>' +
                '<td>' + escapeHtml(r.name || '—') + '</td>' +
                '<td>' + escapeHtml(r.domain || '') + '</td>' +
                '<td>' + escapeHtml(summ) + '</td>' +
                '<td>' + escapeHtml(r.status || 'pending') + '</td>' +
                '<td class="snw-req-actions">' +
                '<button type="button" class="button snw-req-preview" data-id="' + escapeHtml(r.id) + '">Vorschau</button> ' +
                (r.status === 'accepted'
                    ? '<button type="button" class="button snw-req-codes" data-id="' + escapeHtml(r.id) + '">Codes</button> '
                    : '<button type="button" class="button button-primary snw-req-accept" data-id="' + escapeHtml(r.id) + '">Akzeptieren</button> ') +
                '<button type="button" class="button snw-req-reject" data-id="' + escapeHtml(r.id) + '">Ablehnen</button>' +
                '</td></tr>';
        }).join('');
        box.innerHTML = '<table class="wp-list-table widefat fixed striped snw-req-table"><thead><tr>' +
            '<th>E-Mail</th><th>Name</th><th>Domain</th><th>Modus/Layout</th><th>Status</th><th>Aktionen</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function previewRequest(req) {
        var cfg = {};
        for (var k in req.config) { if (Object.prototype.hasOwnProperty.call(req.config, k)) { cfg[k] = req.config[k]; } }
        cfg.api = cfg.api || L.apiBase;
        cfg.source_name = cfg.source_name || L.sourceName;
        cfg.source_url = cfg.source_url || L.sourceUrl;

        var box = requestDetail();
        box.innerHTML = '<h3>Vorschau</h3><div class="snw-req-preview" id="snw-req-preview"></div>';
        var el = $('#snw-req-preview');
        if (W && W.render) {
            el.className = 'steigerwald-news-widget';
            W.render(el, cfg);
        }
    }

    function acceptRequest(id) {
        if (!window.confirm('Anfrage akzeptieren und Einbettungscode erzeugen?')) { return; }
        ajax('snw_accept_request', { id: id }).then(function (res) {
            if (res.success) {
                showAccepted(res.data);
                loadRequests();
            } else {
                requestDetail().innerHTML = '<p>Fehler: ' + escapeHtml((res.data && res.data.message) || '') + '</p>';
            }
        }).catch(function () { requestDetail().innerHTML = '<p>Fehler.</p>'; });
    }

    function showAccepted(data) {
        var box = requestDetail();
        box.innerHTML =
            '<h3>Einbettungscode für ' + escapeHtml(data.email) + '</h3>' +
            '<p><label>WordPress-Shortcode</label><textarea class="large-text code" rows="2" readonly>' + escapeHtml(data.shortcode) + '</textarea></p>' +
            '<p><label>HTML-Snippet (beliebige Website)</label><textarea class="large-text code" rows="4" readonly>' + escapeHtml(data.html) + '</textarea></p>' +
            '<p><a class="button button-primary" href="' + escapeHtml(data.mailto) + '">E-Mail an ' + escapeHtml(data.email) + ' öffnen (mailto)</a></p>';
    }

    function rejectRequest(id) {
        if (!window.confirm('Anfrage ablehnen und löschen?')) { return; }
        ajax('snw_reject_request', { id: id }).then(function () { loadRequests(); requestDetail().innerHTML = ''; });
    }

    function onRequestsAction(e) {
        var btn = e.target.closest('button');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var list = SNW_Requests_cache || [];
        var req = list.filter(function (r) { return r.id === id; })[0];
        if (btn.classList.contains('snw-req-preview') && req) {
            previewRequest(req);
        } else if (btn.classList.contains('snw-req-accept')) {
            acceptRequest(id);
        } else if (btn.classList.contains('snw-req-codes')) {
            // Re-show stored codes by re-accepting is wrong; just reload list and
            // inform. For accepted items we re-derive via a lightweight call.
            ajax('snw_accept_request', { id: id }).then(function (res) {
                if (res.success) { showAccepted(res.data); }
            });
        } else if (btn.classList.contains('snw-req-reject')) {
            rejectRequest(id);
        }
    }

    function createPage() {
        var slug = $('#snw-builder-slug').value.trim();
        ajax('snw_create_page', { slug: slug }).then(function (res) {
            var el = $('#snw-page-status');
            if (res.success && res.data && res.data.url) {
                el.innerHTML = ' <a href="' + escapeHtml(res.data.url) + '" target="_blank" rel="noopener">' + escapeHtml(res.data.url) + '</a>';
            } else {
                el.textContent = ' Fehler.';
            }
        }).catch(function () { $('#snw-page-status').textContent = ' Fehler.'; });
    }

    // Cache the latest request list so preview/accept can read full configs.
    var SNW_Requests_cache = [];
    loadRequests = function () {
        ajax('snw_list_requests').then(function (res) {
            if (res.success && Array.isArray(res.data)) {
                SNW_Requests_cache = res.data;
                renderRequests(res.data);
            } else {
                requestsContainer().innerHTML = '<p>' + (I.empty || 'Keine Anfragen.') + '</p>';
            }
        }).catch(function () {
            requestsContainer().innerHTML = '<p>Fehler beim Laden.</p>';
        });
    };

    // ------------------------------------------------------------------
    // Init
    // ------------------------------------------------------------------
    function initManage() {
        var table = $('#snw-preset-table');
        if (table) { table.addEventListener('click', onPresetAction); }

        var loadBtn = $('#snw-load-requests');
        if (loadBtn) {
            loadBtn.addEventListener('click', function () { loadRequests(); });
            loadRequests();
        }
        var panel = $('#snw-requests-panel');
        if (panel) { panel.addEventListener('click', onRequestsAction); }
        var createBtn = $('#snw-create-page');
        if (createBtn) { createBtn.addEventListener('click', createPage); }

        loadPresets();
    }

    function initBuilder() {
        loadCategories();
        var apiEl = $('#snw-api');
        if (apiEl) { apiEl.value = L.apiBase; }
        var snEl = $('#snw-source-name');
        if (snEl) { snEl.value = L.sourceName; }
        var suEl = $('#snw-source-url');
        if (suEl) { suEl.value = L.sourceUrl; }

        if (window.jQuery && jQuery.fn.wpColorPicker) {
            $all('.snw-color__input').forEach(function (el) {
                jQuery(el).wpColorPicker({ width: 200 });
            });
        }

        var debouncedPreview = debounce(updatePreview, 300);
        var form = $('#snw-builder-form');
        if (form) {
            form.addEventListener('input', debouncedPreview);
            form.addEventListener('change', debouncedPreview);
            // Re-evaluate conditional fields (e.g. image-dependent options).
            form.addEventListener('change', function () { applyModeVisibility(); });
        }

        var pw = $('#snw-preview-width');
        var previewFrame = document.querySelector('.snw-preview-frame');
        function applyPreviewWidth() {
            if (!previewFrame) { return; }
            previewFrame.style.width = (pw && pw.value && pw.value !== '100%') ? pw.value : '';
        }
        if (pw) {
            pw.addEventListener('change', applyPreviewWidth);
            applyPreviewWidth();
        }

        var layoutPicker = $('#snw-layout-picker');
        if (layoutPicker) {
            layoutPicker.addEventListener('click', function (e) {
                var btn = e.target.closest('.snw-layout-opt');
                if (!btn) { return; }
                var val = btn.getAttribute('data-layout');
                $('#snw-layout').value = val;
                syncPicker('#snw-layout-picker', '.snw-layout-opt', 'data-layout', val);
                applyModeVisibility();
                updatePreview();
            });
        }

        var themePicker = $('#snw-theme-picker');
        if (themePicker) {
            themePicker.addEventListener('click', function (e) {
                var btn = e.target.closest('.snw-seg-opt');
                if (!btn) { return; }
                var val = btn.getAttribute('data-theme');
                $('#snw-theme').value = val;
                syncPicker('#snw-theme-picker', '.snw-seg-opt', 'data-theme', val);
                updatePreview();
            });
        }

        var radiusPicker = $('#snw-radius-picker');
        if (radiusPicker) {
            radiusPicker.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-radius]');
                if (!btn) { return; }
                var val = btn.getAttribute('data-radius');
                $('#snw-radius').value = val;
                syncPicker('#snw-radius-picker', '[data-radius]', 'data-radius', val);
                updatePreview();
            });
        }

        var spacingPicker = $('#snw-spacing-picker');
        if (spacingPicker) {
            spacingPicker.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-spacing]');
                if (!btn) { return; }
                var val = btn.getAttribute('data-spacing');
                $('#snw-spacing').value = val;
                syncPicker('#snw-spacing-picker', '[data-spacing]', 'data-spacing', val);
                updatePreview();
            });
        }

        $('#snw-mode').addEventListener('change', function () { applyModeVisibility(); updateModeHint(); updatePreview(); });
        $('#snw-layout').addEventListener('change', function () {
            applyModeVisibility();
            updateModeHint();
            updatePreview();
        });
        $('#snw-teaser').addEventListener('input', function () { var o = $('#snw-teaser-out'); if (o) { o.textContent = this.value; } });

        var tagInput = $('#snw-tag-search');
        if (tagInput) {
            tagInput.addEventListener('input', debounce(function () {
                var q = tagInput.value.trim();
                if (q.length < 2) { var b = $('#snw-tag-results'); if (b) { b.innerHTML = ''; } return; }
                searchTags(q);
            }, 250));
        }

        var postInput = $('#snw-post-search');
        if (postInput) {
            postInput.addEventListener('input', debounce(function () {
                var q = postInput.value.trim();
                if (q.length < 2) { $('#snw-post-results').innerHTML = ''; return; }
                doPostSearch(q, '#snw-post-results', '#snw-selected-posts', 'posts');
            }, 300));
        }

        var pinInput = $('#snw-pinned-search');
        if (pinInput) {
            pinInput.addEventListener('input', debounce(function () {
                var q = pinInput.value.trim();
                if (q.length < 2) { $('#snw-pinned-results').innerHTML = ''; return; }
                doPostSearch(q, '#snw-pinned-results', '#snw-pinned-list', 'pinned');
            }, 300));
        }

        if (L.isAdmin) {
            $('#snw-save').addEventListener('click', savePreset);
            $('#snw-copy').addEventListener('click', copyCurrent);
            $('#snw-reset').addEventListener('click', function () {
                state.currentId = ''; state.name = ''; $('#snw-name').value = '';
                applyConfig(L.defaultConfig || {});
                setStatus('');
            });
        } else {
            var reqBtn = $('#snw-request-submit');
            if (reqBtn) { reqBtn.addEventListener('click', submitRequest); }
        }

        applyModeVisibility();
        updateModeHint();
        syncPicker('#snw-layout-picker', '.snw-layout-opt', 'data-layout', $('#snw-layout').value);
        syncPicker('#snw-theme-picker', '.snw-seg-opt', 'data-theme', $('#snw-theme').value);
        updatePreview();

        // Public builder: keep the builder full-bleed.
        if (!L.isAdmin) {
            var b = document.querySelector('.snw-builder--public');
            var applyFullBleed = function () {
                if (!b) { return; }
                var vw = window.innerWidth;
                b.style.width = vw + 'px';
                b.style.marginLeft = 'calc(50% - ' + (vw / 2) + 'px)';
            };
            applyFullBleed();
            window.addEventListener('resize', applyFullBleed);
        }

        // --- Direction-aware sticky Live Preview (right column). ---
        // The editor (left) is normal document flow and scrolls with the page.
        // The preview has three states:
        //   * FREE: in normal flow, moving with the page.
        //   * BOTTOM: docked to the bottom of the viewport while scrolling down
        //     (so the taller editor can keep scrolling while the preview stays
        //     visible).
        //   * TOP: docked to the top of the viewport once the preview reaches it.
        // A transition never moves the panel by itself: when leaving a docked
        // state the CURRENT visual Y is captured and converted into a
        // container-relative (absolute) position, so the panel stays exactly
        // where it is and then travels with the page. It only re-docks when it
        // physically reaches a viewport edge (within a small threshold, so the
        // dock itself is jump-free). There is one page scrollbar; the panel moves
        // relative to the viewport, its content never scrolls internally — no
        // editor->preview sync and no preview scrollbar.
        var workspace = document.querySelector('.snw-builder');
        var preview = document.querySelector('.snw-builder__preview');
        if (workspace && preview) {
            var bottomGap = parseInt(
                getComputedStyle(workspace).getPropertyValue('--snw-bottom-gap'), 10
            ) || 24;
            var topGap = L.isAdmin ? 32 : 0;
            var PIN_THRESHOLD = 150; // dock only when within this of an edge
            var state = 'free'; // 'free' | 'bottom' | 'top'
            var lastY = window.scrollY;
            var raf = 0;
            var isDesktop = function () { return window.innerWidth > 900; };

            var previewHeight = function () { return preview.getBoundingClientRect().height; };

            // BOTTOM dock (seen while scrolling down).
            var pinBottom = function () {
                var h = previewHeight();
                var vh = window.innerHeight;
                preview.style.position = 'sticky';
                preview.style.top = (vh - h - bottomGap) + 'px';
                preview.style.bottom = 'auto';
                preview.style.left = '';
                preview.style.width = '';
                preview.style.transform = '';
                state = 'bottom';
            };

            // TOP dock (seen once the preview reaches the top edge).
            var pinTop = function () {
                preview.style.position = 'sticky';
                preview.style.top = topGap + 'px';
                preview.style.bottom = 'auto';
                preview.style.left = '';
                preview.style.width = '';
                preview.style.transform = '';
                state = 'top';
            };

            // Release into a FREE, container-relative state while preserving the
            // exact current visual Y (so the transition itself never moves it).
            var releaseToFree = function () {
                var rect = preview.getBoundingClientRect();
                var parentRect = workspace.getBoundingClientRect();
                preview.style.position = 'absolute';
                preview.style.top = (rect.top - parentRect.top) + 'px';
                preview.style.left = (rect.left - parentRect.left) + 'px';
                preview.style.width = rect.width + 'px';
                preview.style.bottom = 'auto';
                preview.style.transform = '';
                state = 'free';
            };

            var update = function () {
                raf = 0;
                var y = window.scrollY;
                if (y === lastY) { return; }
                var direction = y > lastY ? 'down' : 'up';
                lastY = y;

                if (state === 'bottom') {
                    if (direction === 'up') { releaseToFree(); }
                } else if (state === 'top') {
                    if (direction === 'down') { releaseToFree(); }
                } else { // free
                    var rect = preview.getBoundingClientRect();
                    var vh = window.innerHeight;
                    // Directional: scrolling DOWN pins at the bottom edge,
                    // scrolling UP pins at the top edge — matching the user's
                    // state machine (never the opposite edge, which would read
                    // as a teleport).
                    if (direction === 'down' &&
                        rect.bottom <= vh - bottomGap &&
                        (vh - bottomGap) - rect.bottom <= PIN_THRESHOLD) {
                        pinBottom();
                    } else if (direction === 'up' &&
                        rect.top <= topGap &&
                        topGap - rect.top <= PIN_THRESHOLD) {
                        pinTop();
                    }
                }
            };

            var onScroll = function () {
                if (!isDesktop()) {
                    // Mobile: stacked layout. Clear any inline position so the
                    // static media-query rule applies.
                    if (preview.style.position !== '') {
                        preview.style.position = '';
                        preview.style.top = '';
                        preview.style.left = '';
                        preview.style.width = '';
                        preview.style.transform = '';
                    }
                    return;
                }
                if (!raf) { raf = window.requestAnimationFrame(update); }
            };

            // Start in natural flow; scrolling pins at the edges (desktop only).
            if (isDesktop()) {
                preview.style.position = 'sticky';
                preview.style.top = '';
                preview.style.bottom = '';
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', function () {
                if (!isDesktop()) {
                    state = 'free';
                    preview.style.position = '';
                    preview.style.top = '';
                    preview.style.left = '';
                    preview.style.width = '';
                    preview.style.transform = '';
                    return;
                }
                if (state === 'bottom') { pinBottom(); }
                else if (state === 'top') { pinTop(); }
                // free: stays in natural flow, nothing to recompute
            });
            if (window.ResizeObserver) {
                new ResizeObserver(function () {
                    if (!isDesktop()) { return; }
                    if (state === 'bottom') { pinBottom(); }
                    else if (state === 'top') { pinTop(); }
                }).observe(preview);
            }
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function () {
                    if (!isDesktop()) { return; }
                    if (state === 'bottom') { pinBottom(); }
                    else if (state === 'top') { pinTop(); }
                });
            }
        }
    }

    function init() {
        if ($('#snw-builder-form')) { initBuilder(); }
        if ($('#snw-preset-table') || $('#snw-requests-panel')) { initManage(); }
        if ($('#snw-partners-list')) { initPartners(); }
        if ($('#snw-branding-form')) { initSettings(); }
    }

    // ------------------------------------------------------------------
    // Partner page (accepted partners => 1 code per e-mail).
    // ------------------------------------------------------------------
    function initPartners() {
        var listEl = $('#snw-partners-list');
        if (!listEl) { return; }
        var cache = [];

        function render() {
            var q = ($('#snw-partner-search') ? $('#snw-partner-search').value : '').trim().toLowerCase();
            var st = $('#snw-partner-status') ? $('#snw-partner-status').value : '';
            var rows = cache.filter(function (p) {
                if (st && p.status !== st) { return false; }
                if (!q) { return true; }
                var hay = (p.id + ' ' + p.name + ' ' + p.email + ' ' + p.domain).toLowerCase();
                return hay.indexOf(q) !== -1;
            });
            if (!rows.length) {
                listEl.innerHTML = '<p>' + (I.partnerEmpty || 'Noch keine Partner freigegeben.') + '</p>';
                return;
            }
            var statusText = { active: (I.active || 'Aktiv'), idle: (I.idle || 'Im Ruhe'), removed: (I.removed || 'Entfernt'), unknown: (I.unknown || 'Unbekannt') };
            var table = '<table class="wp-list-table widefat fixed striped snw-preset-table"><thead><tr>' +
                '<th>' + (I.code || 'Code') + '</th>' +
                '<th>' + (I.name || 'Name') + '</th>' +
                '<th>' + (I.email || 'E-Mail') + '</th>' +
                '<th>' + (I.domain || 'Domain') + '</th>' +
                '<th>' + (I.created || 'Erstellt') + '</th>' +
                '<th>' + (I.lastSeen || 'Letzte Aktivität') + '</th>' +
                '<th>' + (I.status || 'Status') + '</th>' +
                '</tr></thead><tbody>';
            rows.forEach(function (p) {
                table += '<tr><td><strong>' + escapeHtml(p.id) + '</strong></td>' +
                    '<td>' + escapeHtml(p.name) + '</td>' +
                    '<td>' + escapeHtml(p.email) + '</td>' +
                    '<td>' + escapeHtml(p.domain) + '</td>' +
                    '<td>' + escapeHtml(p.created) + '</td>' +
                    '<td>' + escapeHtml(p.last_seen || '—') + '</td>' +
                    '<td>' + escapeHtml(statusText[p.status] || p.status) + '</td></tr>';
            });
            table += '</tbody></table>';
            listEl.innerHTML = table;
        }

        function load() {
            ajax('snw_list_partners').then(function (res) {
                if (res.success && Array.isArray(res.data)) {
                    cache = res.data;
                    render();
                } else {
                    listEl.innerHTML = '<p>' + (I.partnerEmpty || 'Noch keine Partner freigegeben.') + '</p>';
                }
            }).catch(function () {
                listEl.innerHTML = '<p>Fehler beim Laden.</p>';
            });
        }

        var loadBtn = $('#snw-load-partners');
        if (loadBtn) { loadBtn.addEventListener('click', load); }
        var searchEl = $('#snw-partner-search');
        if (searchEl) { searchEl.addEventListener('input', render); }
        var statusEl = $('#snw-partner-status');
        if (statusEl) { statusEl.addEventListener('change', render); }
        load();
    }

    // ------------------------------------------------------------------
    // Settings page: global widget branding.
    // ------------------------------------------------------------------
    function initSettings() {
        var form = $('#snw-branding-form');
        if (!form) { return; }
        var srcName = (L && L.sourceName) ? L.sourceName : '';
        var srcUrl = (L && L.sourceUrl) ? L.sourceUrl : '';
        var preview = $('#snw-branding-preview');
        var msg = $('#snw-branding-msg');

        var sizeInput = $('#snw-branding-size');
        var imgInput = $('#snw-branding-image');
        var textInput = $('#snw-branding-text');
        var nameInput = $('#snw-branding-name');
        var linkInput = $('#snw-branding-link');
        var textSizeInput = $('#snw-branding-text-size');

        function buildPreview() {
            var text = textInput.value.trim() || (I.brandingText || 'Nachrichten von');
            var name = nameInput.value.trim() || srcName;
            if (!name) { return; }
            var size = parseInt(sizeInput.value, 10) || 32;
            var textSize = parseInt(textSizeInput.value, 10) || 14;
            var img = imgInput.value.trim() || (srcUrl ? srcUrl.replace(/\/+$/, '') + '/favicon.ico' : '');
            var link = linkInput.value.trim() || srcUrl || '#';
            var root = preview ? preview.querySelector('.snw-root') : null;
            if (!root) { return; }
            root.innerHTML = '';
            var a = document.createElement('a');
            a.className = 'snw-branding';
            a.style.fontSize = textSize + 'px';
            a.setAttribute('href', link);
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
            if (img) {
                var ic = document.createElement('img');
                ic.className = 'snw-branding__icon';
                ic.setAttribute('src', img);
                ic.setAttribute('alt', '');
                ic.setAttribute('width', String(size));
                ic.setAttribute('height', String(size));
                ic.style.width = size + 'px';
                ic.style.height = size + 'px';
                a.appendChild(ic);
            }
            var meta = document.createElement('span');
            meta.className = 'snw-branding__meta';
            var t = document.createElement('span');
            t.className = 'snw-branding__text';
            t.textContent = text;
            meta.appendChild(t);
            var n = document.createElement('strong');
            n.className = 'snw-branding__name';
            n.textContent = name;
            meta.appendChild(n);
            a.appendChild(meta);
            root.appendChild(a);
        }

        [sizeInput, imgInput, textInput, nameInput, linkInput, textSizeInput].forEach(function (el) {
            if (el) { el.addEventListener('input', buildPreview); }
        });

        var mediaBtn = $('#snw-branding-media');
        if (mediaBtn && typeof wp !== 'undefined' && wp.media) {
            mediaBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var frame = wp.media({ title: (I.selectImage || 'Bild auswählen'), multiple: false });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    if (att && att.url) { imgInput.value = att.url; buildPreview(); }
                });
                frame.open();
            });
        }

        var saveBtn = $('#snw-save-branding');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                msg.textContent = '';
                ajax('snw_save_branding', {
                    image: imgInput.value.trim(),
                    image_size: sizeInput.value,
                    text_size: textSizeInput.value,
                    text: textInput.value.trim(),
                    name: nameInput.value.trim(),
                    link: linkInput.value.trim()
                }).then(function (res) {
                    if (res.success) {
                        msg.textContent = I.brandingSaved || 'Branding gespeichert.';
                        msg.className = 'snw-msg snw-msg--ok';
                    } else {
                        msg.textContent = I.brandingError || 'Speichern fehlgeschlagen.';
                        msg.className = 'snw-msg snw-msg--err';
                    }
                }).catch(function () {
                    msg.textContent = I.brandingError || 'Speichern fehlgeschlagen.';
                    msg.className = 'snw-msg snw-msg--err';
                });
            });
        }

        buildPreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

