// Per-user permission overrides — submit tri-state (inherit/allow/deny) grid.
$(function () {
    $("#user_perms_form").on("submit", function (e) {
        e.preventDefault();
        var $form = $(this);
        var payload = {
            user_id: $form.find("input[name='user_id']").val(),
            overrides: {}
        };
        // Only send explicit allow/deny; 'inherit' means "no override row".
        $form.find(".perm-choice").each(function () {
            var aid = $(this).data("aid");
            var val = $(this).find("input[type=radio]:checked").val();
            if (val === "allow") { payload.overrides[aid] = 1; }
            else if (val === "deny") { payload.overrides[aid] = 0; }
        });

        $.ajax({
            url: "ajax/users/users_overrides_save_ajax.php",
            type: "POST",
            data: payload,
            dataType: "json",
            success: function (res) {
                if (res && res.status === "success") {
                    Swal.fire({ icon: "success", title: "Saved", text: res.message || "Permissions updated." });
                } else {
                    Swal.fire({ icon: "error", title: "Error", text: (res && res.message) || "Could not save." });
                }
            },
            error: function (xhr) {
                var msg = "Request failed.";
                if (xhr.status === 403) { msg = "You don't have permission to do that."; }
                else if (xhr.status === 419) { msg = "Session/CSRF expired — reload the page."; }
                Swal.fire({ icon: "error", title: "Error", text: msg });
            }
        });
    });
});
