// Per-user permission overrides — instant save, top-right toast (no backdrop),
// PER-CARD multi-select, live filter. Row state: inherit | allow | deny.
$(function () {
    var $root = $("#user_perms");
    var userId = $root.data("user-id");
    var SAVE_URL = "ajax/users/users_overrides_save_ajax.php";

    // Non-blocking toast, top-right. backdrop:false so it never covers the page
    // — the user can keep selecting while toasts come and go.
    var Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        backdrop: false,
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        didOpen: function (t) {
            t.addEventListener("mouseenter", Swal.stopTimer);
            t.addEventListener("mouseleave", Swal.resumeTimer);
        }
    });
    function toast(icon, title) { Toast.fire({ icon: icon, title: title }); }

    // Persist { aid: state } changes; onDone(appliedMap) on success.
    function save(changes, onDone) {
        if (!Object.keys(changes).length) return;
        $.ajax({
            url: SAVE_URL,
            type: "POST",
            data: { user_id: userId, changes: changes },
            dataType: "json",
            success: function (res) {
                if (res && res.status === "success") {
                    if (onDone) onDone(res.applied || {});
                } else {
                    toast("error", (res && res.message) || "Could not save.");
                }
            },
            error: function (xhr) {
                var msg = "Request failed.";
                if (xhr.status === 403) msg = "No permission to manage this user.";
                else if (xhr.status === 419) msg = "Session expired — reload the page.";
                toast("error", msg);
            }
        });
    }

    function prettyLabel($row) {
        return ($row.find(".perm-label").clone().children().remove().end().text() || "").trim();
    }

    // Update the effective-state badge to match the new override state.
    function setBadge($row, state) {
        var $b = $row.find(".perm-badge");
        var roleOn = String($row.data("role-on")) === "1";
        var cls, txt;
        if (state === "allow") { cls = "on"; txt = "allowed"; }
        else if (state === "deny") { cls = "off"; txt = "denied"; }
        else { cls = roleOn ? "on" : "off"; txt = roleOn ? "role: allowed" : "role: none"; }
        $b.removeClass("on off").addClass(cls).text(txt);
    }

    // --- Instant save on any radio change --------------------------------
    $root.on("change", ".perm-choice input[type=radio]", function () {
        var $row = $(this).closest(".perm-row");
        var state = $(this).val();
        var changes = {}; changes[$row.data("aid")] = state;
        $row.addClass("saving");
        save(changes, function () {
            $row.removeClass("saving");
            setBadge($row, state);
            toast("success", prettyLabel($row) + " → " + state);
        });
    });

    // --- Per-card multi-select ------------------------------------------
    function refreshCard($card) {
        var n = $card.find(".row-check:checked").length;
        $card.find(".card-sel-count").text(n);
        $card.find(".perm-card-bulk").toggleClass("show", n > 0);
        // keep the card's "select all" in sync with visible rows
        var $visible = $card.find(".perm-row:not(.hidden) .row-check");
        var allOn = $visible.length > 0 && $visible.filter(":checked").length === $visible.length;
        $card.find(".card-select-all").prop("checked", allOn);
    }

    $root.on("change", ".row-check", function () {
        refreshCard($(this).closest(".perm-mod-card"));
    });

    $root.on("change", ".card-select-all", function () {
        var $card = $(this).closest(".perm-mod-card");
        var on = this.checked;
        $card.find(".perm-row:not(.hidden) .row-check").prop("checked", on);
        refreshCard($card);
    });

    $root.on("click", ".card-sel-clear", function () {
        var $card = $(this).closest(".perm-mod-card");
        $card.find(".row-check, .card-select-all").prop("checked", false);
        refreshCard($card);
    });

    // Bulk apply — only to THIS card's checked rows.
    $root.on("click", ".perm-card-bulk button[data-bulk]", function () {
        var $card = $(this).closest(".perm-mod-card");
        var state = $(this).data("bulk");
        var $rows = $card.find(".row-check:checked").closest(".perm-row");
        if (!$rows.length) return;
        var changes = {};
        $rows.each(function () { changes[$(this).data("aid")] = state; });
        $rows.addClass("saving");
        save(changes, function (applied) {
            $rows.each(function () {
                var $r = $(this);
                $r.removeClass("saving");
                $r.find(".perm-choice input[value='" + state + "']").prop("checked", true);
                setBadge($r, state);
                $r.find(".row-check").prop("checked", false);
            });
            refreshCard($card);
            toast("success", Object.keys(applied).length + " permission(s) → " + state);
        });
    });

    // --- Live filter -----------------------------------------------------
    function applyFilter() {
        var q = ($("#perm_search").val() || "").toLowerCase().trim();
        var shown = 0;
        $root.find(".perm-row").each(function () {
            var match = !q || ($(this).data("search") || "").indexOf(q) !== -1;
            $(this).toggleClass("hidden", !match);
            if (match) shown++;
        });
        // Hide cards with no visible rows; refresh their select-all state.
        $root.find(".perm-mod-group").each(function () {
            var $g = $(this);
            var any = $g.find(".perm-row:not(.hidden)").length > 0;
            $g.toggle(any);
            refreshCard($g.find(".perm-mod-card"));
        });
        $("#perm_visible_count").text(shown);
        $("#perm_no_results").toggle(shown === 0);
    }
    $("#perm_search").on("input", applyFilter);
    applyFilter();
});
