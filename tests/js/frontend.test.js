/**
 * Node tests for the public intake form + domain matching.
 * Run: node tests/js/frontend.test.js
 */
'use strict';

var assert = require('assert');
var FE = require('../../public/js/frontend.js');
var W = require('../../public/js/widget.js');

var pass = 0;
function ok(name, cond) {
    if (cond) { pass++; console.log('  PASS ' + name); }
    else { console.error('  FAIL ' + name); process.exitCode = 1; }
}

console.log('SN News Widget — frontend + domain tests');

// Fake form implementing just enough of the DOM for buildConfigFromForm.
function fakeForm(values, checks) {
    return {
        querySelector: function (sel) {
            var id = sel.replace('#', '');
            if (id in values) {
                return { value: values[id] };
            }
            if (id in checks) {
                return { checked: !!checks[id] };
            }
            return null;
        }
    };
}

var form = fakeForm(
    {
        'snw-mode': 'category',
        'snw-category': '42',
        'snw-layout': 'cards',
        'snw-color-accent': '#123456',
        'snw-limit': '7',
        'snw-title': 'Mein Widget'
    },
    { 'snw-show-image': true, 'snw-show-date': false, 'snw-show-excerpt': true }
);

var cfg = FE.buildConfigFromForm(form);
ok('mode from form', cfg.mode === 'category');
ok('category captured', Array.isArray(cfg.category) && cfg.category[0] === 42);
ok('layout from form', cfg.layout === 'cards');
ok('accent from form', cfg.design.accent === '#123456');
ok('limit from form', cfg.limit === 7);
ok('title from form', cfg.title === 'Mein Widget');
ok('show.image true', cfg.show.image === true);
ok('show.date false', cfg.show.date === false);
ok('show.excerpt true', cfg.show.excerpt === true);

// mode "latest" must not capture a category.
var form2 = fakeForm({ 'snw-mode': 'latest', 'snw-limit': '99' }, {});
var cfg2 = FE.buildConfigFromForm(form2);
ok('latest drops category', Array.isArray(cfg2.category) && cfg2.category.length === 0);
ok('limit clamped to 20', cfg2.limit === 20);

// --- domainMatches (mirrors server-side SNW_Helpers::domain_allowed) ---
ok('domainMatches exact', W.domainMatches('example.com', 'example.com') === true);
ok('domainMatches subdomain', W.domainMatches('example.com', 'news.example.com') === true);
ok('domainMatches other', W.domainMatches('example.com', 'evil.com') === false);
ok('domainMatches sibling', W.domainMatches('example.com', 'notexample.com') === false);
ok('domainMatches strips port', W.domainMatches('example.com:8080', 'example.com:8080') === true);
ok('domainMatches empty', W.domainMatches('', 'example.com') === false);

console.log('\n' + pass + ' assertions passed.');
