# Page pattern

Every page in Swift Lane is presented by one design system:

- `assets/css_main_swiftlane/css/swiftlane-tokens.css` - the tokens (colours, type, radii, control sizes)
- `assets/css_main_swiftlane/css/swiftlane-ds.css` - the shell and every component, loaded last on every page
- `dataJs/swiftlane_ui.js` - the runtime adapter (status pills, search fields, table cells, scroll shadows)

A page gets the look, the spacing and the phone / tablet / desktop behaviour
from those three files. It does not carry its own. The rules below are what
makes a page "one of ours"; `node scripts/check_page_shell.js` enforces them
on every commit (`.githooks/pre-commit`), and `scripts/ui_baseline.json`
lists the pages that broke a rule before the rule existed. A new page is
never added to the baseline: it follows the pattern.

## The skeleton

```php
<div id="main-wrapper">
    <?php include 'views/inc/topbar.php'; ?>
    <?php include 'views/inc/left_sidebar.php'; ?>

    <div class="page-wrapper">
        <?php cdp_pageHeader('Shipping Rates', ['sub' => $row->name ?? '', 'actions' => $buttonsHtml]); ?>

        <div class="container-fluid">
            <div id="resultados_ajax"></div>          <!-- AJAX messages land here -->

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            ...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'views/inc/footer.php'; ?>
    </div>
</div>
```

1. **Title row first.** `cdp_pageHeader('Title')` is the first thing inside
   `.page-wrapper` (views/inc/page_header.php). The shell has no separate top
   bar: that row is pulled up beside the icon tray. Titles are Title Case; a
   record name or reference goes in `sub`; page-level buttons go in `actions`.
   A card title that would repeat the page title gets class `swl-promoted`.
2. **One content wrapper.** The title row is followed by
   `<div class="container-fluid">`. Form pages may wrap it in their `<form>`.
   Settings pages with the side menu (views/inc/left_part_menu.php) use
   `<div class="email-app">` with `.left-part` and `.right-part` instead.
   Nothing else: no `bg-light`, no `p-15`, no bare `.row` under `.page-wrapper`.
3. **Cards hold content.** Content sits in `.card > .card-body`. A heading
   inside a card is `.card-title`. Related inputs sit in `.form-group` with a
   `<label>`.
4. **Grid columns carry a breakpoint.** `col-md-6`, `col-lg-4`, `col-xl-3`.
   A `col-6` without one never stacks on a phone. `col-12` and `col-auto` are
   fine. (Below 576px the stylesheet stacks every column anyway; a column
   that must stay beside its neighbour on a phone is marked `swl-keep`.)
5. **Tables scroll.** Every `<table class="table">` sits in
   `<div class="table-responsive">`. Short cells stay on one line and the box
   shows a shadow on the side that has more; nothing else is needed.
6. **No colours in a page.** No hex / rgb values in a `<style>` block or a
   `style=""` attribute. Use the tokens: `var(--ink-800)`, `var(--accent)`,
   `var(--slate-400)`, `var(--green-500)`, `var(--red-500)`,
   `var(--amber-surface)` and so on. Status chips come from `cdb_styles`
   and are tinted by the adapter; do not colour them by hand.
7. **No page CSS for layout.** If a page needs a component the sheet does not
   have, add it to swiftlane-ds.css (sections 11 and 13 hold the panel
   library) so the next page can use it too. A `<style>` block in a page is
   for a one-off that cannot be generic, and it uses the tokens.

## Components

| Need | Use |
| --- | --- |
| Primary action | `.btn.btn-primary` (amber) - the template's `btn-outline-danger` / `btn-danger` / `btn-warning` map to it too |
| Secondary action | `.btn.btn-secondary` / `.btn-info` / `.btn-outline-*` (white ring pill) |
| Dark action | `.btn.btn-dark` / `.btn-success` |
| Destructive | a `.btn` whose class contains `delete` / `remove`, or that holds a trash icon |
| Inputs | `.form-control`, `.custom-select`, Select2 - 48px ring fields; inside a table cell they are 36px automatically |
| Search above a list | `<input id="search" class="form-control">` - the adapter turns it into the pill search field, the field takes the room, the button beside it is as wide as its label |
| Status chip | `<span class="label" style="background:#hex">` from `cdb_styles` (AJAX) or `.label-success` / `.label-danger` / ... |
| KPI card | `cdp_dashKpi()` / `.swl-metric` (helpers/dashboard_data.php) |
| Panel, bars, key-value list, feed, stats, legend, banner | `cdp_dashPanel()`, `cdp_dashBars()`, `cdp_dashKv()`, `cdp_dashFeed()`, `cdp_dashStats()`, `cdp_dashLegend()`, `cdp_dashBanner()` |
| Section heading between cards | `<h2 class="swl-section">` |
| Modal | `.modal > .modal-dialog > .modal-content` with `.modal-header` (`.modal-title` + `.close`), `.modal-body`, `.modal-footer` (cancel left, primary right) |
| Confirm / toast | SweetAlert2 v11 from the footer - never re-load the template's v7 bundle |

## Things the sheet and the adapter take care of

- A check box or radio that is itself a grid column (`col-md-3 custom-control custom-radio`) keeps its box inside the column; a set of them beside a label goes in `.swl-check-row`.
- A row-action dropdown inside `.table-responsive` is pinned to the viewport while open, so the scroll box never clips it.
- The side menu: 264px from 1170px up (collapsible, remembered), an 84px rail below that which the toggle opens as an overlay, off-canvas on phones with a backdrop, an X and Escape to close it.

## Screen sizes

The sheet is responsive from 360px up. What changes:

- **>= 1170px** - full 264px sidebar (collapsible to 84px, remembered per browser), title row beside the tray, cards 24px radius.
- **768-1169px** - sidebar collapses automatically; KPI cards go two per row; form rows bottom-align so wrapped labels do not knock controls out of line.
- **< 768px** - the sidebar is off-canvas behind the hamburger; the header bar takes taps; the settings side menu becomes a horizontal pill strip above the content; cards 16px radius.
- **< 576px** - every grid column stacks; right-floated button groups go full width; KPI cards one per row.

## Checking a page

Render it without logging in and audit it at four widths:

```bash
MSYS_NO_PATHCONV=1 docker exec php84 php /tmp/render_preview.php views/x/x.php s_x 9 "id=1"
```

then open `dev_preview/audit2.html?pages=s_x` on the preview server
(`.claude/launch.json` -> `preview-static`, port 8099). It reports elements
cut off at the viewport edge, content outside its card, clipped text, odd
control heights, overlapping siblings, tables that overflow without a scroll
box, tap targets under 30px on phones, template fonts leaking, hard-coded
colours, and every modal on the page opened and measured. A clean page has
only `table-scroll` entries (a wide table scrolling inside its box).
