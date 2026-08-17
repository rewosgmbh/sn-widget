/**
 * DOM-shim render tests for the public widget renderer.
 * A minimal fake DOM + mocked fetch exercise the real render pipeline so we
 * can validate empty states, missing images, HTML/excerpt handling, unicode,
 * layout selection and error behaviour without a browser.
 *
 * Run: node tests/js/render.test.js
 */
'use strict';

// --- Minimal fake DOM -------------------------------------------------
function FakeNode(tag) {
    this.tagName = tag;
    this.className = '';
    this.children = [];
    this.attributes = {};
    this._props = {};
    var self = this;
    this.style = { setProperty: function (k, v) { self._props[k] = v; } };
    this.dataset = {};
    this._text = '';
    this._html = '';
    this.href = '';
    this.rel = '';
    this.src = '';
    this.alt = '';
    this.loading = '';
    this.decoding = '';
    this.dateTime = '';
}
Object.defineProperty(FakeNode.prototype, 'textContent', {
    get: function () { return this._text; },
    set: function (v) { this._text = String(v); }
});
function stripToText(html) {
    return String(html)
        .replace(/<[^>]*>/g, '')
        .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"').replace(/&#0?39;/g, "'")
        .replace(/&#(\d+);/g, function (_, n) { return String.fromCharCode(parseInt(n, 10)); });
}
Object.defineProperty(FakeNode.prototype, 'innerHTML', {
    get: function () { return this._html; },
    set: function (v) {
        this._html = String(v);
        if (v === '') { this.children = []; this._text = ''; }
        else { this._text = stripToText(v); }
    }
});
FakeNode.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
FakeNode.prototype.getAttribute = function (k) { return (k in this.attributes) ? this.attributes[k] : null; };
FakeNode.prototype.appendChild = function (c) { this.children.push(c); return c; };
FakeNode.prototype.replaceChildren = function () {
    var args = Array.prototype.slice.call(arguments);
    this.children = [];
    for (var i = 0; i < args.length; i++) { this.children.push(args[i]); }
};
FakeNode.prototype.addEventListener = function () {};

function createDocument() {
    return {
        createElement: function (t) { return new FakeNode(t); },
        createTextNode: function (t) { var n = new FakeNode('#text'); n.textContent = t; return n; },
        getElementById: function () { return null; },
        querySelector: function () { return null; },
        querySelectorAll: function () { return []; },
        head: new FakeNode('head')
    };
}

global.document = createDocument();
global.window = global; // provides AbortController, setTimeout, Intl, fetch

function walk(node, pred, out) {
    if (!node) { return; }
    if (pred(node)) { out.push(node); }
    (node.children || []).forEach(function (c) { walk(c, pred, out); });
}
function findByClass(root, cls) {
    var out = [];
    walk(root, function (n) { return (' ' + n.className + ' ').indexOf(' ' + cls + ' ') !== -1; }, out);
    return out;
}
function textOf(root) {
    var t = [];
    walk(root, function (n) { if (n._text) { t.push(n._text); } }, t);
    return t.join(' ');
}

// --- Load module (boot skipped because window undefined at load is false;
//      we set window=global AFTER require so boot would run, but querySelectorAll
//      returns [] so it is a no-op). ---
var W = require('../../public/js/widget.js');

// --- Mock fetch (capture posts per call to avoid cross-test bleed) ----
function setMock(posts) {
    global.fetch = function () {
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve(posts); } });
    };
}
setMock([]);
function setFetchError() {
    global.fetch = function () { return Promise.reject(new Error('network down')); };
}

var pass = 0, fail = 0;
function ok(name, cond) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { fail++; console.log('  FAIL ' + name); }
}

