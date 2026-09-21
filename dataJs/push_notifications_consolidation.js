"use strict";

$(function() {
    // fallbacks for translation variables
    if (typeof search_user === 'undefined') {
        var search_user = 'Search user...';
    }

    // UI initial state: hide user selector (avoid flash)
    $('#user-container').hide();

    // Show/hide user multi-select depending on radio
    $('input[name="notification_type"]').on('change', function() {
        if (this.value === 'selected_users') {
            $('#user-container').show();
        } else {
            $('#user-container').hide();
        }
    });

    // Initialize user select2 (search limited to consolidation via consolidation_id hidden input)
    init_user_select();
});


function init_user_select() {
    $("#user_id").select2({
        ajax: {
            url: "ajax/select2_user_consolidation.php",
            dataType: "json",
            delay: 250,
            data: function (params) {
                // pass consolidation id to the server so server restricts the search
                var cid = $('#consolidation_id').val() || $('#cid').val() || '';
                return {
                    q: params.term,
                    consolidation_id: cid
                };
            },
            processResults: function (data) {
                // Remove items that are already selected to avoid duplicate selection
                var selected = $('#user_id').val() || [];
                // Normalize selected to strings for safe comparison
                var selectedSet = {};
                for (var i = 0; i < selected.length; i++) {
                    selectedSet[String(selected[i])] = true;
                }

                var filtered = [];
                for (var j = 0; j < data.length; j++) {
                    var item = data[j];
                    // item.id might be int or string; cast to string
                    if (!selectedSet[String(item.id)]) {
                        filtered.push(item);
                    }
                }

                return { results: filtered };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: typeof search_user !== 'undefined' ? search_user : 'Search user...',
        allowClear: true,
        multiple: true
    });
}


// Form submit
$("#push_notification_form").on("submit", function (event) {
    event.preventDefault();

    var notifType = $('input[name="notification_type"]:checked').val();
    var subject = $.trim($("#subject").val());
    var message = $.trim($("#message").val());
    var consolidation_id = $('#consolidation_id').val() || $('#cid').val();

    // basic validation
    if (!consolidation_id) {
        Swal.fire({ title: message_error, html: 'Consolidation not found. Reload the page.', icon: "error", confirmButtonText: "OK" });
        return;
    }
    if (!subject) {
        Swal.fire({ title: message_error, html: 'Subject is required.', icon: "error", confirmButtonText: "OK" });
        return;
    }
    if (!message) {
        Swal.fire({ title: message_error, html: 'Message is required.', icon: "error", confirmButtonText: "OK" });
        return;
    }

    var channels = $('input[name="channels"]:checked').val() || 'both';
    var selectedUsers = [];

    if (notifType === 'selected_users') {
        selectedUsers = $('#user_id').val() || []; // selected user ids (these are sender ids)
        if (selectedUsers.length === 0) {
            Swal.fire({ title: message_error, html: 'Please choose one or more users from the consolidation.', icon: "error", confirmButtonText: "OK" });
            return;
        }
    } else if (notifType !== 'broadcast') {
        Swal.fire({ title: message_error, html: 'Unknown notification type.', icon: "error", confirmButtonText: "OK" });
        return;
    }

    // Read once: every chunk of the push carries exactly the same message.
    var buildData = function () {
        var data = new FormData();
        data.append('notification_type', notifType);
        data.append('channels', channels);
        data.append('subject', subject);
        data.append('message', message);
        data.append('consolidation_id', consolidation_id);
        selectedUsers.forEach(function (u) { data.append('sender_ids[]', u); });
        return data;
    };

    $("#send_notification").attr("disabled", true);
    cdp_pushRun("ajax/tools/push_notifications_consolidation_ajax.php", buildData, function () {
        $("#send_notification").attr("disabled", false);

        // reset UI (keep consolidation id in place)
        $('#push_notification_form')[0].reset();
        $('#user_id').val(null).trigger('change');
        $('#user-container').hide();
    });
});
