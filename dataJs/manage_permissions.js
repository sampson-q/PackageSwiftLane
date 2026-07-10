// Manage Permissions — define assignable permission actions
// (cdb_user_module_actions). Real-time, toast feedback.
$(function () {
    var Toast = Swal.mixin({
        toast: true, position: "top-end", backdrop: false,
        showConfirmButton: false, timer: 2200, timerProgressBar: true
    });
    function toast(icon, title) { Toast.fire({ icon: icon, title: title }); }

    var MODULES = window.PA_MODULES || [];
    function moduleOptions(selectedId) {
        return MODULES.map(function (m) {
            return '<option value="' + m.id + '"' + (String(m.id) === String(selectedId) ? ' selected' : '') + '>' +
                $("<span>").text(m.name).html() + '</option>';
        }).join('');
    }

    function loadList() {
        $.get("ajax/tools/permissions/permission_action_list_ajax.php", function (html) {
            $("#pa_list").html(html);
            filterList();
        });
    }
    loadList();

    // --- Add ------------------------------------------------------------
    $("#pa_add_form").on("submit", function (e) {
        e.preventDefault();
        var data = {
            action_name: $("#pa_name").val(),
            module_id: $("#pa_module").val(),
            description_module: $("#pa_desc").val()
        };
        $.ajax({
            url: "ajax/tools/permissions/permission_action_add_ajax.php",
            type: "POST", data: data, dataType: "json",
            success: function (res) {
                if (res && res.status === "success") {
                    document.getElementById("pa_add_form").reset();
                    toast("success", "Permission added.");
                    Swal.fire({ icon: "info", title: "Added", html: res.message, confirmButtonText: "Got it" });
                    loadList();
                } else {
                    toast("error", (res && res.message) || "Could not add.");
                }
            },
            error: function () { toast("error", "Request failed."); }
        });
    });

    // --- Search ---------------------------------------------------------
    function filterList() {
        var q = ($("#pa_search").val() || "").toLowerCase().trim();
        $("#pa_list .pa-row").each(function () {
            var m = !q || ($(this).data("search") || "").indexOf(q) !== -1;
            $(this).toggleClass("d-none", !m);
        });
        $("#pa_list .pa-mod").each(function () {
            $(this).toggle($(this).find(".pa-row:not(.d-none)").length > 0);
        });
    }
    $("#pa_search").on("input", filterList);

    // --- Edit -----------------------------------------------------------
    $(document).on("click", ".pa-edit", function () {
        var $row = $(this).closest(".pa-row");
        var id = $row.data("id"), name = $row.data("name");
        var mod = $row.data("module"), desc = $row.data("desc");
        Swal.fire({
            title: "Edit Permission",
            html:
                '<div class="text-left" style="font-size:14px;">' +
                '<p class="mb-2">Name: <code>' + $("<span>").text(name).html() + '</code> <small class="text-muted">(not editable — it is used in code)</small></p>' +
                '<label class="mb-1">Module</label>' +
                '<select id="pae_module" class="form-control mb-2">' + moduleOptions(mod) + '</select>' +
                '<label class="mb-1">Description</label>' +
                '<input id="pae_desc" class="form-control" value="' + $("<span>").text(desc).html() + '">' +
                '</div>',
            showCancelButton: true, confirmButtonText: "Save",
            showLoaderOnConfirm: true, allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                var d = String($("#pae_desc").val() || "").trim();
                if (d === "") { Swal.showValidationMessage("Description is required."); return false; }
                return $.ajax({
                    url: "ajax/tools/permissions/permission_action_edit_ajax.php",
                    type: "POST", dataType: "json",
                    data: { id: id, module_id: $("#pae_module").val(), description_module: d }
                }).then(function (r) {
                    if (!r || r.status !== "success") { Swal.showValidationMessage((r && r.message) || "Save failed."); return false; }
                    return r;
                }, function () { Swal.showValidationMessage("Request failed."); return false; });
            }
        }).then(function (res) {
            if (res.isConfirmed) { toast("success", "Saved."); loadList(); }
        });
    });

    // --- Delete ---------------------------------------------------------
    $(document).on("click", ".pa-delete", function () {
        var $row = $(this).closest(".pa-row");
        var id = $row.data("id"), name = $row.data("name");
        Swal.fire({
            title: "Delete Permission?",
            html: 'Delete <code>' + $("<span>").text(name).html() + '</code> and remove it from all roles/departments.<br><br>' +
                  '<b class="text-danger">If code still checks this permission, that check will then deny everyone (except superadmin).</b>',
            icon: "warning", showCancelButton: true, confirmButtonColor: "#c0392b", confirmButtonText: "Delete"
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.ajax({
                url: "ajax/tools/permissions/permission_action_delete_ajax.php",
                type: "POST", data: { id: id }, dataType: "json",
                success: function (res) {
                    if (res && res.status === "success") { toast("success", "Deleted."); loadList(); }
                    else { toast("error", (res && res.message) || "Delete failed."); }
                },
                error: function () { toast("error", "Request failed."); }
            });
        });
    });
});
