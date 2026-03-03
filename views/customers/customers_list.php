<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************


require_once("helpers/querys.php");

$db = new Conexion;

$userData = $user->cdp_getUserData();

// Agencia (userlevel 6) o cualquier rol con permiso view_client_list
if (($userData->userlevel ?? 0) != 6 && !$user->cdp_hasPermission('view_client_list')) {
    header("location: error403.php");
    exit;
}

// Contexto de agencia (para estadísticas multi-tenant)
$ctx = cdp_getAgencyContext();
$whereAgency = '';
if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $whereAgency = " AND agency_id = " . (int)$ctx['agency_id'];
} elseif ($ctx['is_restricted']) {
    // Usuario restringido sin agencia válida: no mostrar datos de otras agencias
    $whereAgency = " AND 1=0";
}

// Contadores para mini dashboard (clientes: userlevel=1)
$db->cdp_query("SELECT COUNT(*) as total FROM cdb_users WHERE userlevel = 1" . $whereAgency);
$row = $db->cdp_registro();
$stats_total = (int) ($row ? $row->total : 0);

$db->cdp_query("SELECT COUNT(*) as total FROM cdb_users WHERE userlevel = 1 AND active = 1" . $whereAgency);
$row = $db->cdp_registro();
$stats_active = (int) ($row ? $row->total : 0);

$db->cdp_query("SELECT COUNT(*) as total FROM cdb_users WHERE userlevel = 1 AND active = 0" . $whereAgency);
$row = $db->cdp_registro();
$stats_inactive = (int) ($row ? $row->total : 0);

