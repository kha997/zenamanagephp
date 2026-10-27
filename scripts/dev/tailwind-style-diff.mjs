#!/usr/bin/env node
// GAP-085: compares two builds of a Tailwind stylesheet by what a browser computes.
//
// Usage: node scripts/dev/tailwind-style-diff.mjs <old.css> <new.css>
//
// Each static class combination found in class="..." attributes under resources/views
// and app/ is rendered as one element, together with a set of bare HTML elements. The
// script renders the elements with each stylesheet in Chromium (Playwright) and compares
// every computed property, plus element size and offset. It runs twice: once at rest,
// once with :hover/:focus/:focus-visible/:focus-within/:active rules forced on.
// Differences known to look the same are discounted (rules in isEquivalent()); anything
// else is printed and the exit code is 1.
//
// Set PLAYWRIGHT_CHROMIUM to a Chromium binary if Playwright's own browser is missing.

import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';

const require = createRequire(path.join(process.cwd(), 'package.json'));
const { chromium } = require('playwright');

const [oldPath, newPath] = process.argv.slice(2);
if (!oldPath || !newPath) {
    console.error('Usage: node scripts/dev/tailwind-style-diff.mjs <old.css> <new.css>');
    process.exit(2);
}

const STATE = /:(hover|focus-visible|focus-within|focus|active)(?![\w-])/g;
const UNRENDERABLE = /(^|:)(group-hover|group|peer-|peer|placeholder|file|first|last|odd|even|dark|before|after|checked|visited|disabled):/;
const TAGS = ['div', 'button', 'input', 'select', 'textarea', 'a', 'h1', 'h2', 'p', 'ul', 'ol', 'li', 'img', 'table',
    'hr', 'label', 'svg', 'code', 'small', 'b', 'blockquote', 'details', 'summary'];

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, out);
        else if (entry.name.endsWith('.php')) out.push(full);
    }
    return out;
}

function classCombinations() {
    const combos = new Set();
    for (const file of [...walk('resources/views'), ...walk('app')]) {
        const source = fs.readFileSync(file, 'utf8');
        for (const match of source.matchAll(/class\s*=\s*"([^"]*)"/g)) {
            const value = match[1].replace(/\{\{.*?\}\}|\{!!.*?!!\}|@\w+(\([^)]*\))?/g, '');
            const tokens = value.split(/\s+/).filter(t => /^[A-Za-z0-9_:/.\[\]%#-]+$/.test(t) && !UNRENDERABLE.test(t));
            if (tokens.length) combos.add(tokens.join(' '));
        }
    }
    return [...combos].sort();
}

function page(css, combos) {
    const escape = s => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    const voids = ['input', 'img', 'hr'];
    const tags = TAGS.map((t, i) => voids.includes(t)
        ? `<${t} id="t${i}"${t === 'img' ? ' src="data:,"' : ''}>`
        : `<${t} id="t${i}">x</${t}>`).join('\n');
    const items = combos.map((c, i) => `<div class="wrap"><div id="c${i}" class="${escape(c)}">x</div></div>`).join('\n');
    return `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body>${tags}\n${items}</body></html>`;
}

async function snapshot(browser, css, combos) {
    const tab = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await tab.setContent(page(css, combos));
    const result = await tab.evaluate(({ comboCount, tagCount }) => {
        const read = el => {
            if (!el) return {};
            const style = getComputedStyle(el);
            const out = {};
            for (const prop of style) if (!prop.startsWith('--')) out[prop] = style.getPropertyValue(prop);
            const r = el.getBoundingClientRect();
            out['@size'] = `${Math.round(r.width)}x${Math.round(r.height)}`;
            if (el.parentElement && el.parentElement.className === 'wrap') {
                const p = el.parentElement.getBoundingClientRect();
                out['@offset'] = `${Math.round(r.left - p.left)},${Math.round(r.top - p.top)}`;
            }
            return out;
        };
        const res = {};
        for (let i = 0; i < tagCount; i++) res[`t${i}`] = read(document.getElementById(`t${i}`));
        for (let i = 0; i < comboCount; i++) res[`c${i}`] = read(document.getElementById(`c${i}`));
        return res;
    }, { comboCount: combos.length, tagCount: TAGS.length });
    await tab.close();
    return result;
}

