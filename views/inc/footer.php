
<!----Footer--->
<footer class="footer text-center py-3">
    &copy <?php echo date('Y') . ' ' . $core->site_name; ?> - <?php echo $lang['foot'] ?>
</footer>
<!----Footer End--->

<script src="assets/template/assets/libs/jquery/dist/jquery.min.js"></script>

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
<script src="assets/template/assets/libs/select2/dist/js/select2.min.js"></script>

<script src="dataJs/check_user_update.js"></script>
<script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>

<?php include 'views/modals/modal_user_update_address.php'; ?>
<?php include 'views/modals/modal_user_update_phone.php'; ?>
<?php include 'views/modals/modal_phone_update_otp.php'; ?>

<style>
    .swal2-container {
    z-index: 99999 !important;
}

.swal2-backdrop-show {
    background: rgba(0, 0, 0, 0.6) !important;
}
</style>