"use strict";
/* ===========================================================================
   Sender auto-fill.

   When a user SELECTS a sender on any shipment / pickup / package form, the rest
   of the cascade is auto-filled with the sender's FIRST details:
     - first sender address   (#sender_address_id)
     - first recipient        (#recipient_id)
     - first recipient address(#recipient_address_id)
   A sender often has several of each; we pick the first (index 0 of the same
   select2 endpoints the form already uses).

   Why one global handler instead of editing ~16 duplicated form scripts:
   every sender/recipient form shares the same field ids and select2 cascade, so
   a single delegated handler on `#sender_id` covers them all.

   Why `select2:select` (not `change`): it fires ONLY on a real user selection,
   so an EDIT form pre-selecting a sender programmatically (val + change) does NOT
   trigger auto-fill and never clobbers the values being loaded.

   Why the `setTimeout(…, 0)`: the form's own `#sender_id` change handler resets
   and re-initialises the child selects synchronously right after select2:select;
   deferring one tick lets that finish first, then we populate the children.
   =========================================================================== */
(function () {
    if (window.__cdpSenderAutofillLoaded) return;
    window.__cdpSenderAutofillLoaded = true;

    function firstRow(rows) { return (Array.isArray(rows) && rows.length) ? rows[0] : null; }

    // Append (if missing) + select an option on an ajax-backed select2, then
    // fire change so the form's dependent logic (rate auto-fetch, etc.) runs.
    function setFirst(sel, item) {
        var $s = $(sel);
        if (!$s.length || !item || item.id === undefined || item.id === null) return;
        if ($s.find("option[value='" + String(item.id).replace(/'/g, "\\'") + "']").length === 0) {
            $s.append(new Option(item.text || "", item.id, true, true));
        }
        $s.val(String(item.id)).trigger("change");
    }

    window.cdp_autofillSenderDefaults = function (senderId) {
        if (!senderId) return;

        // 1) First sender address.
        $.getJSON("ajax/select2_sender_addresses.php?id=" + encodeURIComponent(senderId))
            .done(function (rows) {
                var a = firstRow(rows);
                if (a) { $("#sender_address_id").prop("disabled", false); setFirst("#sender_address_id", a); }
            });

        // 2) First recipient, then 3) its first address.
        $.getJSON("ajax/select2_recipient.php?id=" + encodeURIComponent(senderId))
            .done(function (rows) {
                var rec = firstRow(rows);
                if (!rec) return;

                // Replicate the recipient select2:select side-effects (we set the
                // value programmatically, so that handler won't fire on its own).
                window.recipient_type = rec.type || "recipient";
                $("#recipient_id").prop("disabled", false);
                setFirst("#recipient_id", rec);
                $("#recipient_address_id, #add_address_recipient").prop("disabled", false);

                $.ajax({
                    url: "ajax/select2_recipient_addresses.php",
                    dataType: "json",
                    data: { id: rec.id, type: window.recipient_type, q: "" }
                }).done(function (arows) {
                    var ra = firstRow(arows);
                    if (ra) setFirst("#recipient_address_id", ra);
                });
            });
    };

    // A real user picked a sender -> defer one tick so the form's own change
    // handler has reset/re-initialised the child selects, then auto-fill.
    $(document).on("select2:select", "#sender_id", function () {
        var senderId = $(this).val();
        setTimeout(function () { window.cdp_autofillSenderDefaults(senderId); }, 0);
    });
})();
