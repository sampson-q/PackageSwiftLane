#!/usr/bin/env node
"use strict";

// Page shell check: every page that shows the top bar (includes
// views/inc/topbar.php) must open its <div class="page-wrapper"> with a title
// row that has a title - otherwise the top strip beside the icon tray is empty.
//
// Accepted first thing inside .page-wrapper:
//   <?php cdp_pageHeader('Title' ...); ?>          (views/inc/page_header.php)
//   <div class="page-breadcrumb"> ... class="page-title"> non-empty ... </div>
//
// Run:   node scripts/check_page_shell.js
// Runs on every commit through .githooks/pre-commit (git config core.hooksPath .githooks).
// Exit code 1 lists every page that breaks the rule.

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");

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

const problems = [];
for (const file of walk(path.join(root, "views"), [])) {
    const rel = path.relative(root, file).split(path.sep).join("/");
    // views/inc/ holds the shared pieces (top bar, footer, this header), not pages.
    if (rel.startsWith("views/inc/")) { continue; }
    const src = fs.readFileSync(file, "utf8");
    if (!/inc\/topbar\.php/.test(src)) { continue; }
    const m = /<div[^>]*class="[^"]*\bpage-wrapper\b[^"]*"[^>]*>/.exec(src);
    if (!m) { problems.push(rel + ": no <div class=\"page-wrapper\">"); continue; }
    const rest = stripLead(src.slice(m.index + m[0].length));

    const call = /^<\?php\s+cdp_pageHeader\(\s*(['"])(.*?)\1|^<\?php\s+cdp_pageHeader\(\s*\$/.exec(rest);
    if (call) {
        if (call[1] !== undefined && call[2].trim() === "") { problems.push(rel + ": cdp_pageHeader() is given an empty title"); }
        continue;
    }
    if (/^<div class="page-breadcrumb\b/.test(rest)) {
        const block = rest.slice(0, blockEnd(rest));
        const t = /class="page-title[^"]*"[^>]*>([\s\S]*?)<\/h\d>/.exec(block);
        const text = t ? t[1].replace(/<(?!\?)[^>]+>/g, "").replace(/\s+/g, "") : "";
        if (text === "") { problems.push(rel + ": the title row has no title"); }
        continue;
    }
    problems.push(rel + ": .page-wrapper does not open with a title row (call cdp_pageHeader() first)");
}

if (problems.length) {
    console.error("Page shell check failed - the top strip would be empty on these pages:");
    for (const p of problems) { console.error("  " + p); }
    console.error("Fix: make <?php cdp_pageHeader('Title'); ?> the first thing inside <div class=\"page-wrapper\"> (views/inc/page_header.php).");
    process.exit(1);
}
console.log("Page shell check passed.");
