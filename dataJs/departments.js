// Departments management — fully real-time (no page reloads). Create/delete
// update the card list in place; member/permission changes update the card
// count pills live. Top-right non-blocking toasts.
$(function () {
    var Toast = Swal.mixin({
        toast: true, position: "top-end", backdrop: false,
        showConfirmButton: false, timer: 2000, timerProgressBar: true
    });
    function toast(icon, title) { Toast.fire({ icon: icon, title: title }); }

    function esc(s) {
        s = (s == null ? "" : String(s));
        return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function updateDeptCount(delta) {
        var $c = $("#dept_count");
        var n = (parseInt($c.text(), 10) || 0) + delta;
        if (n < 0) n = 0;
        $c.text(n);
        $("#dept_empty").toggle(n === 0);
    }

    // Build a department card (matches the server-rendered markup).
    function buildCard(d) {
        var desc = d.description ? " &middot; " + esc(d.description) : "";
        return '' +
        '<div class="dept-card" data-dept-id="' + d.id + '">' +
          '<div class="d-flex justify-content-between align-items-start">' +
            '<div>' +
              '<h5>' + esc(d.name) + '</h5>' +
              '<div class="dept-meta">Base Role: <strong>' + esc(d.base_role_name || "—") + '</strong>' + desc + '</div>' +
              '<div class="mt-2">' +
                '<span class="dept-pill"><span class="dept-member-count">0</span> Members</span> ' +
                '<span class="dept-pill" style="background:#e5f6ea;color:#1a8a3a;"><span class="dept-allow-count">0</span> Allow</span> ' +
                '<span class="dept-pill dept-deny-pill" style="background:#fbeaea;color:#c0392b;display:none;"><span class="dept-deny-count">0</span> Deny</span>' +
              '</div>' +
            '</div>' +
            '<div class="dept-actions text-right">' +
              '<button class="btn btn-outline-primary btn-sm dept-members" data-id="' + d.id + '" data-name="' + esc(d.name) + '" data-role="' + d.base_role_id + '">Members</button> ' +
              '<button class="btn btn-outline-secondary btn-sm dept-perms" data-id="' + d.id + '" data-name="' + esc(d.name) + '">Permissions</button> ' +
              '<button class="btn btn-outline-danger btn-sm dept-delete" data-id="' + d.id + '" data-name="' + esc(d.name) + '">Delete</button>' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    // --- Create (real-time, no reload) ----------------------------------
    $("#dept_create_form").on("submit", function (e) {
        e.preventDefault();
        var data = {
            name: $("#dept_name").val(),
            base_role_id: $("#dept_base_role").val(),
            description: $("#dept_desc").val()
        };
        if (!data.name || !data.base_role_id) {
            toast("error", "Name and base role are required.");
            return;
        }
        $.ajax({
            url: "ajax/tools/permissions/departments_create_ajax.php",
            type: "POST", data: data, dataType: "json",
            success: function (res) {
                if (res && res.status === "success" && res.department) {
                    $("#dept_list").prepend(buildCard(res.department)); // new card on top
                    updateDeptCount(1);
                    document.getElementById("dept_create_form").reset();
                    toast("success", "Department created.");
                } else {
                    toast("error", (res && res.message) || "Could not create.");
                }
            },
            error: function () { toast("error", "Request failed."); }
        });
    });

    // --- Delete (real-time) ---------------------------------------------
    $(document).on("click", ".dept-delete", function () {
        var id = $(this).data("id");
        var name = $(this).data("name");
        Swal.fire({
            title: "Delete department?",
            text: '"' + name + '" and its members/permissions will be removed. Base role and users are untouched.',
            icon: "warning", showCancelButton: true, confirmButtonColor: "#c0392b",
            confirmButtonText: "Delete"
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.ajax({
                url: "ajax/tools/permissions/departments_delete_ajax.php",
                type: "POST", data: { id: id }, dataType: "json",
                success: function (res) {
                    if (res && res.status === "success") {
                        $('.dept-card[data-dept-id="' + id + '"]').remove();
                        updateDeptCount(-1);
                        toast("success", "Department deleted.");
                    } else { toast("error", (res && res.message) || "Delete failed."); }
                },
                error: function () { toast("error", "Request failed."); }
            });
        });
    });

    // --- Members modal ---------------------------------------------------
    var membersModal = new bootstrap.Modal(document.getElementById("deptMembersModal"));
    var currentDeptId = null;

    function refreshMemberCount() {
        var n = $("#mem_body .mem-check:checked").length;
        $('.dept-card[data-dept-id="' + currentDeptId + '"] .dept-member-count').text(n);
    }

    $(document).on("click", ".dept-members", function () {
        currentDeptId = $(this).data("id");
        $("#mem_dept_name").text($(this).data("name"));
        $("#mem_body").html('<div class="text-muted p-3">Loading…</div>');
        $("#mem_search").val("");
        membersModal.show();
        $.get("ajax/tools/permissions/department_members_list_ajax.php", { department_id: currentDeptId }, function (html) {
            $("#mem_body").html(html);
        });
    });

    $(document).on("change", ".mem-check", function () {
        var $c = $(this);
        var uid = $c.data("uid");
        var member = this.checked ? 1 : 0;
        $.ajax({
            url: "ajax/tools/permissions/department_member_toggle_ajax.php",
            type: "POST", data: { department_id: currentDeptId, user_id: uid, member: member }, dataType: "json",
            success: function (res) {
                if (res && res.status === "success") {
                    refreshMemberCount();
                    toast("success", member ? "Added to department" : "Removed from department");
                } else {
                    $c.prop("checked", !member);
                    toast("error", (res && res.message) || "Save failed.");
                }
            },
            error: function () { $c.prop("checked", !member); toast("error", "Request failed."); }
        });
    });

    $("#mem_search").on("input", function () {
        var q = (this.value || "").toLowerCase().trim();
        $("#mem_body .mem-row").each(function () {
            var m = !q || ($(this).data("search") || "").indexOf(q) !== -1;
            $(this).toggleClass("hidden", !m);
        });
    });

    // --- Permissions modal ----------------------------------------------
    var permsModal = new bootstrap.Modal(document.getElementById("deptPermsModal"));
    var currentPermDeptId = null;

    function refreshPermCounts() {
        var allow = $('#dperm_body .perm-choice input[value="allow"]:checked').length;
        var deny = $('#dperm_body .perm-choice input[value="deny"]:checked').length;
        var $card = $('.dept-card[data-dept-id="' + currentPermDeptId + '"]');
        $card.find(".dept-allow-count").text(allow);
        $card.find(".dept-deny-count").text(deny);
        $card.find(".dept-deny-pill").toggle(deny > 0);
    }

    $(document).on("click", ".dept-perms", function () {
        currentPermDeptId = $(this).data("id");
        $("#perm_dept_name").text($(this).data("name"));
        $("#dperm_body").html('<div class="text-muted p-3">Loading…</div>');
        $("#dperm_search").val("");
        permsModal.show();
        $.get("ajax/tools/permissions/department_permissions_list_ajax.php", { department_id: currentPermDeptId }, function (html) {
            $("#dperm_body").html(html);
        });
    });

    $(document).on("change", "#dperm_body .perm-choice input[type=radio]", function () {
        var $row = $(this).closest(".perm-row");
        var aid = $row.data("aid");
        var state = $(this).val();
        var changes = {}; changes[aid] = state;
        $.ajax({
            url: "ajax/tools/permissions/department_permissions_save_ajax.php",
            type: "POST", data: { department_id: currentPermDeptId, changes: changes }, dataType: "json",
            success: function (res) {
                if (res && res.status === "success") {
                    refreshPermCounts();
                    toast("success", "Saved → " + state);
                } else { toast("error", (res && res.message) || "Save failed."); }
            },
            error: function () { toast("error", "Request failed."); }
        });
    });

    $("#dperm_search").on("input", function () {
        var q = (this.value || "").toLowerCase().trim();
        $("#dperm_body .perm-row").each(function () {
            var m = !q || ($(this).data("search") || "").indexOf(q) !== -1;
            $(this).toggleClass("hidden", !m);
        });
        $("#dperm_body .dperm-mod").each(function () {
            $(this).toggle($(this).find(".perm-row:not(.hidden)").length > 0);
        });
    });
});