function makeEl() { return new FakeNode('div'); }
var __apiCounter = 0;
function baseConfig(over) {
    var cfg = {
        v: 1, api: 'https://t' + (++__apiCounter) + '/wp/v2', source_name: 'Test', source_url: 'https://x/',
        widget_id: 'SNW-12345', partner: 'asv', title: '', mode: 'latest',
        category: [], tags: [], include: [], exclude: [], pinned: [],
        auto_count: 3, limit: 5, sort: 'newest', layout: 'list',
        show: { image: true, date: true, category: false, excerpt: true, readmore: true, branding: true },
        teaser: 180,
        design: { accent: '#c59a20', background: '', text: '', muted: '', border: '', link: '', radius: 8, spacing: 'normal', typography: 'host' },
        on_error: 'message'
    };
    if (over) { for (var k in over) { cfg[k] = over[k]; } }
    return cfg;
}

function renderSync(el, cfg) {
    W.render(el, cfg);
    return new Promise(function (res) { setTimeout(res, 30); });
}

console.log('Steigerwald-News Widget — render tests');

(function () {
    setMock([]);
    var el = makeEl();
    return renderSync(el, baseConfig({ title: '' })).then(function () {
        ok('0 posts -> empty state', findByClass(el, 'snw-empty').length === 1);
        ok('0 posts -> no list', findByClass(el, 'snw-list').length === 0);
    });
})();

(function () {
    setMock([{
        id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1',
        title: { rendered: 'Über Grüße & <b>fett</b> 🚀' },
        excerpt: { rendered: '<p>Excerpt mit <a href="#">Link</a> &amp; Entities – sehr langer Text der über die Länge hinausgehen soll damit ein Truncation stattfindet beim Rendern des Widgets auf der Seite.</p>' },
        categories: [1], tags: [2],
        _embedded: {}
    }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ title: '', show: { image: true, date: true, category: false, excerpt: true, readmore: true, branding: true } })).then(function () {
        var titleNodes = findByClass(el, 'snw-title');
        ok('1 post -> title rendered', titleNodes.length === 1);
        var txt = textOf(titleNodes[0]);
        ok('HTML stripped from title', txt.indexOf('<b>') === -1 && txt.indexOf('Über Grüße') !== -1);
        var imgs = findByClass(el, 'snw-image');
        ok('missing image -> no img element', imgs.length === 0);
        var exC = findByClass(el, 'snw-excerpt');
        ok('excerpt rendered', exC.length === 1);
        ok('excerpt HTML stripped', exC.length && exC[0]._text.indexOf('<a') === -1);
        ok('excerpt truncated <= ~200', exC.length && exC[0]._text.length <= 200);
        ok('excerpt no broken entity', exC.length && exC[0]._text.indexOf('&amp;') === -1);
        var rm = findByClass(el, 'snw-readmore');
        ok('readmore present', rm.length === 1);
        ok('readmore links to post', rm.length && rm[0].href === 'https://x/a/1?utm_source=asv&utm_medium=referral&utm_campaign=steigerwald_news_widget&utm_content=SNW-12345');
    });
})();

(function () {
    setMock([{
        id: 2, date: '2026-08-10T10:00:00', link: 'https://x/a/2',
        title: { rendered: 'Mit Bild' }, excerpt: { rendered: 'Teaser' },
        featured_media: 9,
        _embedded: { 'wp:featuredmedia': [ { media_details: { sizes: { medium_large: { source_url: 'https://x/m.jpg' } } } }] }
    }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ show: { image: true, date: true, category: false, excerpt: true, readmore: true, branding: true } })).then(function () {
        var imgs = findByClass(el, 'snw-image');
        ok('image present when show.image', imgs.length === 1);
        ok('img src from medium_large', imgs.length && imgs[0].src === 'https://x/m.jpg');
        ok('img lazy+async', imgs.length && imgs[0].loading === 'lazy' && imgs[0].decoding === 'async');
    });
})();

(function () {
    setMock([{ id: 3, date: '2026-08-01T10:00:00', link: 'https://x/a/3', title: { rendered: 'Head' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ layout: 'headlines' })).then(function () {
        var lists = findByClass(el, 'snw-list--headlines');
        ok('headlines uses ul list', lists.length === 1);
        var items = findByClass(el, 'snw-item--headline');
        ok('headlines item present', items.length === 1);
    });
})();

(function () {
    setMock([{ id: 4, date: '2026-08-01T10:00:00', link: 'https://x/a/4', title: { rendered: 'Cat' }, excerpt: { rendered: 'x' }, categories: [5], _embedded: { 'wp:term': [ [ { taxonomy: 'category', id: 5, name: 'Sport' } ] ] } }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ show: { image: true, date: true, category: true, excerpt: true, readmore: true, branding: true } })).then(function () {
        var cat = findByClass(el, 'snw-cat');
        ok('category label shown', cat.length === 1);
        ok('category name rendered', cat.length && textOf(cat[0]).indexOf('Sport') !== -1);
    });
})();

