#!/usr/bin/env node
"use strict";

// Page pattern check: every page that shows the top bar (includes
// views/inc/topbar.php) must follow the shared page skeleton, so that the
// design system (assets/css_main_swiftlane/css/swiftlane-ds.css) presents it
// like every other page on every screen size. See docs/UI_PAGE_PATTERN.md.
//
// Rules (each one names the pages that break it):
//   shell     .page-wrapper opens with a title row that has a title
//             (<?php cdp_pageHeader('Title'); ?>, views/inc/page_header.php)
//   wrapper   the title row is followed by <div class="container-fluid"> (or
//             <div class="email-app"> on the settings pages with a side menu)
//   grid      grid columns carry a breakpoint: col-md-6, not col-6 (a column
//             without one never stacks on a phone); col-12 and col-auto are fine
//   colours   no hard-coded colours in the page's <style> blocks or style=""
//             attributes - use the tokens (var(--ink-800), var(--accent), ...)
//   tables    every <table> sits inside <div class="table-responsive">
//
// Pages that broke a rule before the rule existed are listed in
// scripts/ui_baseline.json and are tolerated for that rule only; a page that
// is new, or that starts breaking a rule it did not break before, fails.
// Fix the page rather than adding it to the baseline. When a baselined page
// is cleaned up, run with --update-baseline to drop it from the list.
//
// Run:   node scripts/check_page_shell.js [--update-baseline]
// Runs on every commit through .githooks/pre-commit (git config core.hooksPath .githooks).
// Exit code 1 lists every page that breaks a rule.

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const baselineFile = path.join(__dirname, "ui_baseline.json");
const updateBaseline = process.argv.includes("--update-baseline");

function walk(dir, out) {
    for (const name of fs.readdirSync(dir)) {
        const p = path.join(dir, name);
        const st = fs.statSync(p);
        if (st.isDirectory()) { walk(p, out); }
        else if (name.endsWith(".php")) { out.push(p); }
    }
    return out;
}

function stripLead(s) {
    // whitespace, HTML comments and comment-only PHP blocks before the first element
    let prev;
    do {
        prev = s;
        s = s.replace(/^\s+/, "")
             .replace(/^<!--[\s\S]*?-->/, "")
             .replace(/^<\?php\s*(\/\*[\s\S]*?\*\/|\/\/[^\n]*)\s*\?>/, "");
    } while (s !== prev);
    return s;
}

function blockEnd(s) {
    // end of the <div> element that starts s
    const re = /<(\/?)div\b[^>]*>/gi;
    let depth = 0, m;
    while ((m = re.exec(s))) {
        depth += m[1] ? -1 : 1;
        if (depth === 0) { return re.lastIndex; }
    }
    return s.length;
}

// Markup without PHP code and HTML comments, so a rule never fires on a
// commented-out block or on a PHP string.
function markupOnly(src) {
    return src.replace(/<\?(?:php|=)[\s\S]*?\?>/g, " ").replace(/<!--[\s\S]*?-->/g, " ");
}

const found = { shell: [], wrapper: [], grid: [], colours: [], tables: [] };
const COLOUR = /#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i;

