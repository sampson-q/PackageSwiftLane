"use strict";

$(function() {
    // safe fallbacks for translation variables
    if (typeof search_consolidation === 'undefined') {
        var search_consolidation = 'Search consolidation...';
    }

    // initialize Select2 for users & consolidations
    loadInvoiceRows();
    cdp_select_init_user();
    cdp_select_init_consolidation();

    // hide user and consolidation containers initially
    $('#single-user-container').hide();
    $('#consolidation-container').hide();
    $('#invoice-container').hide();

    // Combined radio change handler — listens for any radio with name="notification_type"
    $(document).on('change', 'input[name="notification_type"]', function () {
        var val = $(this).val();

        if (val === 'single_user') {
            $('#single-user-container').show();
            $('#consolidation-container').hide();
            $('#invoice-container').hide();

            $('#subject').closest('.form-group').show();
            $('#message').closest('.form-group').show();
            $('#send_notification').show();

        } else if (val === 'consolidation') {
            $('#single-user-container').hide();
            $('#consolidation-container').show();
            $('#invoice-container').hide();

            $('#subject').closest('.form-group').show();
            $('#message').closest('.form-group').show();
            $('#send_notification').show();

        } else if (val === 'invoice') {
            // show invoice form, hide default message subject/message/send button
            $('#single-user-container').hide();
            $('#consolidation-container').hide();
            $('#invoice-container').show();

            $('#subject').closest('.form-group').hide();
            $('#message').closest('.form-group').hide();
            $('#send_notification').hide();

        } else { // broadcast
            $('#single-user-container').hide();
            $('#consolidation-container').hide();
            $('#invoice-container').hide();

            $('#subject').closest('.form-group').show();
            $('#message').closest('.form-group').show();
            $('#send_notification').show();
        }
    });
});

var packagesCacheByUser = {};
var allPackages = [];

function cdp_select_init_user() {
    $("#user_id").select2({
        ajax: {
            url: "ajax/select2_user.php",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true,
        },
        minimumInputLength: 2,
        placeholder: typeof search_user !== 'undefined' ? search_user : 'Search user...',
        allowClear: true,
    }).on("change", function (e) {
        var data = $(this).select2('data');
        if (data && data.length) $("#uid").val(data[0].id);
        else $("#uid").val('');
    });
}

function cdp_select_init_consolidation() {
    $("#consolidation_id").select2({
        ajax: {
            url: "ajax/select2_consolidation.php",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true,
        },
        minimumInputLength: 2,
        placeholder: typeof search_consolidation !== 'undefined' ? search_consolidation : 'Search consolidation...',
        allowClear: true,
    }).on("change", function (e) {
        var data = $(this).select2('data');
        if (data && data.length) $("#cid").val(data[0].id);
        else $("#cid").val('');
    });
}