(function () {
    setFetchError();
    var el = makeEl();
    return renderSync(el, baseConfig({ on_error: 'hide' })).then(function () {
        ok('on_error=hide clears element', el.children.length === 0);
    });
})();

(function () {
    var el = makeEl();
    W.render(el, { some: 'garbage', api: '' });
    return new Promise(function (res) { setTimeout(res, 20); }).then(function () {
        ok('invalid config (no api) does not throw + clears', el.children.length === 0);
    });
})();

// --- Regression: data-layout must live on the OUTER widget element (P0) ---
(function () {
    setMock([]);
    var el = makeEl();
    return renderSync(el, baseConfig({ layout: 'cards' })).then(function () {
        ok('data-layout set on outer widget element', el.getAttribute('data-layout') === 'cards');
        var root = findByClass(el, 'snw-root')[0];
        ok('inner root has no competing data-layout', !!root && root.getAttribute('data-layout') === null);
    });
})();

// --- Regression: REST embeds BOTH media + terms and includes _links (P0) ---
(function () {
    var captured = null;
    global.fetch = function (url) {
        captured = url;
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve([]); } });
    };
    var el = makeEl();
    return renderSync(el, baseConfig({
        mode: 'category', category: [5],
        show: { image: true, date: true, category: true, excerpt: true, readmore: true, branding: true }
    })).then(function () {
        var u = decodeURIComponent(captured);
        ok('REST url includes _links in _fields', u.indexOf('_links') !== -1);
        ok('REST url embeds both wp:term and wp:featuredmedia', u.indexOf('_embed=') !== -1 && u.indexOf('wp:featuredmedia') !== -1 && u.indexOf('wp:term') !== -1);
        ok('category mode filters by categories', u.indexOf('categories=5') !== -1);
    });
})();

// --- Regression: hidden taxonomy values must not leak across modes (P0) ---
(function () {
    var captured = null;
    global.fetch = function (url) {
        captured = url;
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve([]); } });
    };
    var el = makeEl();
    return renderSync(el, baseConfig({ mode: 'latest', category: [5], tags: [7] })).then(function () {
        var u = decodeURIComponent(captured);
        ok('latest mode ignores category filter', u.indexOf('categories=') === -1);
        ok('latest mode ignores tags filter', u.indexOf('tags=') === -1);
    });
})();

// --- Regression: article links open in a new browser tab ---
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'Titel' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({})).then(function () {
        var anchors = [];
        walk(el, function (n) { return n.tagName === 'a'; }, anchors);
        ok('links open in new tab (target=_blank)', anchors.length > 0 && anchors.every(function (a) { return a.target === '_blank'; }));
    });
})();

// --- New customization: design vars + data attributes ------------------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    var captured = {};
    el.style = { setProperty: function (k, v) { captured[k] = v; } };
    return renderSync(el, baseConfig({
        layout: 'cards', design: { columns: 3, image_ratio: '4:3', image_fit: 'contain', image_position: 'right', theme: 'dark', shadow: 'md', align: 'center', link_mode: 'card' }
    })).then(function () {
        var root = findByClass(el, 'snw-root')[0] || el;
        ok('columns var = 3', root._props['--snw-columns'] === '3');
        ok('image ratio var = 4 / 3', root._props['--snw-img-ratio'] === '4 / 3');
        ok('image fit var = contain', root._props['--snw-img-fit'] === 'contain');
        ok('shadow var = md', root._props['--snw-shadow'] === '0 2px 8px rgba(0,0,0,.14)');
        ok('align var = center', root._props['--snw-align'] === 'center');
        ok('data-theme dark on outer', el.getAttribute('data-theme') === 'dark');
        ok('data-img-pos right on outer', el.getAttribute('data-img-pos') === 'right');
        ok('data-link-mode card on outer', el.getAttribute('data-link-mode') === 'card');
    });
})();

