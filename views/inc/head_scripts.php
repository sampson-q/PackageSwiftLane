<link href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
  href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
  rel="stylesheet" />
<!-- Icons: Solar (Iconify) + legacy -->
<script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
<style>
	iconify-icon{display:inline-flex;vertical-align:middle;line-height:1;}
	/* Sidebar: iconos más grandes y espacio con el nombre */
	.left-sidebar .sidebar-nav iconify-icon,
	.left-sidebar .sidebar-link iconify-icon,
	.left-sidebar .create-btn iconify-icon {
		font-size: 1.4rem !important;
		margin-right: 10px;
		min-width: 1.4rem;
	}
</style>
<link rel="stylesheet" href="assets/vendor/fonts/fontawesome.css" />
<link rel="stylesheet" href="assets/vendor/fonts/tabler-icons.css" />
<link rel="stylesheet" href="assets/vendor/fonts/flag-icons.css" />
<link rel="stylesheet" type="text/css" href="assets/template/dist/css/uicons-regular-rounded.css" />
<link href="assets/template/dist/css/style.min.css" rel="stylesheet">
<link href="assets/customClassPagination.css" rel="stylesheet">
<link href="assets/css/scroll-menu.css" rel="stylesheet"> 
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
<link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
<link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">


<?php
if ($direction_layout == 'rtl') {
?>
    <link href="https://fonts.googleapis.com/css?family=Tajawal&subset=arabic" rel="stylesheet">
    <style>
        * {
            font-family: 'Tajawal';
        }
    </style>
<?php
}
?>