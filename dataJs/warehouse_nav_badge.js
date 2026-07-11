"use strict";
// ============================================================================
// Warehouse nav badge — real-time count of packages cleared for delivery that
// still need delivering. Polls the Warehouse Delivery count endpoint so the
// sidebar badge updates without a full page refresh. Only runs for users who
// can see the badge (view_warehouse_delivery); the badge is hidden at 0.
// ============================================================================
(function () {
    var URL = "ajax/courier/warehouse_delivery_ajax.php?action=count";

    function apply(n) {
        var $b = $(".wd-nav-badge");
        if (!$b.length) { return; }
        if (n > 0) { $b.text(n).show(); } else { $b.text("").hide(); }
    }

    function refresh() {
        if (!$(".wd-nav-badge").length) { return; }
        $.getJSON(URL).done(function (r) {
            if (r && r.ok) { apply(Number(r.count) || 0); }
        });
    }

    // Let other scripts (e.g. after a delivery on the WD page) force an update.
    window.wdRefreshNavBadge = refresh;

    $(function () {
        if (!$(".wd-nav-badge").length) { return; } // not permitted / no badge
        refresh();
        setInterval(refresh, 20000); // every 20s
    });
})();
