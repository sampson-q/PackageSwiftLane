
<!----Footer--->
<footer class="footer text-center py-3">
    &copy <?php echo date('Y') . ' ' . $core->site_name; ?> - <?php echo $lang['foot'] ?>
</footer>
<!----Footer End--->

<script src="assets/template/assets/libs/jquery/dist/jquery.min.js"></script>
<script>
    (function ($) {
        if (!$ || !$.ajaxSetup) return;

        function csrfToken() {
            return $('meta[name="csrf-token"]').attr('content') || '';
        }

        function csrfParam() {
            return $('meta[name="csrf-param"]').attr('content') || '_csrf_token';
        }

        $.ajaxSetup({
            beforeSend: function (xhr, settings) {
                var method = (((settings || {}).type) || 'GET').toUpperCase();
                if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) return;

                var token = csrfToken();
                if (!token) return;

                xhr.setRequestHeader('X-CSRF-Token', token);

                if (settings && settings.data && typeof FormData !== 'undefined' && settings.data instanceof FormData) {
                    settings.data.append(csrfParam(), token);
                }
            }
        });
    })(window.jQuery);
</script>

<script>
    // Rows-per-page: append the chosen #per_page value to every list-load AJAX
    // request (those whose object `data` contains a `page` key). jQuery serializes
    // object data to a query string BEFORE prefilters run, so appending to
    // options.data here is safe. No-ops when the page has no #per_page dropdown
    // or when per_page was already sent explicitly (e.g. courier/warehouse).
    (function ($) {
        if (!$ || !$.ajaxPrefilter) return;
        $.ajaxPrefilter(function (options, originalOptions) {
            var od = originalOptions && originalOptions.data;
            if (!od || typeof od !== 'object' || !('page' in od)) return;
            if (/(?:^|&)per_page=/.test(options.data || '')) return;
            var $pp = $('#per_page');
            if (!$pp.length) return;
            options.data = (options.data ? options.data + '&' : '') +
                'per_page=' + encodeURIComponent($pp.val() || 25);
        });
    })(window.jQuery);
</script>

<script>
    // Rows-per-page: inject a 25/50/100/All dropdown into the filter row of every
    // paginated list page that doesn't already ship a static one (warehouse,
    // courier_list and dashboard shipments do). Anchored next to #search so it
    // lands on the same row as the other filters.
    (function ($) {
        if (!$) return;
        $(function () {
            if (typeof cdp_load !== 'function') return;   // paginated list pages only
            if ($('#per_page').length) return;            // already has a dropdown
            var $search = $('#search');
            if (!$search.length) return;                  // no filter row to attach to
            var $anchor = $search.closest('[class*="col-"]');
            if (!$anchor.length) $anchor = $search.closest('.input-group');
            if (!$anchor.length) return;
            $anchor.after(
                '<div class="col-sm-12 col-md-2 mb-2"><div class="input-group">' +
                '<select onchange="cdp_load(1);" class="form-control custom-select" id="per_page" name="per_page">' +
                '<option value="25">25 rows</option>' +
                '<option value="50">50 rows</option>' +
                '<option value="100">100 rows</option>' +
                '<option value="all">All</option>' +
                '</select></div></div>'
            );
        });
    })(window.jQuery);
</script>

<!-- Bootstrap tether Core JavaScript -->
<script src="assets/template/assets/libs/popper.js/dist/umd/popper.min.js"></script>
<script src="assets/template/assets/libs/bootstrap/dist/js/bootstrap.min.js"></script>
<!-- apps -->
<script src="assets/template/dist/js/app.min.js"></script>
<script src="assets/template/dist/js/app.init.js"></script>
<script src="assets/template/dist/js/app-style-switcher.js"></script>
<!-- slimscrollbar scrollbar JavaScript -->
<script src="assets/template/assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
<script src="assets/template/assets/extra-libs/sparkline/sparkline.js"></script>
<!--Wave Effects -->
<script src="assets/template/dist/js/waves.js"></script>
<!--Menu sidebar -->
<script src="assets/template/dist/js/sidebarmenu.js"></script> 
<!--Custom JavaScript -->
<script src="assets/template/dist/js/feather.min.js"></script>
<script src="assets/template/dist/js/custom.min.js"></script>

<script src="assets/template/assets/extra-libs/chart.js-2.8/Chart.min.js"></script>
<script src="dataJs/load_notifications_all.js"> </script>
<script src="assets/template/dist/js/global.js"></script>

<!-- start - This is for export functionality only -->
<!-- solar icons -->
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>
<script src="assets/template/assets/libs/sweetalert2/sweetalert2.all.min.js"></script>
<script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>

<script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

<?php include 'views/modals/modal_user_update_address.php'; ?>
<?php include 'views/modals/modal_user_update_phone.php'; ?>
<?php include 'views/modals/modal_phone_update_otp.php'; ?>
<?php include 'views/modals/modal_user_update_document.php'; ?>

<?php /* Forced account setup (WhatsApp number verification etc.) — customers only */ ?>
<?php if (isset($_SESSION['userlevel']) && (int)$_SESSION['userlevel'] === 1): ?>
<script src="dataJs/check_user_update.js"></script>
<?php endif; ?>

<style>
    .swal2-container {
    z-index: 99999 !important;
}

.swal2-backdrop-show {
    background: rgba(0, 0, 0, 0.6) !important;
}
</style>
