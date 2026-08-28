/* ==========================================================================
   Barcode-scanner Enter guard  —  shared across every #invoice_form.

   That id is reused by ~21 data-entry forms, not just the courier ones:
     courier/       add, edit, add_multiple, add_client, accept,
                    shipment_tracking, deliver_shipment
     customer_packages/  add, add_multiple, add_from_prealert, multiple,
                    edit, tracking, deliver
     consolidate/ + consolidate_packages/   add, edit (both dirs)
     pickup/        add, add_full, accept
   All of them are multi-field forms where Enter-to-submit is unwanted, so the
   single delegated handler below is the intended blast radius — but it IS the
   whole set, not the five courier screens the bug was first reported on.

   Hardware barcode scanners act as a "keyboard wedge": they type the scanned
   payload character-by-character and then emit an Enter (carriage return) to
   signal end-of-scan. In an HTML form, that trailing Enter triggers the
   browser's implicit "submit on Enter" behaviour. On the shipment forms that
   fired the #invoice_form submit handler, whose first check is the package
   description — so every scan into the "# Tracking" (or any) field bounced back
   with the "Enter package description" error before the row was even filled in.

   Fix: on these data-entry forms, Enter inside a single-line <input> should
   NEVER submit. A scan just fills the field; submission stays deliberate, via
   the "Create shipment" button. This also prevents accidental half-filled
   submissions when a human hits Enter out of habit.

   Deliberately narrow:
     - Only #invoice_form — login, OTP and list search boxes keep their normal
       Enter-to-submit / Enter-to-search.
     - <textarea> is untouched (Enter = newline there, never a submit).
     - The submit/other buttons still work via Enter (we don't block them), so
       keyboard-only operators can still submit.
     - Select2 search fields live in a body-level dropdown container, not inside
       the form, so their Enter-to-pick is unaffected; the closest() check is a
       belt-and-braces guard in case a theme renders them inline.
   ========================================================================== */
(function ($) {
    if (!$) return;

    $(document).on("keydown.scannerGuard", "#invoice_form input", function (event) {
        var isEnter = event.key === "Enter" || event.keyCode === 13 || event.which === 13;
        if (!isEnter) return;

        var type = (this.type || "").toLowerCase();
        // Let Enter activate actual buttons (submit happens on purpose then).
        if (type === "submit" || type === "button" || type === "reset" || type === "image") return;
        // Don't interfere with Select2's own search input if a theme inlines it.
        if ($(this).closest(".select2-container, .select2-dropdown").length) return;

        event.preventDefault();
    });
})(window.jQuery);
