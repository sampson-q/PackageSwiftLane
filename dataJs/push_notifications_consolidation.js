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
        Swal.fire({ title: message_error, html: 'Consolidation not found. Reload the page.', icon: "error", confirmButtonText: "Ok" });
        return;
    }
    if (!subject) {
        Swal.fire({ title: message_error, html: 'Subject is required.', icon: "error", confirmButtonText: "Ok" });
        return;
    }
    if (!message) {
        Swal.fire({ title: message_error, html: 'Message is required.', icon: "error", confirmButtonText: "Ok" });
        return;
    }

    var data = new FormData();
    data.append('notification_type', notifType);
    data.append('subject', subject);
    data.append('message', message);
    data.append('consolidation_id', consolidation_id);

    if (notifType === 'selected_users') {
        var selectedUsers = $('#user_id').val(); // array of selected user ids (these are sender ids)
        if (!selectedUsers || selectedUsers.length === 0) {
            Swal.fire({ title: message_error, html: 'Please choose one or more users from the consolidation.', icon: "error", confirmButtonText: "Ok" });
            return;
        }
        // append each sender id using the name server expects: sender_ids[]
        selectedUsers.forEach(function(u) {
            data.append('sender_ids[]', u);
        });
    } else if (notifType === 'broadcast') {
        // consolidation_id already appended, server will handle recipient discovery
    } else {
        Swal.fire({ title: message_error, html: 'Unknown notification type.', icon: "error", confirmButtonText: "Ok" });
        return;
    }

    $.ajax({
        type: "POST",
        url: "ajax/tools/push_notifications_consolidation_ajax.php",
        data: data,
        contentType: false,
        dataType: "json",
        cache: false,
        processData: false,
        beforeSend: function () {
            $("#send_notification").attr("disabled", true);
            Swal.fire({
                title: message_loading,
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
            });
        },
        success: function (response) {
            $("#send_notification").attr("disabled", false);

            if (response.success === true) {
                Swal.fire({ title: 'Notifications sent', html: 'Push notifications were sent successfully.', icon: "success", confirmButtonText: "Ok" });
            } else {
                if (response.errors) {
                    var html = '<ul class="error">';
                    if (Array.isArray(response.errors)) {
                        response.errors.forEach(function(e){ html += '<li>' + e + '</li>'; });
                    } else {
                        for (var k in response.errors) { if (!response.errors.hasOwnProperty(k)) continue; html += '<li>' + response.errors[k] + '</li>'; }
                    }
                    html += '</ul>';
                    Swal.fire({ title: message_error, html: html, icon: "error", confirmButtonText: "Ok" });
                } else {
                    Swal.fire({ title: message_error, html: 'Unknown error', icon: "error", confirmButtonText: "Ok" });
                }
            }

            // reset UI (keep consolidation id in place)
            $('#push_notification_form')[0].reset();
            $('#user_id').val(null).trigger('change');
            $('#user-container').hide();
        },
        error: function (xhr, status, err) {
            $("#send_notification").attr("disabled", false);
            Swal.fire({
                title: message_error,
                html: 'AJAX error: ' + status,
                icon: "error",
                confirmButtonText: "Ok",
            });
        }
    });
});
