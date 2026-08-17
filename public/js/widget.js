/**
 * Steigerwald-News Widget — Public renderer (vanilla JS, no dependencies).
 *
 * Design goals:
 *  - Works on ANY host site (WordPress, Joomla, TYPO3, static, builders).
 *  - No frameworks, no jQuery, no external CDN, no external fonts.
 *  - Idempotent: safe if the script is included more than once.
 *  - Multiple widgets on one page are supported.
 *  - Never throws an uncaught error that could break the host page.
 *  - Pulls content exclusively from the WordPress Core REST API.
 *
 * The widget is intentionally stateless. All configuration arrives via the
 * `data-config` attribute (a URL-safe base64-encoded JSON string).
 */
(function () {
    'use strict';

    var VERSION = '1.1.0';

    // --- Unicode-safe base64url helpers -------------------------------
    var _atob = (typeof atob !== 'undefined')
        ? atob
        : function (s) { return Buffer.from(s, 'base64').toString('binary'); };
    var _btoa = (typeof btoa !== 'undefined')
        ? btoa
        : function (s) { return Buffer.from(s, 'binary').toString('base64'); };

    function b64urlEncode(str) {
        var bin = '';
        try { bin = unescape(encodeURIComponent(str)); } catch (e) { bin = str; }
        return _btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function b64urlDecode(str) {
        var s = String(str).replace(/-/g, '+').replace(/_/g, '/');
        var pad = s.length % 4;
        if (pad) { s += '===='.slice(pad); }
        var bin;
        try { bin = _atob(s); } catch (e) { return ''; }
        try { return decodeURIComponent(escape(bin)); } catch (e) { return bin; }
    }

    var STYLE_ID = 'snw-widget-styles';

    // ------------------------------------------------------------------
    // Pure helpers (also unit-tested in Node)
    // ------------------------------------------------------------------

    function decodeConfig(raw) {
        if (!raw) { return null; }
        var json;
        if (typeof raw === 'string' && raw.charAt(0) === '{') {
            json = raw; // already plain JSON (debugging)
        } else {
            json = b64urlDecode(raw);
        }
        if (!json) { return null; }
        try {
            var parsed = JSON.parse(json);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function encodeConfig(obj) {
        return b64urlEncode(JSON.stringify(obj));
    }

    function stripHtml(html) {
        if (typeof document === 'undefined') { return String(html || ''); }
        var div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function decodeEntities(text) {
        if (typeof document === 'undefined') { return String(text || ''); }
        var ta = document.createElement('textarea');
        ta.innerHTML = text || '';
        return ta.value;
    }

    function truncate(text, length) {
        text = String(text == null ? '' : text).replace(/\s+/g, ' ').trim();
        length = parseInt(length, 10);
        if (!length || isNaN(length) || length < 1) { length = 180; }
        if (text.length <= length) { return text; }
        var cut = text.slice(0, length);
        var lastSpace = cut.lastIndexOf(' ');
        if (lastSpace > length * 0.6) { cut = cut.slice(0, lastSpace); }
        return cut.replace(/[\s.,;:!?-]+$/, '') + '…';
    }

    function trackedUrl(rawUrl, partner, widgetId) {
        if (!rawUrl) { return ''; }
        var url;
        try { url = new URL(rawUrl); } catch (e) { return rawUrl; }
        if (partner) {
            url.searchParams.set('utm_source', partner);
            url.searchParams.set('utm_medium', 'referral');
            url.searchParams.set('utm_campaign', 'steigerwald_news_widget');
            if (widgetId) { url.searchParams.set('utm_content', widgetId); }
        }
        return url.toString();
    }

    function featuredImageUrl(post) {
        var media = post && post._embedded && post._embedded['wp:featuredmedia'];
        if (!media || !media.length) { return ''; }
        var item = media[0];
        var sizes = (item && item.media_details && item.media_details.sizes) || {};
        return sizes.medium_large && sizes.medium_large.source_url
            || sizes.large && sizes.large.source_url
            || sizes.medium && sizes.medium.source_url
            || (item.source_url || '');
    }

    function categoryNames(post) {
        var embedded = post && post._embedded && post._embedded['wp:term'];
        var names = [];
        if (!embedded || !embedded.length) { return names; }
        for (var i = 0; i < embedded.length; i++) {
            var group = embedded[i];
            if (!group || !group.length) { continue; }
            for (var j = 0; j < group.length; j++) {
                if (group[j] && group[j].taxonomy === 'category' && group[j].name) {
                    names.push({ id: group[j].id, name: group[j].name });
                }
            }
        }
        return names;
    }

    function formatDate(iso) {
        if (!iso) { return ''; }
        var d = new Date(iso);
        if (isNaN(d.getTime())) { return String(iso); }
        try {
            return new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'long', year: 'numeric' }).format(d);
        } catch (e) {
            return d.toLocaleDateString();
        }
    }

    function sortPosts(posts, sort) {
        if (!posts || !posts.length) { return posts; }
        var out = posts.slice();
        if (sort === 'oldest') {
            out.sort(function (a, b) { return new Date(a.date) - new Date(b.date); });
        } else if (sort === 'title') {
            out.sort(function (a, b) {
                return stripHtml(a.title && a.title.rendered).localeCompare(stripHtml(b.title && b.title.rendered), 'de');
            });
        }
        // 'newest' and 'manual' keep API order (date desc / include order).
        return out;
    }

    function buildCacheKey(config) {
        return [
            config.api,
            config.type || 'posts',
            config.mode,
            (config.category || []).join(','),
            (config.tags || []).join(','),
            (config.include || []).join(','),
            (config.exclude || []).join(','),
            (config.pinned || []).join(','),
            config.auto_count,
            config.limit,
            config.sort,
            config.show && config.show.category ? 1 : 0
        ].join('|');
    }

    // ------------------------------------------------------------------
    // Lightweight cache (memory + sessionStorage, TTL ~10 min)
    // ------------------------------------------------------------------
    var memoryCache = {};
    var TTL = 10 * 60 * 1000;

    function cacheGet(key) {
        if (memoryCache[key] && memoryCache[key].exp > Date.now()) {
            return memoryCache[key].data;
        }
        try {
            var raw = (typeof sessionStorage !== 'undefined') && sessionStorage.getItem('snw:' + key);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (parsed.exp > Date.now()) { memoryCache[key] = parsed; return parsed.data; }
            }
        } catch (e) { /* storage unavailable - ignore */ }
        return null;
    }

    function cacheSet(key, data) {
        memoryCache[key] = { exp: Date.now() + TTL, data: data };
        try {
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.setItem('snw:' + key, JSON.stringify({ exp: Date.now() + TTL, data: data }));
            }
        } catch (e) { /* ignore */ }
    }

    // ------------------------------------------------------------------
    // Data fetching (WordPress Core REST only)
    // ------------------------------------------------------------------
    function fetchJson(url, controller) {
        var signal = controller ? controller.signal : undefined;
        return fetch(url, {
            method: 'GET',
            mode: 'cors',
            credentials: 'omit',
            headers: { 'Accept': 'application/json' },
            signal: signal
        }).then(function (response) {
            if (!response.ok) { throw new Error('HTTP ' + response.status); }
            return response.json();
        });
    }

    // `_links` must be present for WordPress to populate `_embedded`.
    var FIELDS = 'id,date,link,title,excerpt,categories,tags,featured_media,_links,_embedded';

    function buildAutoUrl(base, config, limit, exclude) {
        var type = config.type || 'posts';
        var url = new URL(base.replace(/\/+$/, '') + '/' + type);
        url.searchParams.set('per_page', String(Math.max(1, Math.min(20, limit || 5))));
        url.searchParams.set('status', 'publish');
        url.searchParams.set('_fields', FIELDS);

        // Embed both media and terms whenever either is needed; requesting
        // only one would drop the other from the rendered widget.
        var embeds = [];
        if (config.show && config.show.image) { embeds.push('wp:featuredmedia'); }
        if (config.show && config.show.category) { embeds.push('wp:term'); }
        url.searchParams.set('_embed', embeds.length ? embeds.join(',') : 'wp:featuredmedia');

        // Only filter by taxonomy for modes that actually use it, so hidden
        // category/tag values never leak across content modes.
        var filterModes = { category: true, tags: true, 'category_tags': true, hybrid: true };
        if (filterModes[config.mode] && config.category && config.category.length) {
            url.searchParams.set('categories', config.category.join(','));
        }
        if (filterModes[config.mode] && config.tags && config.tags.length) {
            url.searchParams.set('tags', config.tags.join(','));
        }
        if (exclude && exclude.length) {
            url.searchParams.set('exclude', exclude.join(','));
        }

        var sort = config.sort || 'newest';
        if (sort === 'oldest') {
            url.searchParams.set('orderby', 'date');
            url.searchParams.set('order', 'asc');
        } else if (sort === 'title') {
            url.searchParams.set('orderby', 'title');
            url.searchParams.set('order', 'asc');
        } else {
            url.searchParams.set('orderby', 'date');
            url.searchParams.set('order', 'desc');
        }
        return url.toString();
    }

    function buildByIdUrl(base, ids, type) {
        var t = type || 'posts';
        var url = new URL(base.replace(/\/+$/, '') + '/' + t);
        url.searchParams.set('include', '');
        url.searchParams.set('include', ids.join(','));
        url.searchParams.set('orderby', 'include');
        url.searchParams.set('status', 'publish');
        url.searchParams.set('_fields', FIELDS);
        url.searchParams.set('_embed', 'wp:term,wp:featuredmedia');
        return url.toString();
    }

    function fetchByIds(base, ids, controller, withCategories, type) {
        if (!ids || !ids.length) { return Promise.resolve([]); }
        var url = buildByIdUrl(base, ids, type || 'posts');
        return fetchJson(url, controller).then(function (posts) {
            return Array.isArray(posts) ? posts : [];
        });
    }

    function fetchPosts(config, controller) {
        var base = config.api;
        var type = config.type || 'posts';
        if (config.mode === 'manual') {
            return fetchByIds(base, config.include, controller, false, type);
        }
        if (config.mode === 'hybrid') {
            var pinned = fetchByIds(base, config.pinned, controller, false, type);
            var autoCount = parseInt(config.auto_count, 10);
            if (autoCount > 0) {
                var auto = fetchJson(buildAutoUrl(base, config, autoCount, config.pinned), controller)
                .then(function (posts) { return Array.isArray(posts) ? posts : []; });
                return Promise.all([pinned, auto]).then(function (res) {
                    return res[0].concat(res[1]).slice(0, Math.max(1, parseInt(config.limit, 10) || 5));
                });
            }
            return pinned.then(function (posts) { return Array.isArray(posts) ? posts : []; });
        }
        return fetchJson(buildAutoUrl(base, config, config.limit, config.exclude), controller)
            .then(function (posts) { return sortPosts(Array.isArray(posts) ? posts : [], config.sort); });
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------
    var TEXTS = {
        readmore: 'Artikel lesen',
        empty: 'Aktuell sind keine passenden Beiträge vorhanden.',
        error: 'Die Nachrichten konnten gerade nicht geladen werden.',
        loading: 'Nachrichten werden geladen …',
        untitled: 'Beitrag',
        branding: 'Nachrichten bereitgestellt von'
    };

    function makeLink(href, label, className, rel) {
        var a = document.createElement('a');
        a.href = href;
        a.textContent = label;
        if (className) { a.className = className; }
        a.target = '_blank';
        a.rel = rel || 'noopener noreferrer';
        return a;
    }

    function applyDesign(root, config) {
        var d = config.design || {};
        root.style.setProperty('--snw-accent', d.accent || '#c59a20');
        root.style.setProperty('--snw-bg', d.background || 'transparent');
        root.style.setProperty('--snw-text', d.text || 'inherit');
        root.style.setProperty('--snw-link', d.link || 'var(--snw-accent)');
        root.style.setProperty('--snw-radius', (parseInt(d.radius, 10) || 0) + 'px');
        if (d.muted) { root.style.setProperty('--snw-muted', d.muted); }
        if (d.border) { root.style.setProperty('--snw-border', d.border); }

        var spacing = d.spacing || 'normal';
        var gap = spacing === 'compact' ? 10 : (spacing === 'spacious' ? 24 : 16);
        var pad = spacing === 'compact' ? 8 : (spacing === 'spacious' ? 20 : 14);
        root.style.setProperty('--snw-gap', gap + 'px');
        root.style.setProperty('--snw-pad', pad + 'px');

        var fonts = {
            host: 'inherit',
            system: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
            arial: 'Arial, Helvetica, sans-serif',
            georgia: 'Georgia, "Times New Roman", serif',
            serif: 'Georgia, "Times New Roman", serif',
            sans: '"Helvetica Neue", Arial, sans-serif'
        };
        root.style.setProperty('--snw-font', fonts[d.typography] || 'inherit');
    }

    function buildItem(config, post, layout) {
        var show = config.show || {};
        var partner = config.partner || '';
        var widgetId = config.widget_id || '';

        var postUrl = trackedUrl(post.link, partner, widgetId);
        var titleText = stripHtml((post.title && post.title.rendered) || '');
        if (!titleText) { titleText = TEXTS.untitled; }

        if (layout === 'headlines') {
            var li = document.createElement('li');
            li.className = 'snw-item snw-item--headline';
            li.appendChild(makeLink(postUrl, titleText, 'snw-title-link'));
            if (show.date && post.date) {
                var t = document.createElement('time');
                t.className = 'snw-meta';
                t.dateTime = post.date;
                t.textContent = formatDate(post.date);
                li.appendChild(t);
            }
            return li;
        }

        var article = document.createElement('article');
        article.className = 'snw-item';

        var imageUrl = '';
        if (show.image) { imageUrl = featuredImageUrl(post); }

        if (imageUrl && layout !== 'compact' && layout !== 'headlines') {
            var link = makeLink(postUrl, '', 'snw-image-link');
            link.setAttribute('aria-label', titleText);
            var img = document.createElement('img');
            img.className = 'snw-image';
            img.src = imageUrl;
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            link.appendChild(img);
            article.appendChild(link);
        }

        var content = document.createElement('div');
        content.className = 'snw-content';

        if (show.date && post.date) {
            var meta = document.createElement('p');
            meta.className = 'snw-meta';
            var time = document.createElement('time');
            time.dateTime = post.date;
            time.textContent = formatDate(post.date);
            meta.appendChild(time);
            content.appendChild(meta);
        }

        if (show.category) {
            var cats = categoryNames(post);
            if (cats.length) {
                var catWrap = document.createElement('p');
                catWrap.className = 'snw-cat';
                cats.forEach(function (c, idx) {
                    if (idx > 0) { catWrap.appendChild(document.createTextNode(', ')); }
                    var base = (config.source_url || '').replace(/\/+$/, '');
                    var catLink = makeLink(base + '/?cat=' + encodeURIComponent(c.id), c.name, 'snw-cat-link');
                    catWrap.appendChild(catLink);
                });
                content.appendChild(catWrap);
            }
        }

        var h = document.createElement('h3');
        h.className = 'snw-title';
        h.appendChild(makeLink(postUrl, titleText));
        content.appendChild(h);

        if (show.excerpt && layout !== 'compact' && layout !== 'headlines') {
            var raw = stripHtml((post.excerpt && post.excerpt.rendered) || '');
            var excerptText = truncate(raw, parseInt(config.teaser, 10) || 180);
            if (excerptText) {
                var p = document.createElement('p');
                p.className = 'snw-excerpt';
                p.textContent = excerptText;
                content.appendChild(p);
            }
        }

        if (show.readmore && layout !== 'headlines') {
            var rm = makeLink(postUrl, TEXTS.readmore, 'snw-readmore');
            content.appendChild(rm);
        }

        article.appendChild(content);
        return article;
    }

    function build(config, el) {
        if (!config || !config.api) {
            if (typeof console !== 'undefined') {
                console.debug('[Steigerwald-News Widget] Ungültige oder unvollständige Konfiguration.', config);
            }
            el.replaceChildren();
            return;
        }

        // Allow per-widget text overrides for translation on third-party sites.
        if (config.texts && typeof config.texts === 'object') {
            for (var tk in config.texts) {
                if (Object.prototype.hasOwnProperty.call(config.texts, tk)) {
                    TEXTS[tk] = config.texts[tk];
                }
            }
        }

        // Abort any in-flight request for this element (rapid config changes).
        if (el.__snwController) {
            try { el.__snwController.abort(); } catch (e) { /* ignore */ }
        }
        var controller = ('AbortController' in window) ? new AbortController() : null;
        el.__snwController = controller;
        if (controller) {
            setTimeout(function () { try { controller.abort(); } catch (e) {} }, 9000);
        }

        var loading = document.createElement('p');
        loading.className = 'snw-state snw-loading';
        loading.textContent = TEXTS.loading;
        el.replaceChildren(loading);

        var key = buildCacheKey(config);
        var cached = cacheGet(key);

        var proceed = cached
            ? Promise.resolve(cached)
            : fetchPosts(config, controller).then(function (posts) {
                  cacheSet(key, posts);
                  return posts;
              });

        proceed.then(function (posts) {
            if (el.__snwController !== controller) { return; } // superseded
            renderWidget(config, el, Array.isArray(posts) ? posts : []);
        }).catch(function (err) {
            if (el.__snwController !== controller) { return; } // superseded
            if (typeof console !== 'undefined') {
                console.debug('[Steigerwald-News Widget] Ladefehler:', err && err.message);
            }
            // A timeout (AbortError on the still-current controller) and any
            // other failure both fall through to the configured error state
            // instead of leaving the loading message hanging forever.
            renderError(config, el);
        });
    }

    function renderWidget(config, el, posts) {
        var layout = config.layout || 'list';
        var show = config.show || {};

        // `data-layout` lives on the outer widget element so the layout
        // selectors in CSS target it correctly.
        el.setAttribute('data-layout', layout);
        el.replaceChildren();

        var root = document.createElement('section');
        root.className = 'snw-root';
        root.setAttribute('aria-label', config.title || config.source_name || 'Nachrichten');
        applyDesign(root, config);

        if (config.title) {
            var heading = document.createElement('h2');
            heading.className = 'snw-heading';
            heading.textContent = config.title;
            root.appendChild(heading);
        }

        if (!posts.length) {
            var empty = document.createElement('p');
            empty.className = 'snw-state snw-empty';
            empty.textContent = TEXTS.empty;
            root.appendChild(empty);
            el.appendChild(root);
            return;
        }

        if (layout === 'headlines') {
            var ul = document.createElement('ul');
            ul.className = 'snw-list snw-list--headlines';
            posts.forEach(function (post) { ul.appendChild(buildItem(config, post, layout)); });
            root.appendChild(ul);
        } else {
            var list = document.createElement('div');
            list.className = 'snw-list';
            posts.forEach(function (post) { list.appendChild(buildItem(config, post, layout)); });
            root.appendChild(list);
        }

        if (show.branding && config.source_name) {
            var branding = document.createElement('p');
            branding.className = 'snw-branding';
            branding.appendChild(document.createTextNode(TEXTS.branding + ' '));
            var base = (config.source_url || '').replace(/\/+$/, '');
            var srcLink = makeLink(trackedUrl(base || '#', config.partner, config.widget_id), config.source_name, 'snw-branding-link', 'noopener nofollow');
            branding.appendChild(srcLink);
            root.appendChild(branding);
        }

        el.appendChild(root);
    }

    function renderError(config, el) {
        if (config.on_error === 'hide') {
            el.replaceChildren();
            return;
        }
        el.setAttribute('data-layout', config.layout || 'list');
        el.replaceChildren();
        var root = document.createElement('section');
        root.className = 'snw-root';
        applyDesign(root, config);
        if (config.title) {
            var heading = document.createElement('h2');
            heading.className = 'snw-heading';
            heading.textContent = config.title;
            root.appendChild(heading);
        }
        var err = document.createElement('p');
        err.className = 'snw-state snw-error';
        err.textContent = TEXTS.error;
        root.appendChild(err);
        el.appendChild(root);
    }

    // ------------------------------------------------------------------
    // Style injection (only when the static stylesheet is absent)
    // ------------------------------------------------------------------
    var CSS = '';

    function ensureStyles() {
        if (typeof document === 'undefined') { return; }
        if (document.getElementById(STYLE_ID)) { return; }
        if (document.querySelector('link[href*="widget.css"]')) { return; }
        var style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = CSS;
        var head = document.head || document.getElementsByTagName('head')[0];
        if (head) { head.appendChild(style); }
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------
    function render(el, config) {
        if (!el) { return; }
        build(config, el);
    }

    function refresh(el) {
        if (!el) { return; }
        var cfg = decodeConfig(el.getAttribute('data-config'));
        if (!cfg) {
            if (typeof console !== 'undefined') {
                console.debug('[Steigerwald-News Widget] data-config konnte nicht decodiert werden.');
            }
            el.replaceChildren();
            return;
        }
        build(cfg, el);
    }

    function boot() {
        ensureStyles();
        var els = document.querySelectorAll('.steigerwald-news-widget');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (el.__snwBuilt) { continue; }
            el.__snwBuilt = true;
            var cfg = decodeConfig(el.getAttribute('data-config'));
            if (!cfg) {
                if (typeof console !== 'undefined') {
                    console.debug('[Steigerwald-News Widget] Ungültiges data-config an Element', el);
                }
                continue;
            }
            build(cfg, el);
        }
    }

    var api = {
        version: VERSION,
        boot: boot,
        render: render,
        refresh: refresh,
        decodeConfig: decodeConfig,
        encodeConfig: encodeConfig,
        truncate: truncate,
        trackedUrl: trackedUrl,
        featuredImageUrl: featuredImageUrl,
        categoryNames: categoryNames,
        buildCacheKey: buildCacheKey,
        formatDate: formatDate,
        stripHtml: stripHtml,
        TEXTS: TEXTS
    };

    // CSS is defined before boot so style injection has content.
    CSS = [
        '.steigerwald-news-widget{',
        '--snw-accent:#c59a20;--snw-bg:transparent;--snw-text:inherit;',
        '--snw-muted:color-mix(in srgb,currentColor 60%,transparent);',
        '--snw-border:rgba(0,0,0,.14);--snw-link:var(--snw-accent);',
        '--snw-radius:8px;--snw-gap:16px;--snw-pad:14px;--snw-font:inherit;',
        'box-sizing:border-box;color:var(--snw-text);font-family:var(--snw-font);',
        'width:100%;container-type:inline-size;line-height:1.5;}',
        '.steigerwald-news-widget *,.steigerwald-news-widget *::before,.steigerwald-news-widget *::after{box-sizing:border-box;}',
        '.steigerwald-news-widget .snw-root{background:var(--snw-bg);color:var(--snw-text);padding:var(--snw-pad);border-radius:var(--snw-radius);}',
        '.steigerwald-news-widget .snw-heading{margin:0 0 14px;padding:0 0 8px;border-bottom:2px solid var(--snw-accent);color:inherit;font:inherit;font-size:1.25em;font-weight:700;line-height:1.25;}',
        '.steigerwald-news-widget .snw-list{display:grid;gap:var(--snw-gap);}',
        '.steigerwald-news-widget .snw-list--headlines{list-style:none;margin:0;padding:0;}',
        '.steigerwald-news-widget .snw-item{border-bottom:1px solid var(--snw-border);padding-bottom:var(--snw-gap);}',
        '.steigerwald-news-widget .snw-list--headlines .snw-item{border:0;padding:0;margin:0 0 10px;}',
        '.steigerwald-news-widget .snw-list--headlines .snw-item::before{content:"•";margin-right:8px;color:var(--snw-accent);}',
        '.steigerwald-news-widget .snw-list--headlines .snw-item{display:flex;gap:8px;align-items:baseline;}',
        '.steigerwald-news-widget .snw-item:last-child{border-bottom:0;padding-bottom:0;}',
        '.steigerwald-news-widget[data-layout="list"] .snw-item{display:grid;grid-template-columns:minmax(96px,150px) 1fr;gap:14px;align-items:start;}',
        '.steigerwald-news-widget .snw-image-link{display:block;overflow:hidden;aspect-ratio:16/9;background:rgba(127,127,127,.08);border-radius:var(--snw-radius);}',
        '.steigerwald-news-widget .snw-image{display:block;width:100%;height:100%;object-fit:cover;}',
        '.steigerwald-news-widget .snw-content{min-width:0;}',
        '.steigerwald-news-widget .snw-meta{margin:0 0 5px;color:var(--snw-muted);font-size:.82em;}',
        '.steigerwald-news-widget .snw-cat{margin:0 0 4px;color:var(--snw-muted);font-size:.8em;text-transform:uppercase;letter-spacing:.03em;}',
        '.steigerwald-news-widget .snw-cat-link{color:var(--snw-muted);text-decoration:none;}',
        '.steigerwald-news-widget .snw-cat-link:hover{text-decoration:underline;}',
        '.steigerwald-news-widget .snw-title{margin:0;color:inherit;font:inherit;font-size:1.02em;font-weight:700;line-height:1.35;}',
        '.steigerwald-news-widget .snw-title a,.steigerwald-news-widget .snw-title-link{color:inherit;text-decoration:none;}',
        '.steigerwald-news-widget .snw-title a:hover,.steigerwald-news-widget .snw-title-link:hover{text-decoration:underline;text-underline-offset:3px;}',
        '.steigerwald-news-widget .snw-excerpt{margin:7px 0 0;line-height:1.5;font-size:.94em;color:inherit;}',
        '.steigerwald-news-widget .snw-readmore{display:inline-block;margin-top:8px;font-size:.9em;font-weight:700;color:var(--snw-link);text-decoration:none;}',
        '.steigerwald-news-widget .snw-readmore::after{content:" →";}',
        '.steigerwald-news-widget .snw-readmore:hover{text-decoration:underline;}',
        '.steigerwald-news-widget .snw-branding{margin:var(--snw-gap) 0 0;padding-top:10px;border-top:1px solid var(--snw-border);color:var(--snw-muted);font-size:.78em;}',
        '.steigerwald-news-widget .snw-branding-link{color:var(--snw-muted);text-decoration:none;}',
        '.steigerwald-news-widget .snw-branding-link:hover{text-decoration:underline;}',
        '.steigerwald-news-widget .snw-state{margin:0;padding:10px 0;color:var(--snw-muted);font-size:.92em;}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-list{grid-template-columns:repeat(2,minmax(0,1fr));}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-item{display:block;overflow:hidden;padding:0;border:1px solid var(--snw-border);border-radius:var(--snw-radius);background:var(--snw-bg);}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-image-link{border-radius:0;aspect-ratio:16/10;}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-content{padding:12px 13px 14px;}',
        '.steigerwald-news-widget[data-layout="compact"] .snw-image-link,.steigerwald-news-widget[data-layout="compact"] .snw-excerpt{display:none!important;}',
        '.steigerwald-news-widget[data-layout="compact"] .snw-item{display:block;}',
        '.steigerwald-news-widget .snw-item:focus-within,.steigerwald-news-widget a:focus-visible{outline:2px solid var(--snw-accent);outline-offset:2px;}',
        '@container (max-width:520px){',
        '.steigerwald-news-widget[data-layout="list"] .snw-item{grid-template-columns:96px 1fr;gap:11px;}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-list{grid-template-columns:1fr;}',
        '.steigerwald-news-widget .snw-excerpt{display:none;}',
        '}',
        '@container (max-width:380px){',
        '.steigerwald-news-widget[data-layout="list"] .snw-item{grid-template-columns:1fr;}',
        '.steigerwald-news-widget[data-layout="list"] .snw-image-link{aspect-ratio:16/7;}',
        '}',
        '@media (max-width:520px){',
        '.steigerwald-news-widget[data-layout="list"] .snw-item{grid-template-columns:96px 1fr;gap:11px;}',
        '.steigerwald-news-widget[data-layout="cards"] .snw-list{grid-template-columns:1fr;}',
        '}',
        '@media (prefers-color-scheme:dark){',
        '.steigerwald-news-widget{--snw-border:rgba(255,255,255,.18);}',
        '}'
    ].join('');

    if (typeof window !== 'undefined') {
        window.SteigerwaldNewsWidget = api;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { boot(); }, { once: true });
        } else {
            boot();
        }
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

})();