for (const file of walk(path.join(root, "views"), [])) {
    const rel = path.relative(root, file).split(path.sep).join("/");
    // views/inc/ holds the shared pieces (top bar, footer, the header), not pages.
    if (rel.startsWith("views/inc/")) { continue; }
    const src = fs.readFileSync(file, "utf8");
    if (!/inc\/topbar\.php/.test(src)) { continue; }

    // shell: the title row
    const m = /<div[^>]*class="[^"]*\bpage-wrapper\b[^"]*"[^>]*>/.exec(src);
    if (!m) { found.shell.push(rel + ": no <div class=\"page-wrapper\">"); continue; }
    let rest = stripLead(src.slice(m.index + m[0].length));
    const call = /^<\?php\s+cdp_pageHeader\(\s*(['"])(.*?)\1|^<\?php\s+cdp_pageHeader\(\s*\$/.exec(rest);
    if (call) {
        if (call[1] !== undefined && call[2].trim() === "") { found.shell.push(rel + ": cdp_pageHeader() is given an empty title"); }
        rest = rest.slice(rest.indexOf("?>") + 2);
    } else if (/^<div class="page-breadcrumb\b/.test(rest)) {
        const end = blockEnd(rest);
        const block = rest.slice(0, end);
        const t = /class="page-title[^"]*"[^>]*>([\s\S]*?)<\/h\d>/.exec(block);
        const text = t ? t[1].replace(/<(?!\?)[^>]+>/g, "").replace(/\s+/g, "") : "";
        if (text === "") { found.shell.push(rel + ": the title row has no title"); }
        rest = rest.slice(end);
    } else {
        found.shell.push(rel + ": .page-wrapper does not open with a title row (call cdp_pageHeader() first)");
    }

    // wrapper: what follows the title row. A <form> around the whole page is
    // transparent here: the form pages wrap their container in one.
    let after = stripLead(markupOnly(rest));
    const formTag = /^<form\b[^>]*>/.exec(after);
    if (formTag) {
        after = stripLead(after.slice(formTag[0].length));
        let hidden;
        while ((hidden = /^<input\b[^>]*type="hidden"[^>]*>/.exec(after))) { after = stripLead(after.slice(hidden[0].length)); }
    }
    const first = /^<(\w+)([^>]*)>/.exec(after);
    const cls = first ? ((/class="([^"]*)"/.exec(first[2]) || [])[1] || "") : "";
    if (!first || first[1] !== "div" || !/(^|\s)(container-fluid|email-app)(\s|$)/.test(cls)) {
        found.wrapper.push(rel + ": the title row is followed by <" + (first ? first[1] + (cls ? " class=\"" + cls + "\"" : "") : "?") + ">, not <div class=\"container-fluid\">");
    }

    const markup = markupOnly(src);

    // grid: columns without a breakpoint
    const cols = new Set();
    for (const cm of markup.matchAll(/class="([^"]*)"/g)) {
        for (const c of cm[1].split(/\s+/)) {
            if (/^col-(?:[1-9]|1[01])$/.test(c)) { cols.add(c); }
        }
    }
    if (cols.size) { found.grid.push(rel + ": " + [...cols].sort().join(", ") + " (use col-md-N / col-lg-N)"); }

    // colours: hard-coded in <style> blocks or style attributes
    const colourHits = [];
    for (const sm of markup.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) {
        const body = sm[1].replace(/\/\*[\s\S]*?\*\//g, "");
        const hit = COLOUR.exec(body);
        if (hit) { colourHits.push("<style> " + hit[0]); }
    }
    for (const am of markup.matchAll(/\sstyle="([^"]*)"/g)) {
        const hit = COLOUR.exec(am[1]);
        if (hit) { colourHits.push("style=\"" + am[1].trim().slice(0, 40) + "\""); }
    }
    if (colourHits.length) { found.colours.push(rel + ": " + colourHits.length + " hard-coded colour(s), e.g. " + colourHits[0]); }

    // tables: every <table> inside a .table-responsive box
    let open = 0, bare = 0;
    for (const tm of markup.matchAll(/<(\/?)(div|table)\b([^>]*)>/g)) {
        if (tm[2] === "div") {
            if (tm[1]) { if (open > 0) { open--; } }
            else if (/class="[^"]*\btable-responsive\b/.test(tm[3])) { open++; }
            else if (open > 0) { open++; } // a div inside the box counts toward its depth
        } else if (!tm[1] && open === 0) {
            bare++;
        }
    }
    if (bare) { found.tables.push(rel + ": " + bare + " <table> outside a .table-responsive box"); }
}

// Baseline handling
const rules = Object.keys(found);
const fileOf = (p) => p.split(":")[0];
if (updateBaseline) {
    const base = {};
    for (const r of rules) { base[r] = [...new Set(found[r].map(fileOf))].sort(); }
    fs.writeFileSync(baselineFile, JSON.stringify(base, null, 2) + "\n");
    console.log("Baseline written to scripts/ui_baseline.json: " + rules.map((r) => r + "=" + base[r].length).join(", "));
    process.exit(0);
}
let baseline = {};
try { baseline = JSON.parse(fs.readFileSync(baselineFile, "utf8")); } catch (e) { baseline = {}; }

const messages = {
    shell: "the top strip would be empty on these pages - make <?php cdp_pageHeader('Title'); ?> the first thing inside <div class=\"page-wrapper\"> (views/inc/page_header.php)",
    wrapper: "page content must sit in <div class=\"container-fluid\"> right after the title row (settings pages with the side menu use <div class=\"email-app\">)",
    grid: "grid columns need a breakpoint so they stack on phones (col-md-6, col-lg-4 ...; col-12 and col-auto are fine)",
    colours: "no hard-coded colours in a page - use the design tokens (var(--ink-800), var(--accent), var(--slate-400) ...) from assets/css_main_swiftlane/css/swiftlane-tokens.css",
    tables: "wrap every <table> in <div class=\"table-responsive\"> so it scrolls instead of breaking the page on narrow screens"
};
let failed = false;
for (const r of rules) {
    const tolerated = new Set(baseline[r] || []);
    const bad = found[r].filter((p) => !tolerated.has(fileOf(p)));
    if (!bad.length) { continue; }
    failed = true;
    console.error("[" + r + "] " + messages[r] + ":");
    for (const p of bad) { console.error("  " + p); }
}
if (failed) {
    console.error("See docs/UI_PAGE_PATTERN.md for the page skeleton these rules describe.");
    process.exit(1);
}
console.log("Page pattern check passed (" + rules.map((r) => r + ": " + found[r].length + " baselined").join(", ") + ").");