// --- New customization: author + relative date -------------------------
(function () {
    setMock([{
        id: 1, date: '2020-01-01T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' },
        _embedded: { author: [ { name: 'Max Mustermann' } ] }
    }]);
    var el = makeEl();
    return renderSync(el, baseConfig({
        show: { image: true, date: true, category: false, excerpt: true, readmore: true, branding: true, author: true },
        design: { date_format: 'relative' }
    })).then(function () {
        var auth = findByClass(el, 'snw-author');
        ok('author meta rendered', auth.length === 1);
        ok('author name shown', auth.length && textOf(auth[0]).indexOf('Max Mustermann') !== -1);
        var times = [];
        walk(el, function (n) { return n.tagName === 'time'; }, times);
        ok('relative date uses "vor"', times.length && /vor /.test(times[0]._text));
    });
})();

// --- New customization: heading level ----------------------------------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ design: { heading_level: 'h2' } })).then(function () {
        var t = findByClass(el, 'snw-title');
        ok('item title uses H2', t.length === 1 && t[0].tagName === 'h2');
    });
})();

// --- New customization: title truncation ------------------------------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'Ein sehr langer Beitragstitel der gekürzt werden soll' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ design: { title_length: 10 } })).then(function () {
        var t = findByClass(el, 'snw-title');
        var txt = textOf(t[0]);
        ok('title truncated', t.length && txt.length <= 12);
        ok('title truncation adds ellipsis', t.length && txt.indexOf('…') !== -1);
    });
})();

// --- New customization: custom readmore / empty / error labels --------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ readmore_label: 'Mehr erfahren' })).then(function () {
        var rm = findByClass(el, 'snw-readmore');
        ok('custom readmore label', rm.length === 1 && rm[0]._text === 'Mehr erfahren');
    });
})();

(function () {
    setMock([]);
    var el = makeEl();
    return renderSync(el, baseConfig({ empty_label: 'Nichts da.' })).then(function () {
        var e = findByClass(el, 'snw-empty');
        ok('custom empty label', e.length === 1 && e[0]._text === 'Nichts da.');
    });
})();

(function () {
    setFetchError();
    var el = makeEl();
    return renderSync(el, baseConfig({ on_error: 'message', error_label: 'Oops.' })).then(function () {
        var e = findByClass(el, 'snw-error');
        ok('custom error label', e.length === 1 && e[0]._text === 'Oops.');
    });
})();

// --- New customization: scoped custom CSS injection --------------------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ design: { custom_css: '.snw-title { color: red; }' } })).then(function () {
        var styles = [];
        walk(el, function (n) { return n.tagName === 'style'; }, styles);
        var custom = styles.filter(function (s) { return s.getAttribute('data-snw-custom') === '1'; });
        ok('custom css injected', custom.length === 1);
        var uid = el.getAttribute('data-snw-uid');
        ok('custom css is scoped to widget uid', custom.length && custom[0]._text.indexOf('[data-snw-uid="' + uid + '"]') !== -1);
        ok('custom css cannot break out', custom.length && custom[0]._text.indexOf('<') === -1);
    });
})();

// --- New customization: card link mode mark-up -------------------------
(function () {
    setMock([{ id: 1, date: '2026-08-17T10:00:00', link: 'https://x/a/1', title: { rendered: 'T' }, excerpt: { rendered: 'x' }, _embedded: {} }]);
    var el = makeEl();
    return renderSync(el, baseConfig({ design: { link_mode: 'card' } })).then(function () {
        var links = [];
        walk(el, function (n) { return n.tagName === 'a' && (' ' + n.className + ' ').indexOf(' snw-title-link ') !== -1; }, links);
        ok('card mode keeps title link', links.length === 1);
    });
})();

setTimeout(function () {
    console.log('\n' + pass + ' assertions passed, ' + fail + ' failed.');
    process.exit(fail > 0 ? 1 : 0);
}, 400);