// Parses rgb()/rgba()/oklab()/oklch() into sRGB 0-255 plus alpha; null for anything else.
function toRgba(value) {
    let m = value.match(/^rgba?\(([\d.]+), ([\d.]+), ([\d.]+)(?:, ([\d.]+))?\)$/);
    if (m) return [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]];
    m = value.match(/^okl(ab|ch)\(([-\d.e]+%?) ([-\d.e]+) ([-\d.e]+)(?: \/ ([\d.]+))?\)$/);
    if (!m) return null;
    const L = m[2].endsWith('%') ? parseFloat(m[2]) / 100 : +m[2];
    let A = +m[3];
    let B = +m[4];
    if (m[1] === 'ch') [A, B] = [A * Math.cos(B * Math.PI / 180), A * Math.sin(B * Math.PI / 180)];
    const l = (L + 0.3963377774 * A + 0.2158037573 * B) ** 3;
    const mm = (L - 0.1055613458 * A - 0.0638541728 * B) ** 3;
    const s = (L - 0.0894841775 * A - 1.2914855480 * B) ** 3;
    const lin = [4.0767416621 * l - 3.3077115913 * mm + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * mm - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * mm + 1.7076147010 * s];
    const srgb = lin.map(c => 255 * (c <= 0.0031308 ? 12.92 * c : 1.055 * Math.max(c, 0) ** (1 / 2.4) - 0.055));
    return [...srgb, m[5] === undefined ? 1 : +m[5]];
}

const sameColour = (a, b) => {
    const x = toRgba(a);
    const y = toRgba(b);
    return Boolean(x && y) && x.slice(0, 3).every((v, i) => Math.abs(v - y[i]) <= 1) && Math.abs(x[3] - y[3]) <= 0.01;
};

const shadowLayers = v => v.replace(/(rgba\(0, 0, 0, 0\)|rgb\(255, 255, 255\)) 0px 0px 0px 0px( inset)?(, )?/g, '').replace(/,\s*$/, '');

function isEquivalent(name, prop, a, b) {
    // v4 uses the individual translate/rotate/scale properties; geometry is checked via @size/@offset.
    if (['transform', 'translate', 'rotate', 'scale'].includes(prop)) return true;
    // v4 transitions a few more properties; the at-rest and forced-state results are compared directly.
    if (prop === 'transition-property') return true;
    // v4 adds fully transparent shadow layers and zero-spread ring offsets.
    if (prop === 'box-shadow' && shadowLayers(a) === shadowLayers(b)) return true;
    // Both values are "fully round".
    if (prop.includes('radius') && parseFloat(a) >= 9999 && parseFloat(b) >= 9999) return true;
    // Same colour written in another colour space (v4's color-mix output for /opacity modifiers).
    if (prop.includes('color') && sameColour(a, b)) return true;
    // v4 writes explicit 0%/100% stops.
    if (prop === 'background-image' && b.replace(/ 0%| 100%| in oklab/g, '') === a) return true;
    // sr-only hides with clip-path instead of clip.
    if ((prop === 'clip' || prop === 'clip-path') && name.split(' ').includes('sr-only')) return true;
    return false;
}

function compare(oldSnap, newSnap, combos) {
    const unexplained = [];
    let equivalent = 0;
    for (const key of Object.keys(oldSnap)) {
        const name = key.startsWith('t') ? `<${TAGS[+key.slice(1)]}>` : combos[+key.slice(1)];
        const left = oldSnap[key];
        const right = newSnap[key];
        const props = Object.keys(left).filter(p => left[p] !== right[p]);
        if (!props.length) continue;
        const real = props.filter(p => !isEquivalent(name, p, left[p], right[p]));
        // Offsets only shift when an element above changed height; those are reported on that element.
        const own = real.filter(p => !['@offset', 'top', 'bottom', 'inset-block-start', 'inset-block-end'].includes(p));
        if (own.length) unexplained.push({ name, diff: Object.fromEntries(own.map(p => [p, [left[p], right[p]]])) });
        else equivalent++;
    }
    return { unexplained, equivalent };
}

const oldCss = fs.readFileSync(oldPath, 'utf8');
const newCss = fs.readFileSync(newPath, 'utf8');
const forceStates = css => css.replace(STATE, '').replaceAll('@media (hover:hover)', '@media all');
const combos = classCombinations();

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});
let failed = false;
for (const [label, oldText, newText] of [['at rest', oldCss, newCss], ['forced states', forceStates(oldCss), forceStates(newCss)]]) {
    const oldSnap = await snapshot(browser, oldText, combos);
    const newSnap = await snapshot(browser, newText, combos);
    const { unexplained, equivalent } = compare(oldSnap, newSnap, combos);
    console.log(`${label}: ${combos.length} class combinations + ${TAGS.length} elements; `
        + `${equivalent} differ only in equivalent ways; ${unexplained.length} unexplained`);
    for (const { name, diff } of unexplained) console.log(`  ${name}\n    ${JSON.stringify(diff)}`);
    failed ||= unexplained.length > 0;
}
await browser.close();
process.exit(failed ? 1 : 0);