// Submit handler for broadcast/single/consolidation
$("#push_notification_form").on("submit", function (event) {
    event.preventDefault();

    var notifType = $('input[name="notification_type"]:checked').val();
    var subject = $.trim($("#subject").val());
    var message = $.trim($("#message").val());

    if (!notifType) {
        Swal.fire({ title: message_error || 'Error', html: 'Please select a notification type.', icon: "error", confirmButtonText: "Ok" });
        return;
    }
    if (!subject) {
        Swal.fire({ title: message_error || 'Error', html: 'Subject is required.', icon: "error", confirmButtonText: "Ok" });
        return;
    }
    if (!message) {
        Swal.fire({ title: message_error || 'Error', html: 'Message is required.', icon: "error", confirmButtonText: "Ok" });
        return;
    }

    var data = new FormData();
    data.append('notification_type', notifType);
    data.append('subject', subject);
    data.append('message', message);

    if (notifType === 'single_user') {
        var selectedUser = $('#user_id').val() || $('#uid').val();
        if (!selectedUser) {
            Swal.fire({ title: message_error || 'Error', html: 'Please choose a user for "Single user" notifications.', icon: "error", confirmButtonText: "Ok" });
            return;
        }
        data.append('user_id', selectedUser);
    } else if (notifType === 'consolidation') {
        var selectedCon = $('#consolidation_id').val() || $('#cid').val();
        if (!selectedCon) {
            Swal.fire({ title: message_error || 'Error', html: 'Please choose a consolidation for "Consolidation" notifications.', icon: "error", confirmButtonText: "Ok" });
            return;
        }
        data.append('consolidation_id', selectedCon);
    }

    $.ajax({
        type: "POST",
        url: "ajax/tools/push_notifications_ajax.php",
        data: data,
        contentType: false,
        dataType: "json",
        cache: false,
        processData: false,
        beforeSend: function () {
            $("#send_notification").attr("disabled", true);
            // Swal.fire({ title: message_loading || 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        },
        success: function (response) {
            $("#send_notification").attr("disabled", false);
            if (response.success === true) cdp_showSuccess();
            else {
                if (response.errors) cdp_showError(response.errors);
                else cdp_showError({ general: 'Unknown error' });
            }

            $('#push_notification_form')[0].reset();
            $('#single-user-container').hide();
            $('#consolidation-container').hide();

            if ($('#user_id').length) $('#user_id').val(null).trigger('change');
            if ($('#consolidation_id').length) $('#consolidation_id').val(null).trigger('change');

            $("#uid").val('');
            $("#cid").val('');
        },
        error: function (xhr, status, err) {
            $("#send_notification").attr("disabled", false);
            Swal.fire({ title: message_error || 'Error', html: 'AJAX error: ' + status, icon: "error", confirmButtonText: "Ok" });
        }
    });
});

function cdp_showSuccess() {
  Swal.fire({ title: 'Push Notifications Sent', icon: "success", allowOutsideClick: false, confirmButtonText: "Ok" })
}

function cdp_showError(errors) {
  var html_code = '<ul class="error">';
  if (Array.isArray(errors)) {
      errors.forEach(function (err) { html_code += '<li class="text-left"><i class="icon-double-angle-right"></i>' + err + '</li>'; });
  } else {
      for (var key in errors) {
        if (!errors.hasOwnProperty(key)) continue;
        html_code += '<li class="text-left"><i class="icon-double-angle-right"></i>' + errors[key] + '</li>';
      }
  }
  html_code += '</ul>';
  Swal.fire({ title: message_error || 'Error', html: html_code, icon: "error", allowOutsideClick: false, confirmButtonText: "Ok" });
}

/* ---------------------------
   Dynamic invoice rows: per-row sender -> package select, dedupe packages
   Each row may select *multiple* packages (order_id becomes array).
   --------------------------- */

var invoiceRows = [{
    sender_id: 0,           // integer (user id)
    order_id: [],           // array of order_ids (strings)
    order_text: [],         // array of display texts for selected orders
    amount: ""              // string/decimal (applies to each selected order in that row)
}];

// track currently selected package IDs (order_ids) to exclude from other rows
var selectedOrderIds = new Set();

// utility to generate unique ids for dynamic elements
function _uniq(prefix) {
    return prefix + '_' + Math.random().toString(36).substr(2, 9);
}

/* render all rows */
function loadInvoiceRows() {
    // ensure selectedOrderIds matches invoiceRows before any fetches
    rebuildSelectedOrderIdsFromModel();

    $("#data_items").html("");
    invoiceRows.forEach(function (item, index) {
        // normalize to arrays (backwards compat)
        if (!Array.isArray(invoiceRows[index].order_id)) {
            invoiceRows[index].order_id = invoiceRows[index].order_id && invoiceRows[index].order_id !== 0 ? [String(invoiceRows[index].order_id)] : [];
        }
        if (!Array.isArray(invoiceRows[index].order_text)) {
            invoiceRows[index].order_text = invoiceRows[index].order_text ? [String(invoiceRows[index].order_text)] : [];
        }

        var rowDomId = 'row_id_' + index;
        var senderId = 'sender_id_' + index;
        var packageId = 'order_id_' + index;
        var amountId = 'amount_' + index;
        var hiddenSenderInputId = 'sender_input_' + index;

        var html = '';
        html += '<div class="card-hover" id="' + rowDomId + '"><hr><div class="row">';

        html += '<div class="col-sm-12 col-md-6 col-lg-5"><div class="form-group"><label for="sender"> ' + translate_sender + '</label><div class="input-group">' +
            '<select class="form-control input-sm dynamic-sender" id="' + senderId + '" name="sender_id"></select>' +
            '</div></div></div>';

        // hidden input that carries the selected sender/user id for this row
        html += '<input type="hidden" class="row-sender-input" id="' + hiddenSenderInputId + '" value="' + (item.sender_id || '') + '">';

        // NOTE: package select is MULTI
        html += '<div class="col-sm-12 col-md-6 col-lg-4"><div class="form-group"><label for="package"> ' + translate_sender_package + '</label><div class="input-group">' +
            '<select multiple class="form-control input-sm dynamic-order" id="' + packageId + '" name="order_id" disabled></select>' +
            '</div></div></div>';

        html += '<div class="col-sm-12 col-md-6 col-lg-2"><div class="form-group"><label for="amount"> ' + translate_amount + '</label><div class="input-group">' +
            '<input type="text" id="' + amountId + '" name="amount" class="form-control input-sm number_only amount-input" value="' + (item.amount || '') + '" />' +
            '</div></div></div>';

        if (index > 0) {
            html += '<div class="col-sm-12 col-md-6 col-lg-1"><div class="form-group mt-4">' +
                '<button type="button" class="btn btn-outline-danger remove-row" data-index="' + index + '"><i class="fa fa-trash"></i></button>' +
                '</div></div>';
        }

        html += '</div><hr></div>';

        $("#data_items").append(html);
    });

    // initialize select2 for each dynamic row (sender and package selects)
    invoiceRows.forEach(function (item, index) {
        initDynamicSender('#sender_id_' + index, index);
        // package select is initialized but disabled until sender selected
        initDynamicOrderSelect('#order_id_' + index, index);
        // wire inputs to update invoiceRows
        $('#amount_' + index).off('change keyup').on('change keyup', function () { invoiceRows[index].amount = $(this).val(); });
    });

    // remove-row button
    $('.remove-row').off('click').on('click', function () {
        var ix = parseInt($(this).data('index'), 10);
        deleteInvoiceRow(ix);
    });
}

/* deleteInvoiceRow: ensure we free selectedOrderIds and reindex state */
function deleteInvoiceRow(index) {
    // animate then remove to mimic courier-ui behaviour
    var rowSelector = '#row_id_' + index;
    if ($(rowSelector).length) {
        $(rowSelector).animate({ backgroundColor: "#FFBFBF" }, 400).fadeOut(400, function () {
            // remove model entry and rebuild state then re-render
            var removed = invoiceRows[index];
            if (removed) {
                // handle array of selected order ids
                if (Array.isArray(removed.order_id)) {
                    removed.order_id.forEach(function(id){ if (id) selectedOrderIds.delete(String(id)); });
                } else if (removed.order_id) {
                    selectedOrderIds.delete(String(removed.order_id));
                }
            }
            invoiceRows.splice(index, 1);
            rebuildSelectedOrderIdsFromModel();
            loadInvoiceRows();
        });
    } else {
        // fallback if element not present
        var removed = invoiceRows[index];
        if (removed) {
            if (Array.isArray(removed.order_id)) {
                removed.order_id.forEach(function(id){ if (id) selectedOrderIds.delete(String(id)); });
            } else if (removed.order_id) {
                selectedOrderIds.delete(String(removed.order_id));
            }
        }
        invoiceRows.splice(index, 1);
        rebuildSelectedOrderIdsFromModel();
        loadInvoiceRows();
    }
}

function initDynamicSender(selector, index) {
    var $sel = $(selector);
    // prevent double init
    if ($sel.data('select2')) {
        $sel.select2('destroy');
    }

    // if we already have a sender stored in the model, add the option so Select2 can show it
    if (invoiceRows[index] && invoiceRows[index].sender_id && invoiceRows[index].sender_text) {
        try {
            $sel.append(new Option(invoiceRows[index].sender_text, invoiceRows[index].sender_id, true, true));
        } catch (err) {
            // ignore
        }
    }

    $sel.select2({
        ajax: {
            url: 'ajax/select2_user.php',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: typeof search_sender !== 'undefined' ? search_sender : 'Search sender/customer',
        allowClear: true
    }).off('change').on('change', function () {
        var userId = $(this).val() ? parseInt($(this).val(), 10) : 0;
        invoiceRows[index].sender_id = userId;
        // record display text when possible
        var selData = $(this).select2('data');
        if (selData && selData.length) {
            invoiceRows[index].sender_text = selData[0].text || selData[0].username || selData[0].name || String(userId);
        } else {
            invoiceRows[index].sender_text = '';
        }

        // update the hidden input that carries the selected user's id for this row
        $('#sender_input_' + index).val(invoiceRows[index].sender_id || '');

        // reset package selection for this row and enable/disable package select accordingly
        $('#order_id_' + index).val(null).trigger('change');
        // clear stored order text/id for this row (sender changed)
        invoiceRows[index].order_id = [];
        invoiceRows[index].order_text = [];
        if (userId) {
            $('#order_id_' + index).prop('disabled', false);
            // reinit package select (ensures we use the cached packages when present)
            initDynamicOrderSelect('#order_id_' + index, index);
        } else {
            $('#order_id_' + index).prop('disabled', true);
        }
    });
}

/**
 * initDynamicOrderSelect(selector, index)
 * Populates a plain <select multiple> with orders for selected sender (sender_id).
 * Uses cached packages from fetchInvoicePackages() if available; falls back to ajax/select2_user_orders.php.
 */
function initDynamicOrderSelect(selector, index) {
    var $sel = $(selector);

    // ensure model uses arrays
    if (!Array.isArray(invoiceRows[index].order_id)) invoiceRows[index].order_id = invoiceRows[index].order_id && invoiceRows[index].order_id !== 0 ? [String(invoiceRows[index].order_id)] : [];
    if (!Array.isArray(invoiceRows[index].order_text)) invoiceRows[index].order_text = invoiceRows[index].order_text ? [String(invoiceRows[index].order_text)] : [];

    // save current selection (if any) - array of strings
    var curValArr = invoiceRows[index].order_id && invoiceRows[index].order_id.length ? invoiceRows[index].order_id.map(String) : null;
    var curTextArr = invoiceRows[index].order_text && invoiceRows[index].order_text.length ? invoiceRows[index].order_text : null;

    // show a clean loading placeholder and disable while fetching
    populateOrderSelectOptions($sel, [], curValArr, 'Loading packages...');
    $sel.prop('disabled', true);

    var userSenderId = $('#sender_id_' + index).val() ? parseInt($('#sender_id_' + index).val(), 10) : 0;
    if (!userSenderId) {
        // no sender -> show instructive placeholder
        populateOrderSelectOptions($sel, [], curValArr, 'First, select Sender/Customer');
        $sel.prop('disabled', true);
        console.log('initDynamicOrderSelect: no sender selected for row', index);
        return;
    }

    // prepare exclude list (selectedOrderIds is a Set of string ids)
    var excludeArr = Array.from(selectedOrderIds);
    // allow the currently selected ids in this row to remain available in the server result
    if (curValArr && curValArr.length) excludeArr = excludeArr.filter(function (v) { return curValArr.indexOf(v) === -1; });
    var excludeCSV = excludeArr.join(',');

    // If we have cached packages for users, use them
    if (packagesCacheByUser && packagesCacheByUser[userSenderId] && packagesCacheByUser[userSenderId].length) {
        // Filter cached list by excludeCSV
        var cached = packagesCacheByUser[userSenderId].filter(function(it){
            return excludeArr.indexOf(String(it.order_id)) === -1;
        }).map(function(it){
            return { id: it.order_id, text: (it.tracking ? it.tracking : String(it.order_id)) + (it.postal_tracking ? ' — ' + it.postal_tracking : ''), raw: it };
        });

        // If current values exist but are not in cached list, ensure they remain visible
        if (curValArr && curValArr.length) {
            curValArr.forEach(function(cv, idx){
                var found = cached.filter(function(c){ return String(c.id) === String(cv); }).length > 0;
                if (!found) {
                    var label = (curTextArr && curTextArr[idx]) ? curTextArr[idx] : cv;
                    cached.push({ id: cv, text: label });
                }
            });
        }

        populateOrderSelectOptions($sel, cached, curValArr, 'Select package...');
        $sel.prop('disabled', false);

        // wire change handler for multi-select
        $sel.off('change.invoice').on('change.invoice', function () {
            var vals = $(this).val(); // could be null, string, or array
            var newVals = [];
            if (!vals) newVals = [];
            else if (Array.isArray(vals)) newVals = vals.map(String);
            else newVals = [String(vals)];

            // free previously selected ids for this row (handle arrays)
            var prev = invoiceRows[index].order_id && Array.isArray(invoiceRows[index].order_id) ? invoiceRows[index].order_id : (invoiceRows[index].order_id ? [String(invoiceRows[index].order_id)] : []);
            prev.forEach(function(p){ if (p) selectedOrderIds.delete(String(p)); });

            if (newVals && newVals.length) {
                invoiceRows[index].order_id = newVals;
                // collect display texts from selected options
                var texts = newVals.map(function(v){
                    var opt = $(this).find('option[value="' + v + '"]');
                    return opt.length ? opt.text() : String(v);
                }.bind(this));
                invoiceRows[index].order_text = texts;
                newVals.forEach(function(v){ selectedOrderIds.add(String(v)); });
            } else {
                invoiceRows[index].order_id = [];
                invoiceRows[index].order_text = [];
            }

            // refresh others so they exclude the updated set
            refreshPackageSelects();
        });

        return;
    }

    // fallback: call old select2_user_orders endpoint (keeps backwards compatibility)
    console.log('Fetching orders (fallback) for sender', userSenderId, 'exclude=', excludeCSV, 'row=', index);
    $.ajax({
        url: 'ajax/select2_user_orders.php',
        dataType: 'json',
        data: {
            sender_id: userSenderId,
            exclude: excludeCSV,
            ship_from: $('#shipping_date_from').val() || '',
            ship_to:   $('#shipping_date_to').val() || '',
            q: '' // fetch full list
        },
        success: function (data) {
            // transform data to expected shape and populate
            var items = (data && data.length) ? data.map(function(d){ return { id: d.id, text: d.text, tracking: d.tracking, postal_tracking: d.postal_tracking }; }) : [];
            populateOrderSelectOptions($sel, items, curValArr, 'Select package...');
            $sel.prop('disabled', false);

            $sel.off('change.invoice').on('change.invoice', function () {
                var vals = $(this).val();
                var newVals = [];
                if (!vals) newVals = [];
                else if (Array.isArray(vals)) newVals = vals.map(String);
                else newVals = [String(vals)];

                var prev = invoiceRows[index].order_id && Array.isArray(invoiceRows[index].order_id) ? invoiceRows[index].order_id : (invoiceRows[index].order_id ? [String(invoiceRows[index].order_id)] : []);
                prev.forEach(function(p){ if (p) selectedOrderIds.delete(String(p)); });

                if (newVals && newVals.length) {
                    invoiceRows[index].order_id = newVals;
                    var texts = newVals.map(function(v){
                        var opt = $(this).find('option[value="' + v + '"]');
                        return opt.length ? opt.text() : String(v);
                    }.bind(this));
                    invoiceRows[index].order_text = texts;
                    newVals.forEach(function(v){ selectedOrderIds.add(String(v)); });
                } else {
                    invoiceRows[index].order_id = [];
                    invoiceRows[index].order_text = [];
                }

                refreshPackageSelects();
            });
        },
        error: function (xhr, status, err) {
            console.error('Failed to load orders for sender:', status, err, xhr.responseText);
            populateOrderSelectOptions($sel, [], curValArr, 'Error loading packages');
            $sel.prop('disabled', true);
        }
    });
}


/**
 * refreshPackageSelects()
 * Rebuilds all order selects across invoiceRows preserving existing selections
 * and excluding the rest of the selectedOrderIds.
 */
function refreshPackageSelects() {
    invoiceRows.forEach(function (item, idx) {
        // only attempt to refresh if the row's sender is set
        if ($('#sender_id_' + idx).length && $('#sender_id_' + idx).val()) {
            initDynamicOrderSelect('#order_id_' + idx, idx);
        } else {
            // if no sender selected, ensure the select is empty & disabled
            var $sel = $('#order_id_' + idx);
            if ($sel.length) {
                populateOrderSelectOptions($sel, [], null);
                $sel.prop('disabled', true);
            }
        }
    });
}

/* collect items for send_invoices call (server expects items: [{sender_id, order_ids, amount}, ...]) */
$(document).on('click', '#send_invoice_notifications', function () {
    var shipment_from = $('#shipping_date_from').val();
    var shipment_end = $('#shipping_date_to').val();
    var pickup_date = $('#shipment_pickup_date').val();

    if (!shipment_from || !shipment_end || !pickup_date) {
        Swal.fire({ title: message_error || 'Error', html: 'Please set shipment start/end dates and pickup date.', icon: "error", confirmButtonText: "Ok" });
        return;
    }

    var items = [];
    var invalid = false;
    var validationErrors = [];

    invoiceRows.forEach(function (it, idx) {
        var amount = (it.amount || '').toString().trim();
        // normalize to array of strings (the model stores strings)
        var orderIds = Array.isArray(it.order_id) ? it.order_id.map(String) : (it.order_id ? [String(it.order_id)] : []);
        // remove falsy/empty
        orderIds = orderIds.filter(function(v){ return v && v !== ''; });

        // ensure sender_id present for this row
        var senderId = it.sender_id ? parseInt(it.sender_id, 10) : 0;

        if (orderIds.length) {
            if (amount === '' || isNaN(amount)) {
                validationErrors.push("Row " + (idx + 1) + ": invalid amount.");
                invalid = true;
                return;
            }
            if (!senderId) {
                validationErrors.push("Row " + (idx + 1) + ": select a sender/customer for this row.");
                invalid = true;
                return;
            }

            // push single group object per row (server will use sender_id to group and aggregate)
            items.push({
                sender_id: senderId,
                order_ids: orderIds.map(function(v){ return parseInt(v, 10); }),
                amount: (parseFloat(amount)).toFixed(2)
            });
        }
    });

    if (validationErrors.length) {
        Swal.fire({ title: message_error || 'Error', html: validationErrors.join('<br>'), icon: "error", confirmButtonText: "Ok" });
        return;
    }

    if (invalid || !items.length) {
        Swal.fire({ title: message_error || 'Error', html: 'Please select at least one package and enter a valid amount for each row.', icon: "error", confirmButtonText: "Ok" });
        return;
    }

    // Build FormData matching PHP: include shipment_from/shipment_end/pickup_date + items
    var form = new FormData();
    form.append('action', 'send_invoices');
    form.append('shipment_from', shipment_from);
    form.append('shipment_end', shipment_end);
    form.append('pickup_date', pickup_date);
    form.append('items', JSON.stringify(items));

    $.ajax({
        url: 'ajax/tools/push_notifications_invoice_ajax.php',
        type: 'POST',
        dataType: 'json',
        data: form,
        contentType: false,
        processData: false,
        cache: false,
        beforeSend: function () {
            $('#send_invoice_notifications').attr('disabled', true);
            Swal.fire({ title: message_loading || 'Sending...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        },
        success: function (resp) {
            $('#send_invoice_notifications').attr('disabled', false);
            Swal.close();
            if (resp.success) {
                cdp_showSuccess();
                // reset UI
                invoiceRows = [{
                    sender_id: 0, order_id: [], order_text: [], amount: ""
                }];
                selectedOrderIds.clear();
                packagesCacheByUser = {};
                allPackages = [];
                loadInvoiceRows();
            } else {
                cdp_showError(resp.errors || ['Failed sending notifications.']);
            }
        },
        error: function (xhr, status, err) {
            $('#send_invoice_notifications').attr('disabled', false);
            Swal.fire({ title: message_error || 'Error', html: 'AJAX error: ' + status, icon: "error", confirmButtonText: "Ok" });
        }
    });
});


/**
 * populateOrderSelectOptions
 * - $select: jQuery object of the <select> element
 * - data: array returned by server [{id, text, ...}, ...]
 * - currentVal: value or array of values to keep selected (optional)
 * - placeholderText: text for the empty/default option
 */
function populateOrderSelectOptions($select, data, currentVal, placeholderText) {
    placeholderText = typeof placeholderText !== 'undefined' ? placeholderText : 'Select package...';

    $select.empty();
    // default empty option with visible placeholder text
    // Note: for multi-select, this first option will still be shown as placeholder in many browsers.
    $select.append($('<option>', { value: '', text: placeholderText }));

    // normalize currentVal to array for comparisons
    var curArr = null;
    if (currentVal) {
        if (Array.isArray(currentVal)) curArr = currentVal.map(String);
        else curArr = [String(currentVal)];
    }

    if (!data || !data.length) {
        // nothing else to add
        if (curArr && curArr.length) {
            // keep the previously selected values (if any) visible
            curArr.forEach(function(cv){
                $select.append($('<option>', { value: cv, text: cv, selected: true }));
            });
        }
        return;
    }

    data.forEach(function (row) {
        // row.id = order_id, row.text = "PREFIXNUMBER — postal" or "PREFIXNUMBER"
        var opt = $('<option>', { value: row.id, text: row.text });
        if (curArr && curArr.indexOf(String(row.id)) !== -1) opt.prop('selected', true);
        $select.append(opt);
    });
}


/* addPackage: push empty object and re-render */
function addPackage() {
    invoiceRows.push({
        sender_id: 0,
        order_id: [],
        order_text: [],
        amount: ""
    });
    loadInvoiceRows();
    // animate last row highlight
    var index = invoiceRows.length - 1;
    $('#row_id_' + index).animate({ backgroundColor: "#18BC9C" }, 400).delay(700).queue(function(next){
        $(this).css('background-color','');
        next();
    });
}

function rebuildSelectedOrderIdsFromModel() {
    selectedOrderIds.clear();
    invoiceRows.forEach(function(it) {
        if (!it) return;
        if (Array.isArray(it.order_id)) {
            it.order_id.forEach(function(id){
                if (id) selectedOrderIds.add(String(id));
            });
        } else if (it.order_id) {
            selectedOrderIds.add(String(it.order_id));
        }
    });
}

$('#shipping_date_from, #shipping_date_to').on('change', function(){ refreshPackageSelects(); });
