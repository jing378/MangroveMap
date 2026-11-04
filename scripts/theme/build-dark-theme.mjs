#!/usr/bin/env node
/**
 * Dark-mode palette builder.
 *
 * The views style themselves with hardcoded colors in <style> blocks and style="" attributes.
 * This script makes those colors themeable without changing the light design:
 *
 *   1. Every literal color in a CSS declaration becomes a variable whose fallback is the
 *      original value, e.g.  `border: 1px solid #e0e8e0`  →  `border: 1px solid var(--bd-e0e8e0, #e0e8e0)`.
 *      The prefix is the color's role, taken from the property:
 *        fg = text (color, fill, stroke, …), bd = borders/outlines/shadows, bg = everything else.
 *      Light rgba() colors become  `rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.9)`; translucent ones
 *      (alpha < 0.5, i.e. glass/overlay effects) are left alone.
 *   2. It collects every variable used in the views and writes their dark values to
 *      resources/views/partials/theme-palette.blade.php (generated; do not edit by hand).
 *
 * Light mode never defines these variables, so it keeps using the fallbacks, i.e. the exact old colors.
 * The script is idempotent: run it again after adding styles with new hardcoded colors.
 *
 *   node scripts/theme/build-dark-theme.mjs           # rewrite views + regenerate the palette
 *   node scripts/theme/build-dark-theme.mjs --check   # only report files that still need rewriting
 *
 * Only CSS is touched: <script> blocks (map/polygon colors), @php blocks and Blade echoes are left alone,
 * as are CSS custom-property definitions (--foo: #123), which pages theme by hand.
 * Hand-tuned dark values go in OVERRIDES below.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const viewsDir = path.join(root, 'resources', 'views');
const paletteFile = path.join(viewsDir, 'partials', 'theme-palette.blade.php');
const checkOnly = process.argv.includes('--check');

// Views that must not be rewritten.
const EXCLUDE = [
    'partials/theme.blade.php',
    'partials/theme-palette.blade.php',
    'partials/theme-toggle.blade.php',
    'users/end-user.blade.php', // unused legacy page
];

// Hand-tuned dark values ("role-hex" → hex). Everything else is computed by darkColor().
const OVERRIDES = {
    'bg-ffffff': '#141d18', // cards, sidebar, header
    'bg-f5f7f6': '#1b2620', // hover / page tint
    'bg-edf7f2': '#173326', // green tint (selected, badges)
    'bg-f8fcfa': '#16231c',
    'bd-e0e8e0': '#2a3a31', // the default border
    'fg-1a2e1a': '#e3ece6', // main text
    'fg-ffffff': '#ffffff',
};

/* ---------------- color math ---------------- */

function parseHex(hex) {
    let h = hex.replace('#', '').toLowerCase();
    if (h.length === 3) h = h.split('').map(c => c + c).join('');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

const toHex = rgb => '#' + rgb.map(v => Math.round(Math.min(255, Math.max(0, v))).toString(16).padStart(2, '0')).join('');

function rgbToHsl([r, g, b]) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    const l = (max + min) / 2;
    if (max === min) return [0, 0, l];
    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h;
    if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
    return [h * 60, s, l];
}

function hslToRgb([h, s, l]) {
    if (s === 0) return [l * 255, l * 255, l * 255];
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    const hue = t => {
        t = ((t % 1) + 1) % 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
    };
    const k = h / 360;
    return [hue(k + 1 / 3) * 255, hue(k) * 255, hue(k - 1 / 3) * 255];
}

/**
 * Light surfaces/borders turn dark (keeping their hue as a tint), dark text turns light,
 * and saturated mid-tone accents (buttons, badges, light text on them) stay as they are.
 */
function darkColor(role, rgb) {
    let [h, s, l] = rgbToHsl(rgb);
    // Neutral greys get the app's green tint so they sit with the rest of the palette.
    if (s < 0.06) { h = 150; s = 0.12; }

    if (role === 'fg') {
        if (l >= 0.6) return rgb; // already light (text on colored buttons, faint icons)
        return hslToRgb([h, Math.min(s, 0.6), Math.max(l, 1 - l * 0.85)]);
    }
    if (l < 0.7) return rgb; // accents and dark surfaces keep their color
    if (role === 'bd') return hslToRgb([h, Math.min(s, 0.3), 0.17 + (1 - l) * 0.5]);
    return hslToRgb([h, Math.min(s, 0.45), 0.075 + (1 - l) * 0.6]);
}

/* ---------------- CSS rewriting ---------------- */

function roleFor(prop) {
    prop = prop.toLowerCase();
    if (/^(color|caret-color|fill|stroke|-webkit-text-fill-color|text-decoration-color|text-emphasis-color)$/.test(prop)) return 'fg';
    if (/^(border|outline|column-rule|box-shadow)/.test(prop)) return 'bd';
    return 'bg';
}

const used = new Map(); // var name → dark value ("#hex" or "r, g, b")