$db->cdp_query("SELECT COUNT(*) as total FROM cdb_users WHERE userlevel = 1 AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $whereAgency);
$row = $db->cdp_registro();
$stats_new = (int) ($row ? $row->total : 0);

$db->cdp_query("SELECT COUNT(*) as total FROM cdb_users WHERE userlevel = 1 AND created < DATE_SUB(NOW(), INTERVAL 30 DAY)" . $whereAgency);
$row = $db->cdp_registro();
$stats_previous = (int) ($row ? $row->total : 0);
$pct_total = $stats_previous > 0 ? round((($stats_total - $stats_previous) / $stats_previous) * 100) : 0;
$pct_active = $stats_total > 0 ? round(($stats_active / $stats_total) * 100) : 0;
$pct_inactive = $stats_total > 0 ? round(($stats_inactive / $stats_total) * 100) : 0;
$pct_new = $stats_total > 0 ? round(($stats_new / $stats_total) * 100) : 0;

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en"> 

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Meta Description (for search results) -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Author (content owner) -->
    <meta name="author" content="CODDINGPRO">
    <!-- Keywords (related keywords) -->
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph Meta (for social media sharing, like Facebook) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['filter6'] ?> | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        /* Mini dashboard estilo referencia: tarjetas blancas, icono Solar en módulo arriba-derecha */
        .customers-stats-card { background: #fff; border-radius: 0.5rem; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 2px 6px rgba(0,0,0,.06); transition: box-shadow .2s; overflow: hidden; }
        .customers-stats-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
        .customers-stats-card .card-body { padding: 1.25rem 1.25rem 1rem; position: relative; }
        .customers-stats-card .stat-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem; }
        .customers-stats-card .stat-title { font-size: 0.875rem; font-weight: 500; color: #566a7f; margin: 0; line-height: 1.3; }
        .customers-stats-card .stat-icon-module { width: 48px; height: 48px; border-radius: 0.5rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .customers-stats-card .stat-icon-module iconify-icon { font-size: 1.75rem !important; }
        .customers-stats-card .stat-icon-module.bg-label-primary { background: rgba(115,103,240,.14); color: #7367f0; }
        .customers-stats-card .stat-icon-module.bg-label-success { background: rgba(40,199,111,.14); color: #28c76f; }
        .customers-stats-card .stat-icon-module.bg-label-secondary { background: rgba(105,108,117,.12); color: #5e5873; }
        .customers-stats-card .stat-icon-module.bg-label-warning { background: rgba(255,159,67,.14); color: #ff9f43; }
        .customers-stats-card .stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; color: #2d3748; margin: 0 0 0.15rem 0; letter-spacing: -0.02em; }
        .customers-stats-card .stat-badge { font-size: 0.8125rem; font-weight: 600; margin-right: 0.25rem; }
        .customers-stats-card .stat-label { font-size: 0.8125rem; color: #a1acb8; margin: 0; line-height: 1.4; }
        .customers-stats-section-title { font-size: 0.8rem; font-weight: 600; color: #566a7f; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em; }
    </style>

</head>

<body>
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->


    <?php include 'views/inc/preloader.php'; ?>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="container-fluid">

            <!-- -------------------------------------------------------------- -->
              <!-- Mini Dashboard - Diseño exacto referencia (borde rojo): 4 tarjetas, icono Solar en módulo -->
              <!-- -------------------------------------------------------------- -->
              <div class="d-md-flex align-items-center">
                    <div>
                        <h3 class="card-title"><span><?php echo $lang['filter6']; ?></span></h3>
                    </div>
                </div>
                <div><br></div>
              <div class="row mb-4">
                <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                  <div class="card customers-stats-card h-100">
                    <div class="card-body">
                      <div class="stat-top">
                        <h6 class="stat-title"><?php echo isset($lang['filter6']) ? $lang['filter6'] : 'Total'; ?></h6>
                        <div class="stat-icon-module bg-label-primary">
                          <iconify-icon icon="solar:users-group-two-rounded-linear"></iconify-icon>
                        </div>
                      </div>
                      <h4 class="stat-value"><?php echo number_format($stats_total); ?></h4>
                      <?php if ($pct_total != 0): ?>
                      <span class="stat-badge <?php echo $pct_total >= 0 ? 'text-success' : 'text-danger'; ?>">(<?php echo $pct_total >= 0 ? '+' : ''; ?><?php echo $pct_total; ?>%)</span>
                      <?php endif; ?>
                      <p class="stat-label"><?php echo isset($lang['filter83']) ? $lang['filter83'] : 'Todos los clientes'; ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                  <div class="card customers-stats-card h-100">
                    <div class="card-body">
                      <div class="stat-top">
                        <h6 class="stat-title"><?php echo isset($lang['filter84']) ? $lang['filter84'] : 'Activos'; ?></h6>
                        <div class="stat-icon-module bg-label-success">
                          <iconify-icon icon="solar:user-check-linear"></iconify-icon>
                        </div>
                      </div>
                      <h4 class="stat-value"><?php echo number_format($stats_active); ?></h4>
                      <?php if ($stats_total > 0): ?>
                      <span class="stat-badge text-success">(<?php echo $pct_active; ?>%)</span>
                      <?php endif; ?>
                      <p class="stat-label"><?php echo isset($lang['filter84']) ? $lang['filter84'] : 'Cuentas activas'; ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                  <div class="card customers-stats-card h-100">
                    <div class="card-body">
                      <div class="stat-top">
                        <h6 class="stat-title"><?php echo isset($lang['filter85']) ? $lang['filter85'] : 'Inactivos'; ?></h6>
                        <div class="stat-icon-module bg-label-secondary">
                          <iconify-icon icon="solar:user-minus-linear"></iconify-icon>
                        </div>
                      </div>
                      <h4 class="stat-value"><?php echo number_format($stats_inactive); ?></h4>
                      <?php if ($stats_total > 0): ?>
                      <span class="stat-badge text-danger">(<?php echo $pct_inactive; ?>%)</span>
                      <?php endif; ?>
                      <p class="stat-label"><?php echo isset($lang['filter85']) ? $lang['filter85'] : 'Cuentas inactivas'; ?></p>
                    </div>
                  </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 mb-3 mb-lg-0">
                  <div class="card customers-stats-card h-100">
                    <div class="card-body">
                      <div class="stat-top">
                        <h6 class="stat-title">News</h6>
                        <div class="stat-icon-module bg-label-warning">
                          <iconify-icon icon="solar:user-plus-linear"></iconify-icon>
                        </div>
                      </div>
                      <h4 class="stat-value"><?php echo number_format($stats_new); ?></h4>
                      <?php if ($stats_total > 0): ?>
                      <span class="stat-badge text-success">(<?php echo $pct_new; ?>%)</span>
                      <?php endif; ?>
                      <p class="stat-label">Last 30 days</p>
                    </div>
                  </div>
                </div>
              </div>

            <!-- -------------------------------------------------------------- -->
              <!-- Start Page Content -->
              <!-- -------------------------------------------------------------- -->
              <div><br></div>
              <div class="widget-content searchable-container list">
                <!-- ---------------------
                            start Contact
                        ---------------- -->
                    <div class="card card-body">
                        

                      <div class="row">
                            <div class="col-md-8 col-xl-4">
                                <div class="col-sm-12 col-md-6 pull-right m-b-1">
                                    <div class="input-group input-group">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-danger"><iconify-icon icon="solar:magnifer-linear"></iconify-icon></button>
                                        </div>
                                        <input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['filter82']; ?>" onkeyup="cdp_load(1);">
                                    </div>
                                </div><!-- /.col -->

                                <div class="col-sm-12 col-md-6 pull-right m-b-1 mb-2"> <!-- Agregado mb-2 para el espacio -->
                                    <div class="input-group">
                                        <select onchange="cdp_load(1);" class="form-control custom-select" id="filterby" name="filterby">
                                            <option value="0"><?php echo $lang['filter83']; ?></option>
                                            <option value="1"><?php echo $lang['filter84']; ?></option>
                                            <option value="2"><?php echo $lang['filter85']; ?></option>
                                        </select>
                                    </div>
                                </div>   
                            </div>
                        <div
                          class="
                            col-md-4 col-xl-4
                            text-end
                            d-flex
                            justify-content-md-end justify-content-center
                            mt-3 mt-md-0
                          "
                        >
                          <a href="customers_add.php" id="btn-add-contact" class="btn btn-danger">
                            <i data-feather="users" class="feather-sm fill-white me-1"> </i>
                            <?php echo $lang['rolesp47']; ?></a
                          >
                        </div>
                      </div>
                    </div>
                    <!-- ---------------------
                                end Contact
                        ---------------- -->

                    <div class="row">
                        <!-- Column -->

                        <div class="col-lg-12 col-xl-12 col-md-12">

                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">

                                        <div class="outer_div"></div>

                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- Column -->
                    </div>
                </div>
               

            </div>
            <!-- ============================================================== -->
            <!-- End Page wrapper  -->
            <!-- ============================================================== -->
             <?php include 'views/inc/footer.php'; ?>
        </div>

        <!-- ============================================================== -->
        <!-- End Wrapper -->
        <!-- ============================================================== -->
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="dataJs/customers.js"></script>


</body>

</html>