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

require_once("../helpers/querys.php");
require_once("../loader.php");
require_once(__DIR__ . '/../helpers/ajax_guard.php');
require_login();

$user = new User;
$db = new Conexion;
$userData = $user->cdp_getUserData();

$dias_ = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
$meses_ = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec');

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$start = ($page - 1) * $perPage;
$userId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0;
$maxBatchNotificationIds = 100;

$sql = "SELECT a.user_id, b.shipping_type, a.id_notifi_user, b.notification_description, b.notification_date , b.order_id , a.notification_status, a.notification_read, b.notification_id
        FROM cdb_notifications_users AS a
        INNER JOIN cdb_notifications AS b ON a.notification_id = b.notification_id
        WHERE a.notification_read ='0' AND a.user_id = :user_id
        ORDER BY b.notification_id DESC
        LIMIT $start, $perPage";

$db->cdp_query($sql);
$db->bind(':user_id', $userId);
$db->cdp_execute();
$data = $db->cdp_registros();
$rowCount = $db->cdp_rowCount();

$bg = $rowCount > 0 ? 'bg-primary' : 'bg-danger';
?>

<ul class="list-style-none">
    <li>
        <div class="drop-title text-white <?php echo $bg; ?>">
            <h4 class="m-b-0 m-t-5"><?php echo $rowCount; ?></h4>
            <span class="font-light"><?php echo $lang['notification_title']; ?></span>
        </div>
    </li>
    <li>
        <div class="message-center notifications" id="messages">
            <?php if ($rowCount > 0) {
                foreach ($data as $key) {
                    $fecha = strtotime($key->notification_date);
                    $anio = date("Y", $fecha);
                    $mes = date("m", $fecha);
                    $dia = date("d", $fecha);
                    $hora = date("h", $fecha);
                    $minuto = date("i", $fecha);
                    $segundo = date("s", $fecha);

                    $href = ''; // Determina el enlace de la notificación
                    switch ($key->shipping_type) {
                        case '1':
                            $href = 'courier_view.php?id=' . $key->order_id . '&id_notification=' . $key->notification_id;
                            break;
                        case '2':
                            $href = 'consolidate_view.php?id=' . $key->order_id . '&id_notification=' . $key->notification_id;
                            break;
                        case '3':
                            $href = 'prealert_list.php?id_notification=' . $key->notification_id;
                            break;
                        case '4':
                            $href = 'customer_packages_view.php?id=' . $key->order_id . '&id_notification=' . $key->notification_id;
                            break;
                        case '5':
                            $href = 'consolidate_package_view.php?id=' . $key->order_id . '&id_notification=' . $key->notification_id;
                            break;
                        default:
                            $href = 'customers_edit.php?user=' . $key->order_id;
                            break;
                    }
            ?>

                    <a href="<?php echo $href; ?>" class="message-item">
                        <span><i class="mdi mdi-bell font-18"></i></span>
                        <span class="mail-contnet">
                            <h6 class="message-title"><?php echo $key->notification_description; ?></h6> 
                            <span class="time"><?php echo $meses_[$mes] . ' ' . $dia . ', ' . $anio . ' ' . $hora . ':' . $minuto . ':' . $segundo; ?></span>
                        </span>
                    </a>

            <?php
                    if ($key->notification_status == 0) {
                        // Se actualiza el estado en lote al final
                        $notificationIds[] = $key->notification_id;
                    }
                }
                if (!empty($notificationIds)) {
                    $ids = array_values(array_unique(array_map('intval', $notificationIds)));
                    $ids = array_slice($ids, 0, $maxBatchNotificationIds);
                    $placeholders = [];
                    foreach ($ids as $idx => $nid) {
                        $placeholders[] = ':nid_' . $idx;
                    }
                    $db->cdp_query("UPDATE cdb_notifications_users SET notification_status = 1 WHERE user_id = :scope_user_id AND notification_id IN (" . implode(',', $placeholders) . ")");
                    $db->bind(':scope_user_id', $userId);
                    foreach ($ids as $idx => $nid) {
                        $db->bind(':nid_' . $idx, $nid);
                    }
                    $db->cdp_execute();
                }
            }
            ?>
        </div>
    </li>
    <li>
        <a class="nav-link text-center m-b-5" href="notifications_list.php">
            <strong style="color: black"><?php echo $lang['notification_title2']; ?></strong>
            <i class="fa fa-angle-right"></i>
        </a>
    </li>
</ul>

<input type="hidden" id="countNotificationsInput" value="<?php echo $rowCount; ?>">
<script>
    $('#countNotifications').html('<?php echo $rowCount; ?>');
    if ($('#countNotificationsInput').val() > 0) {
        $('#countNotifications').addClass('bg-danger text-white');
    } else {
        $('#countNotifications').removeClass('bg-danger');
    }
</script>

<script>
    $("#messages").on('scroll', function() {
        currentScroll = $('#messages').scrollTop();
        $('#currentScroll').val(currentScroll);
    });
</script>