function register(role, rgb, isTriple) {
    const hex = toHex(rgb).slice(1);
    const key = `${role}-${hex}`;
    const name = `--${key}${isTriple ? '-rgb' : ''}`;
    const dark = OVERRIDES[key] ? parseHex(OVERRIDES[key]) : darkColor(role, rgb);
    const changed = toHex(dark) !== toHex(rgb);
    if (changed) used.set(name, isTriple ? dark.map(Math.round).join(', ') : toHex(dark));
    return { name, changed };
}

// Replace literal colors inside one declaration value.
function rewriteValue(role, value) {
    // Translucent colors (alpha < 0.5) are glass/overlay effects, e.g. a white chip over a photo:
    // they stay as they are (and are restored if an earlier run themed them).
    value = value.replace(/rgba\(var\(--(?:bg|fg|bd)-[0-9a-f]{6}-rgb,\s*(\d+),\s*(\d+),\s*(\d+)\),\s*([\d.]+)\)/g,
        (m, r, g, b, a) => (parseFloat(a) < 0.5 ? `rgba(${r}, ${g}, ${b}, ${a})` : m));

    // Protect colors that are already themed (var fallbacks) from being processed twice.
    const kept = [];
    value = value.replace(/var\(--(bg|fg|bd)-([0-9a-f]{6})(-rgb)?,[^)]*\)/g, (m, r, hex, triple) => {
        register(r, parseHex(hex), Boolean(triple)); // keep it in the regenerated palette
        kept.push(m);
        return `\u0000${kept.length - 1}\u0000`;
    });

    value = value.replace(/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b(?![0-9a-fA-F])/g, (m) => {
        const { name, changed } = register(role, parseHex(m), false);
        return changed ? `var(${name}, ${m})` : m;
    });
    value = value.replace(/\bwhite\b(?![-\w])/gi, (m) => {
        const { name, changed } = register(role, [255, 255, 255], false);
        return changed ? `var(${name}, ${m})` : m;
    });
    value = value.replace(/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(,\s*[\d.]+%?\s*)?\)/g, (m, r, g, b, a) => {
        if (a && !a.includes('%') && parseFloat(a.replace(',', '')) < 0.5) return m;
        const rgb = [+r, +g, +b];
        const { name, changed } = register(role, rgb, true);
        if (!changed) return m;
        return `rgba(var(${name}, ${r}, ${g}, ${b})${a ? a.trimEnd() : ', 1'})`;
    });

    return value.replace(/\u0000(\d+)\u0000/g, (_, i) => kept[+i]);
}

// Rewrites `prop: value` declarations. A declaration must end in ; or } (or the end of a
// style attribute), so selectors such as `a:hover {` or `@media (max-width: 768px) {` are skipped.
function rewriteCss(css, isAttribute = false) {
    const end = isAttribute ? '(?=;|$)' : '(?=[;}])';
    const re = new RegExp(`(^|[;{\\s])(-{0,2}[a-zA-Z][\\w-]*)(\\s*:\\s*)([^;{}]+?)\\s*${end}`, 'g');
    return css.replace(re, (m, lead, prop, colon, value) => {
        if (prop.startsWith('--')) return m; // custom properties are themed by hand
        if (/\{\{|\{!!|@/.test(value)) return m; // Blade inside the value
        const next = rewriteValue(roleFor(prop), value);
        return next === value ? m : `${lead}${prop}${colon}${next}`;
    });
}

function rewriteView(src) {
    // Split out regions we must not touch: <script>…</script> and @php … @endphp.
    const parts = src.split(/(<script\b[\s\S]*?<\/script>|@php\b[\s\S]*?@endphp)/i);
    return parts.map((part, i) => {
        if (i % 2 === 1) return part;
        part = part.replace(/(<style\b[^>]*>)([\s\S]*?)(<\/style>)/gi, (m, open, css, close) => open + rewriteCss(css) + close);
        part = part.replace(/(\sstyle=")([^"]*)(")/g, (m, open, css, close) => open + rewriteCss(css, true) + close);
        return part;
    }).join('');
}

/* ---------------- run ---------------- */

function walk(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
        const full = path.join(dir, e.name);
        if (e.isDirectory()) return walk(full);
        return e.name.endsWith('.blade.php') ? [full] : [];
    });
}

const changedFiles = [];
for (const file of walk(viewsDir)) {
    const rel = path.relative(viewsDir, file).split(path.sep).join('/');
    if (EXCLUDE.includes(rel)) continue;
    const src = fs.readFileSync(file, 'utf8');
    const out = rewriteView(src);
    if (out !== src) {
        changedFiles.push(rel);
        if (!checkOnly) fs.writeFileSync(file, out);
    }
}

if (checkOnly) {
    if (changedFiles.length) {
        console.log('Views with colors that are not themed yet:\n  ' + changedFiles.join('\n  '));
        process.exit(1);
    }
    console.log('All view colors are themed.');
    process.exit(0);
}

const lines = [...used.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, value]) => `        ${name}: ${value};`);

fs.writeFileSync(paletteFile, `{{-- GENERATED by scripts/theme/build-dark-theme.mjs. Do not edit by hand; run the script instead. --}}
<style>
    html[data-theme="dark"] {
${lines.join('\n')}
    }
</style>
`);

console.log(`Rewrote ${changedFiles.length} view(s); ${used.size} dark color variable(s) written to ${path.relative(root, paletteFile)}.`);
