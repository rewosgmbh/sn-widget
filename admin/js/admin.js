/**
 * Steigerwald-News Widget - Admin Builder.
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
            api: ($('#snw-api').value || '').trim() || L.apiBase,
            source_name: ($('#snw-source-name').value || '').trim() || L.sourceName,
            source_url: ($('#snw-source-url').value || '').trim() || L.sourceUrl,
            widget_id: ($('#snw-widget-id').value || '').trim(),
            partner: ($('#snw-partner').value || '').trim(),
            title: ($('#snw-title').value || '').trim(),
            mode: mode,
            category: category,
            tags: tags,
            include: include,
            exclude: parseIdList($('#snw-exclude').value),
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

        $('#snw-api').value = cfg.api || L.apiBase;
        $('#snw-source-name').value = cfg.source_name || L.sourceName;
        $('#snw-source-url').value = cfg.source_url || L.sourceUrl;
        $('#snw-widget-id').value = cfg.widget_id || '';

        $('#snw-exclude').value = (cfg.exclude || []).join(',');

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
        $all('.snw-cond').forEach(function (el) {
            var modes = (el.getAttribute('data-show-modes') || '').split(',');
            var layouts = (el.getAttribute('data-show-layout') || '').split(',');
            var showMode = !modes[0] || modes.indexOf(mode) !== -1;
            var showLayout = !layouts[0] || layouts.indexOf(layout) !== -1;
            el.style.display = (showMode && showLayout) ? '' : 'none';
        });
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
        $('#snw-api').value = L.apiBase;
        $('#snw-source-name').value = L.sourceName;
        $('#snw-source-url').value = L.sourceUrl;

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
    }

    function init() {
        if ($('#snw-builder-form')) { initBuilder(); }
        if ($('#snw-preset-table') || $('#snw-requests-panel')) { initManage(); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

